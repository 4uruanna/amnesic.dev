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
  .env, auth.txt, data/, vendor/, compose.override.yaml   propres à chaque installation, ignorés par git
```

Le robot ne dépend que de PHP et de `ext-curl`, déjà dans l'image `php:8.4-cli` : pas de bibliothèque HTTP. Il ne partage aucun code avec l'API ; leur seul contrat est [`api.md` § `POST /v1/messages`](api.md#post-v1messages).

## Conteneurs

`compose.yaml` reprend le Mailpit installé le 2026-10-18 sur la machine.

| | `mailpit` | `robot` |
|---|---|---|
| Image | `axllent/mailpit:v1.32.0`, version épinglée (1.30 ou plus, pour `--max-message-size`) | construite depuis `Dockerfile` |
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
| `MAILPIT_ALLOWED_RECIPIENTS` | expression des Destinataires acceptés en SMTP, par exemple `@qa\.mondomaine\.fr$` |
| `POLL_SECONDS` | `5` |

Au démarrage, une variable absente ou vide arrête le robot avec un message qui la nomme, sans jamais afficher de valeur.

## Cycle

Toutes les `POLL_SECONDS` secondes, le robot :

1. demande à Mailpit les messages sans étiquette `rejet` : `GET /api/v1/search?query=!tag:rejet`, par pages de 50 ;
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

Le robot ne valide rien lui-même : l'API est seule juge du contrat. Un mail sans `To` (envoyé en `Bcc` seul), une adresse invalide ou un sujet de plus de 998 caractères reçoivent donc `422` et restent dans Mailpit, étiquetés. Un mail proche des 25 Mo de `--max-message-size` peut dépasser, une fois en JSON, les 25 Mio du dépôt : il reçoit `413`, avec le même sort.

## Erreurs

| Réponse | Action du robot |
|---|---|
| `201` | supprime le message de Mailpit : `DELETE /api/v1/messages`, `{ "IDs": [ID] }` |
| `400`, `413`, `415`, `422` | l'étiquette `rejet` (`PUT /api/v1/tags`) : il reste visible dans l'interface de Mailpit jusqu'à `--max-age`, et le robot ne le reprend plus |
| `401`, `403` | jeton faux ou révoqué : ne touche à rien, abandonne le cycle ; le suivant réessaie |
| `5xx`, délai dépassé, erreur réseau | ne touche à rien ; le cycle suivant réessaie |
| Mailpit injoignable | abandonne le cycle ; le suivant réessaie |

Pour reprendre un mail rejeté, après une correction de l'API par exemple, retirer son étiquette `rejet` dans l'interface de Mailpit.

Si la réponse `201` se perd (délai dépassé) ou si la suppression échoue, le mail est déposé une seconde fois au cycle suivant. Ce doublon est accepté : l'API ne dédoublonne pas.

## Journal

Une ligne par événement sur la sortie standard, lue par `docker compose logs robot`, sans jamais de jeton ni de contenu de mail :

- `started`, au démarrage ;
- `deposited <ID Mailpit> <id apimail>` ;
- `rejected <ID Mailpit> <code> <field>: <message>`, une par erreur de la réponse ;
- `retry <cause>`, quand un cycle est abandonné.

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

Comme pour l'API, Composer et PHPUnit tournent dans Docker, en PHP 8.4 ([infra.md § Développement local](infra.md#développement-local)) :

```sh
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD/robot":/app -w /app composer:2 install
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD/robot":/app -w /app php:8.4-cli vendor/bin/phpunit
```

Pour faire tourner le Relais sur le poste, dans `robot/` : `.env` rempli ; `auth.txt` écrit par `php -r 'echo "mailpit:", password_hash($argv[1], PASSWORD_BCRYPT), "\n";' "<mot de passe>" > auth.txt`, le même mot de passe dans `MAILPIT_PASS` ; `data/` donné à l'utilisateur `10001` sans `sudo` par `docker run --rm -v "$PWD/data:/data" busybox chown 10001:10001 /data` ; puis `docker compose up -d --build`. Pour ne pas ouvrir le port 25 du poste, un `compose.override.yaml`, ignoré par git, remplace les ports de `mailpit` :

```yaml
services:
  mailpit:
    ports: !override
      - 127.0.0.1:2525:1025
      - 127.0.0.1:8025:8025
```
