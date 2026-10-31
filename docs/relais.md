# Relais

Le Relais reçoit les mails du MX et les dépose dans l'API ([ADR 0014](adr/0014-relais-mailpit-et-robot.md)). Il tient en deux conteneurs Docker sur la machine qui reçoit le MX. `mailpit` reçoit en SMTP et garde chaque mail en attente. `robot` relève Mailpit toutes les 5 secondes, convertit chaque mail en `MessageDeposit` et le dépose par `POST /v1/messages` avec le Jeton d'un Bot ([ADR 0015](adr/0015-releve-toutes-les-5-secondes.md)). Leur code et leur configuration vivent dans `robot/`, que la branche `ovh` ne contient jamais.

```
Internet ──SMTP 25──▶ mailpit ◀──HTTP 8025── robot ──HTTPS──▶ https://api.amnesic.dev/v1/messages
```

## Arborescence

```
robot/
  compose.yaml       conteneurs mailpit et robot
  Dockerfile         image du robot : php:8.4-cli, dépendances sans dev
  .dockerignore      n'envoie à l'image que composer.*, bin/ et src/ : ni .env, ni auth.txt, ni data/
  composer.json      projet Composer à part : autoload Robot\ → src/, PHPUnit en dev
  phpunit.xml
  .env.template      variables de .env, sans valeur secrète
  bin/relais.php     configuration, puis un cycle toutes les POLL_SECONDS
  src/
    Mailpit.php      client de l'API REST de Mailpit
    Apimail.php      client de POST /v1/messages
    Conversion.php   message Mailpit → MessageDeposit
    Relais.php       un cycle : relever, déposer, puis supprimer ou étiqueter
  tests/
    fixtures/        messages Mailpit capturés (GET /api/v1/message/{ID}), avec le contenu de leurs parts
    apimail.php      la vraie API sur apimail_test : routeur de php -S, ou remise à neuf de la base en CLI
  .env, auth.txt, data/, vendor/, compose.override.yaml   propres à chaque installation, ignorés par git
```

Le robot ne dépend que de PHP et de `ext-curl`, déjà dans l'image `php:8.4-cli` : pas de bibliothèque HTTP. Il ne partage aucun code avec l'API ; leur seul contrat est [`api.md` § `POST /v1/messages`](api.md#post-v1messages).

## Conteneurs

`compose.yaml` reprend le Mailpit installé le 2026-10-18 sur la machine.

| | `mailpit` | `robot` |
|---|---|---|
| Image | `axllent/mailpit:v1.32.0`, version épinglée (1.30 ou plus, pour `--max-message-size`), la même dans `compose.yaml` à la racine et dans la CI | construite depuis `Dockerfile` |
| Ports | `25:1025` (SMTP, redirigé par la box) ; `127.0.0.1:8025:8025` (interface et API, la machine seule) | aucun |
| Données | `./data:/data` (`mailpit.db`) et `./auth.txt:/auth.txt:ro`, que l'utilisateur `10001` doit pouvoir écrire et lire | aucune : tout l'état est dans Mailpit |
| Durcissement | utilisateur `10001:10001`, `read_only`, `tmpfs: /tmp`, `cap_drop: ALL`, `no-new-privileges` | les mêmes |
| Journaux | pilote `local` | pilote `local` |
| Redémarrage | `unless-stopped` | `unless-stopped`, `init: true` ; démarre quand `mailpit` est sain (`/mailpit readyz`, le contrôle de l'image) |

Options de Mailpit : `--database=/data/mailpit.db`, `--max=2000`, `--max-age=7d`, `--max-message-size=25`, `--smtp-max-recipients=5`, `--smtp-allowed-recipients=${MAILPIT_ALLOWED_RECIPIENTS}`, `--ui-auth-file=/auth.txt`, `--tags-disable=plus-addresses,x-tags`. La dernière réserve les étiquettes au robot : sans elle, un mail envoyé à `ci+rejet@…` ou avec un en-tête `X-Tags: rejet` serait étiqueté `rejet` dès son arrivée, et jamais déposé. N'ajouter aucune option `--smtp-relay-*` : Mailpit deviendrait un relais ouvert.

Le robot joint Mailpit par le réseau de Compose, à `http://mailpit:8025`, avec l'authentification de l'interface (`auth.txt`).

## Configuration

`robot/.env`, copié de `.env.template` et rempli sur la machine. Compose le passe en entier au robot (`env_file`) ; `mailpit` n'en reçoit que `MAILPIT_ALLOWED_RECIPIENTS`, dans sa commande, et Compose refuse de démarrer si elle est vide : Mailpit accepterait alors tous les Destinataires. Une valeur qui contient `$`, `#` ou une espace s'écrit entre apostrophes.

| Variable | Valeur |
|---|---|
| `APIMAIL_URL` | `https://api.amnesic.dev` |
| `APIMAIL_TOKEN` | Jeton du Bot `relais-mx`, 64 caractères hexadécimaux : secret |
| `MAILPIT_URL` | `http://mailpit:8025` depuis le conteneur (`http://127.0.0.1:8025` depuis la machine) |
| `MAILPIT_USER` | `mailpit` |
| `MAILPIT_PASS` | le mot de passe de `MAILPIT_USER` dans `auth.txt` : secret |
| `MAILPIT_ALLOWED_RECIPIENTS` | expression des Destinataires acceptés en SMTP, par exemple `'(?i)@qa\.mondomaine\.fr$'` : `(?i)` ignore la casse, sans quoi Mailpit refuse `ci@QA.mondomaine.fr`, que l'API aurait accepté en minuscules |
| `POLL_SECONDS` | `5` |

Au démarrage, une variable absente ou vide arrête le robot avec un message qui la nomme, sans jamais afficher de valeur.

## Cycle

Toutes les `POLL_SECONDS` secondes, le robot :

1. demande à Mailpit les messages sans étiquette `rejet` : `GET /api/v1/search?query=!tag:rejet`, par pages de 50, jusqu'à une page incomplète ; Mailpit les donne du plus récent au plus ancien, et un mail arrivé entre deux pages, qui décale les suivantes, n'est compté qu'une fois ;
2. traite chacun, du plus ancien au plus récent, pour que l'API les reçoive dans l'ordre :
   1. `GET /api/v1/message/{ID}`, puis `GET /api/v1/message/{ID}/part/{PartID}` pour chaque pièce ;
   2. le convertit ([Conversion](#conversion)) ;
   3. le dépose par `POST /v1/messages`, avec un délai de 300 s, et agit selon la réponse ([Erreurs](#erreurs)).

Un cycle plus long que `POLL_SECONDS` enchaîne directement sur le suivant. Un seul robot tourne à la fois : deux robots déposeraient deux fois les mêmes mails.

## Conversion

| `MessageDeposit` | Message Mailpit |
|---|---|
| `from` | `From` : `{ "address": Address, "name": Name }`, un `Name` vide devient `null` |
| `to` | `To`, même règle ; `Cc` et `Bcc` sont ignorés ([ADR 0002](adr/0002-seul-to-donne-acces.md)) |
| `subject` | `Subject` |
| `sentAt` | `Date` |
| `text`, `html` | `Text`, `HTML` ; une chaîne vide devient `null` |
| `attachments` | `Attachments` puis `Inline`, dans cet ordre : `filename` = `FileName`, ou `partie-<PartID>` s'il est vide ; `contentType` = `ContentType` ; `content` = le contenu de la part, en base64 |

Ce que Mailpit donne, relevé sur la version 1.32 : pour un mail en HTML seul, il tire `Text` du HTML ; une image inline sans nom de fichier prend son `Content-ID` pour `FileName` ; une pièce qui n'a ni nom de fichier ni `Content-ID` ne figure ni dans `Attachments` ni dans `Inline`, et n'est donc pas déposée. Un mail sans en-tête `From` a un `From` vide, et sans en-tête `Date`, sa date de réception.

Le robot ne valide rien lui-même : l'API est seule juge du contrat. Un mail sans `To` (envoyé en `Bcc` seul), une adresse invalide, un sujet de plus de 998 caractères ou une `Date` qui sort, en UTC, des années 0000 à 9999 reçoivent donc `422` et restent dans Mailpit, étiquetés. Un mail proche des 25 Mo de `--max-message-size` peut dépasser, une fois en JSON, les 25 Mio du dépôt : il reçoit `413`, avec le même sort.

## Erreurs

| Réponse | Action du robot |
|---|---|
| `201` | supprime le message de Mailpit : `DELETE /api/v1/messages`, `{ "IDs": [ID] }` |
| `400`, `413`, `415`, `422` | l'étiquette `rejet` (`PUT /api/v1/tags`) : il reste visible dans l'interface de Mailpit jusqu'à `--max-age` ou `--max`, et le robot ne le reprend plus |
| `400` de Mailpit sur le message ou une de ses parts | la même étiquette, sans dépôt : Mailpit ne saura jamais le rendre. C'est le cas d'un en-tête `Date` en `+2400`, qu'il lit mais ne sait pas écrire en JSON |
| `401`, `403` | jeton faux ou révoqué : ne touche à rien, abandonne le cycle ; le suivant réessaie |
| `5xx`, tout autre code (une redirection, la `404` d'une `APIMAIL_URL` fausse…), délai dépassé, erreur réseau | ne touche à rien, abandonne le cycle ; le suivant réessaie |
| Mailpit injoignable, ou toute autre réponse de Mailpit que `200` et `400` (un mail supprimé dans l'interface pendant le cycle…) | abandonne le cycle ; le suivant réessaie |

Abandonner le cycle au premier échec garde l'ordre de réception : un mail n'est jamais déposé avant un plus ancien qui attend encore. Un mail qui reçoit toujours `5xx` bloque donc ceux qui le suivent, jusqu'à la correction de l'API ou jusqu'à `--max-age` ; le journal le signale à chaque cycle.

Un mail en attente n'est perdu que lorsque Mailpit le supprime de lui-même : au bout de `--max-age` (7 jours), ou dès que Mailpit garde plus de `--max` mails (2000, ceux étiquetés `rejet` compris), le plus ancien d'abord, sans que le robot le journalise.

Pour reprendre un mail rejeté, après une correction de l'API par exemple, retirer son étiquette `rejet` dans l'interface de Mailpit.

Si la réponse `201` se perd (délai dépassé) ou si la suppression échoue, le mail est déposé une seconde fois au cycle suivant. Ce doublon est accepté : l'API ne dédoublonne pas.

## Journal

Une ligne par événement sur la sortie standard, lue par `docker compose logs robot`, sans jamais de jeton ni de contenu de mail :

- `started`, au démarrage ;
- `deposited <ID Mailpit> <id apimail>` ;
- `rejected <ID Mailpit> <code> <field>: <message>`, une par erreur de la réponse ; une `400`, `413` ou `415`, qui n'a pas d'`errors`, donne une seule ligne, avec un `field` vide et son `detail` ; `rejected <ID Mailpit> mailpit 400` pour un mail que Mailpit ne sait pas rendre ;
- `retry <cause>`, quand un cycle est abandonné : `retry apimail 401`, `retry apimail 503`, `retry apimail: <erreur de curl>` (délai, réseau), `retry mailpit 404`, `retry mailpit: <erreur de curl>`.

## Déploiement

Une fois :

1. **Clé** : sur la machine, une clé SSH dédiée ; sa partie publique dans GitHub → dépôt `amnesic.dev` → Settings → Deploy keys, sans droit d'écriture.
2. **Clone** : `git clone git@github.com:4uruanna/amnesic.dev.git apimail`.
3. **Bot** : avec un jeton admin, `POST /v1/accounts` crée `relais-mx` (`bot`), `POST /v1/accounts/{id}/tokens` lui donne le jeton de `APIMAIL_TOKEN`.
4. **Configuration** : dans `apimail/robot/`, `.env` copié de `.env.template` et rempli ; `auth.txt` et `data/` repris du Mailpit en place, après l'avoir arrêté, puis donnés à l'utilisateur des conteneurs : `sudo chown -R 10001:10001 data auth.txt`.
5. **Démarrage** : `docker compose up -d --build` dans `robot/`. Au premier cycle, le robot dépose tous les mails que Mailpit garde encore : les supprimer avant dans l'interface si on n'en veut pas.

Mise à jour : `git pull && docker compose up -d --build` dans `robot/`. Rien n'est automatique : un push sur `main` ne touche pas la machine.

## Développement local et tests

- **Unitaires** : `Conversion`, sur des messages Mailpit en JSON capturés sur un vrai Mailpit.
- **Intégration** : un cycle contre un vrai Mailpit en conteneur et la vraie API (`php -S` sur `public/`, base `apimail_test`), sans mocks. Les mails de test entrent par l'API d'envoi de Mailpit, `POST /api/v1/send`.
- **CI** : un job `robot` dans `.github/workflows/ci.yml`, avec les services MySQL et Mailpit. Le job `publish` attend aussi celui-ci, et échoue si l'arbre publié sur `ovh` contient `robot/`.

Comme pour l'API, Composer et PHPUnit tournent dans Docker, en PHP 8.4, dans l'environnement de [infra.md § Développement local](infra.md#développement-local) : les tests d'intégration lancent l'API par `php -S` et ont besoin de son `vendor/`, de son `config.php`, de MySQL et du service `mailpit` de `compose.yaml`, qui partage le réseau de MySQL comme `php` (identifiants `mailpit` / `test` de `robot/phpunit.xml`, jetables ; les mails dont l'en-tête `Date` compte entrent en SMTP, par le port 1025).

Les limites d'OVH (`post_max_size=130M`, `memory_limit=512M`) sont aussi celles du `php -S` des tests : sans elles, un dépôt de plus de 8 Mio recevrait `200` au lieu de `201`. Depuis la racine du dépôt :

```sh
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD/robot":/app -w /app composer:2 install
API_MAIL_DB_PASSWORD="$(php -r 'require "config.php"; echo API_MAIL_DB_PASSWORD;')" docker compose up -d --wait mysql mailpit
docker compose run --rm --no-deps -u "$(id -u):$(id -g)" -w /app/robot php vendor/bin/phpunit
```

`tests/apimail.php` sert l'API avec la base `apimail_test`, quel que soit `API_MAIL_DB_NAME`, comme les tests de l'API ; il emprunte à ceux-ci `TestApp` et `TestDatabase`, le robot lui-même n'utilise aucun code de l'API.

Pour faire tourner le Relais sur le poste, dans `robot/` : `.env` rempli ; `auth.txt` écrit par `php -r 'echo "mailpit:", password_hash($argv[1], PASSWORD_BCRYPT), "\n";' "<mot de passe>" > auth.txt`, le même mot de passe dans `MAILPIT_PASS` ; `data/` donné à l'utilisateur `10001` sans `sudo` par `docker run --rm -v "$PWD/data:/data" busybox chown 10001:10001 /data` ; puis `docker compose up -d --build`. Pour ne pas ouvrir le port 25 du poste, un `compose.override.yaml`, ignoré par git, remplace les ports de `mailpit` :

```yaml
services:
  mailpit:
    ports: !override
      - 127.0.0.1:2525:1025
      - 127.0.0.1:8025:8025
```

Pour que ce Relais dépose dans l'API locale (la démo de J06), depuis la racine du dépôt, MySQL lancé :

```sh
docker compose run --rm --no-deps php php robot/tests/apimail.php   # remet apimail_test à neuf ; jetons du Bot et de ci
docker compose run -d --no-deps --name apimail-demo-api -e APIMAIL_TEST_STORAGE=/tmp/demo php php -d post_max_size=130M -d memory_limit=512M -S 0.0.0.0:8080 robot/tests/apimail.php
(cd robot && docker compose up -d mailpit)
docker network connect --alias api robot_default apimail-mysql-1      # l'API, sur le réseau de mysql, devient http://api:8080
(cd robot && docker compose up -d --build robot)
```

avec, dans `robot/.env`, `APIMAIL_URL=http://api:8080` et le jeton du Bot dans `APIMAIL_TOKEN`.
