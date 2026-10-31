# Infrastructure

L'API est le site principal, `https://api.amnesic.dev`, d'un Hébergement Web Pro OVHcloud. Elle y est déployée par l'intégration Git d'OVH depuis la branche `ovh`, construite par une GitHub Action ([ADR 0012](adr/0012-deploiement-git-ovh.md)). Les constantes de l'offre ont été relevées le 2026-10-26 dans la doc OVH (docs.ovhcloud.com) ; elles peuvent changer.

## Contraintes de l'offre Pro

| Point | Valeur | Effet sur l'API |
|---|---|---|
| PHP | 7.4 à 8.5, environnement `stable64` ; FPM derrière Apache 2.4 | PHP 8.4 |
| Limites PHP | `memory_limit` 512M, `max_execution_time` 165 s, `post_max_size` 130M, `php.ini` non modifiable | corps limité à 25 Mo par l'API |
| En-tête `Authorization` | n'arrive pas à PHP sans réglage | `CGIPassAuth On` dans `.htaccess` |
| Extensions | `pdo_mysql`, `mbstring`, `sodium`, `openssl`, `intl` ; pas de `mailparse` | sans objet : le Relais découpe le MIME |
| Bases | 10 MySQL 8.4 de 2 Go, 30 connexions simultanées, lecture seule au-delà du quota, inaccessibles hors d'OVH | une base dédiée ; contenu sur disque |
| Disque | 250 Go, stockage réseau partagé entre plusieurs serveurs web | pas de SQLite |
| Déploiement Git | GitHub seulement ; « Deploy Git » (ou le webhook) fait un `git pull` dans le dossier du site, sans Composer ni script ; dossier vide à l'association | `vendor/` construit par la GitHub Action dans la branche `ovh` |
| SSH | oui, sans root ; **pas de trafic sortant depuis une session SSH** | pas de `composer install` sur le serveur |
| Cron | une fois par heure au plus, minute non choisie, 60 min et 5 Mo de sortie par exécution, désactivé après 10 échecs | purge horaire |
| Mails MX Plan | ni pipe ni webhook vers un script | réception par push du Relais ([ADR 0001](adr/0001-reception-par-push.md)) |
| Filtres réseau | filtre amont non configurable (rafales, User-Agent) ; pare-feu ModSecurity optionnel qui peut bloquer `DELETE` | pare-feu applicatif désactivé |
| Sauvegardes | automatiques, fichiers et bases restaurés séparément | pas de sauvegarde applicative |

## Arborescence sur l'hébergement

```
/                       racine FTP ; /.ovhconfig inchangé (stable64)
/www/                   dossier du site api.amnesic.dev = clone de la branche ovh
  .htaccess             tout vers public/index.php
  .ovhconfig            app.engine.version=8.4
  public/index.php
  src/  bin/  migrations/  vendor/
  config.template.php  composer.json  composer.lock  .gitignore
  config.php            propre au serveur, ignoré par git
  storage/messages/     contenu des Messages, ignoré par git
```

`www/` est le dossier par défaut du site principal ; l'onglet « Mes sites » du Control Panel donne le vrai.

## Mise en place (une fois)

1. **Base** : Bases de données → créer une base MySQL 8.4 dédiée à l'API. Noter l'hôte (`<base>.mysql.db`), le nom et l'utilisateur ; le mot de passe, tu le saisis toi-même dans `config.php`.
2. **Site** : dans « Mes sites », la ligne `api.amnesic.dev`, sur le dossier `www/` : SSL Let's Encrypt actif, **pare-feu applicatif désactivé**. Vider son dossier (`www/`), après avoir sauvegardé ce qu'il contient : OVH n'associe Git qu'à un dossier vide.
3. **GitHub** : dépôt privé `4uruanna/amnesic.dev` ; la GitHub Action y crée la branche `ovh` au premier push sur `main`.
4. **Lier Git** : « Mes sites » → `api.amnesic.dev` → ⁝ → « Lier Git » : dépôt `git@github.com:4uruanna/amnesic.dev.git`, branche `ovh`.
   - **Clé SSH** : copier celle qu'affiche OVH et l'ajouter dans GitHub → dépôt `amnesic.dev` → Settings → **Deploy keys**, sans droit d'écriture. Le guide OVH propose de l'ajouter aux clés du compte : elle ouvrirait alors à OVH tous les dépôts du compte, en écriture.
   - **Webhook** : copier l'URL qu'affiche OVH et l'ajouter dans GitHub → dépôt `amnesic.dev` → Settings → Webhooks, événement `push`.
5. **Configuration** : une fois l'association active, copier `www/config.template.php` en `www/config.php` (FTP ou SSH) et le remplir.
6. **Installation** : quand `001_comptes.sql` est déployée (J02), ouvrir `https://api.amnesic.dev/v1/setup` (sans jeton : la base est vide), puis [créer un admin](#créer-un-admin).
7. **Cron** : voir [Purge](#purge).

### `.ovhconfig`

À la racine du dépôt, donc du dossier du site. Un `.ovhconfig` de dossier de site ne peut changer que la version de PHP ; l'environnement (`stable64`), le moteur et le pare-feu viennent de `/.ovhconfig`, à la racine FTP, qui reste tel quel.

```ini
app.engine.version=8.4
```

### `.htaccess`

À la racine du dépôt. Comme OVH sert le clone entier, toute requête est envoyée à `public/index.php` : aucun autre fichier (`.git/`, `src/`, `config.php`, `storage/`…) n'a d'URL, et l'API répond `404` à ces chemins. `/.git/`, `.htaccess` et `.ovhconfig` n'arrivent même pas jusqu'à elle : OVH les refuse en `403`.

```apache
CGIPassAuth On

RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

RewriteRule ^ public/index.php [END]
```

Si `CGIPassAuth` est refusé (erreur 500 dans les logs), le remplacer par `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1`. Les en-têtes de sécurité (HSTS, `nosniff`, `no-store`) sont posés par un middleware PHP, pas par Apache.

### `config.php`

Toute la configuration d'une installation tient dans `config.php`, à la racine de l'API (`www/config.php` sur le serveur, la racine du dépôt en local) : hors de `public/`, ignoré par git, donc jamais touché par un `git pull`. Le dépôt contient `config.template.php`, avec les mêmes constantes vides : le copier en `config.php` et le remplir.

```php
<?php

declare(strict_types=1);

const API_MAIL_DB_HOST = '';     // <base>.mysql.db sur OVH, 127.0.0.1 en local
const API_MAIL_DB_NAME = '';
const API_MAIL_DB_USER = '';
const API_MAIL_DB_PASSWORD = '';
```

`src/container.php` le charge par `require_once` et en tire le `PDO`. Il jette ce que le fichier écrit : un `config.php` qui commence par `<php` au lieu de `<?php` n'est que du texte, que PHP imprimerait sinon, mot de passe compris, en tête de chaque réponse ; ses constantes manquent alors, et l'API répond `500`. Le chemin de `storage/` n'est pas configurable : c'est toujours le dossier `storage/` voisin de `config.php`.

## Développement local

Le PHP du poste (8.5, sans `pdo_mysql`, `dom` ni `mbstring`) ne suffit pas : Composer et les tests tournent dans Docker, en PHP 8.4 comme en production. `composer.json` épingle aussi la plateforme à 8.4.0, pour que les dépendances restent installables sur OVH.

`compose.yaml` décrit l'environnement des tests : MySQL 8.4, dont le mot de passe root est `API_MAIL_DB_PASSWORD` du `config.php` local et dont les données vivent en mémoire (perdues à l'arrêt), et une image PHP 8.4 avec `pdo_mysql` (que `php:8.4-cli` n'a pas), qui partage le réseau de MySQL. Le `config.php` local vise donc `127.0.0.1` avec l'utilisateur `root`. MySQL y est réglé au plus juste (sans `performance_schema`, tampons et journal InnoDB minimaux, sans tables de fuseaux horaires) et limité à 192 Mio de RAM et un demi-CPU : environ 190 Mio au pic, données comprises, et une quinzaine de secondes de démarrage.

```sh
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app composer:2 install
API_MAIL_DB_PASSWORD="$(php -r 'require "config.php"; echo API_MAIL_DB_PASSWORD;')" docker compose up -d --wait mysql
docker compose run --rm --no-deps -u "$(id -u):$(id -g)" php vendor/bin/phpunit
php -S localhost:8080 -t public
```

`--no-deps` est nécessaire : sans `API_MAIL_DB_PASSWORD` dans l'environnement, Compose recréerait MySQL avec un mot de passe vide, que l'image refuse : MySQL s'arrêterait. `docker compose down` arrête MySQL.

Les tests d'intégration travaillent toujours sur la base `apimail_test` du serveur de `config.php`, quel que soit `API_MAIL_DB_NAME` : `tests/TestDatabase.php` la vide et lui réapplique `migrations/` avant chaque test qui l'utilise.

## Déploiement

Pas de script de déploiement. Un changement va en production ainsi :

1. fusion de `dev` dans `main`, puis push sur GitHub ;
2. la GitHub Action (`.github/workflows/ci.yml`) lance PHPUnit en PHP 8.4 avec un service MySQL 8.4, ainsi que les tests du Robot (job `robot`, [relais.md § Développement local et tests](relais.md#développement-local-et-tests)). Si tous passent, elle construit `vendor/` sans les dépendances de dev et ajoute sur `ovh` un commit qui ne contient que ce qui tourne : `.htaccess`, `.ovhconfig`, `public/`, `src/`, `bin/`, `migrations/`, `vendor/`, `config.template.php`, `composer.json`, `composer.lock`, et un `.gitignore` qui couvre `config.php` et `storage/`. Jamais `robot/`, qui tourne sur la machine du MX : la publication échoue si l'arbre en contient. Ce commit, dont le message porte le SHA de `main`, s'ajoute au précédent, même si rien de publié n'a changé : `ovh` n'est jamais poussée en force, donc le `git pull` d'OVH ne rencontre pas de conflit. Un push annule le run encore en cours du push précédent sur la même branche, et un run relancé sur un commit qui n'est plus la tête de `main` ne publie rien ;
3. le webhook prévient OVH, qui fait un `git pull` de `ovh` dans `www/` ; à défaut, « Mes sites » → ⁝ → « Deploy Git ». `config.php` et `storage/`, ignorés par git, ne sont pas touchés ;
4. si le déploiement apporte une migration : ouvrir `https://api.amnesic.dev/v1/setup?token=<jeton admin>`.

Sur un push ou une pull request vers une autre branche, l'Action lance seulement les tests. Ne jamais modifier sur le serveur un fichier suivi par git : la case « forcer » de « Deploy Git » l'écraserait.

## Migrations

Fichiers `migrations/NNN_<slug>.sql`, appliqués dans l'ordre par `Shared\Migrator`, qui note chaque fichier appliqué dans `schema_migrations`. En production, on les déclenche par `GET /v1/setup` ([ADR 0013](adr/0013-migrations-par-get-setup.md), [`api.md` § `GET /v1/setup`](api.md#get-v1setup)) :

- tant que `schema_migrations` n'existe pas (base vide), sans jeton ;
- ensuite avec le jeton d'un admin dans l'URL : `?token=…`. Ce jeton reste dans l'historique du navigateur et dans les logs d'accès d'OVH : utiliser un jeton admin dédié, nommé `setup`, et le révoquer quand il ne sert plus.

MySQL valide implicitement chaque `CREATE` ou `ALTER` : une migration qui échoue au milieu se corrige à la main avant de rappeler `/v1/setup`. Une migration appliquée ne se modifie jamais ; on en ajoute une nouvelle.

## Purge

Control Panel → Hébergement → Tâches planifiées - Cron → Ajouter :

- commande : `www/bin/purge.php` (chemin depuis la racine FTP) ;
- langage : PHP 8.4 ;
- fréquence : toutes les heures.

Le script supprime par lots de 500 les Messages reçus il y a plus de 30 jours, par le même code que `DELETE /v1/messages/{id}`, et écrit `purged <n>` dans le log cron d'OVH ([ADR 0009](adr/0009-purge-apres-30-jours.md)). Après 10 échecs consécutifs, OVH désactive la tâche : surveiller le rapport quotidien par mail.

## Créer un admin

L'API n'a pas de commande d'amorçage : le premier `admin`, comme un `admin` de secours, se crée en SQL, dans phpMyAdmin (Control Panel → Bases de données → la base → phpMyAdmin), une fois les tables créées par `/v1/setup`. Changer `'admin'` si ce nom existe déjà.

```sql
SET @token = LOWER(HEX(RANDOM_BYTES(32)));
SET @id = UUID();
INSERT INTO accounts (id, name, role, created_at) VALUES (@id, 'admin', 'admin', UTC_TIMESTAMP(3));
INSERT INTO tokens (id, account_id, name, hash, created_at)
  VALUES (UUID(), @id, 'bootstrap', SHA2(@token, 256), UTC_TIMESTAMP(3));
SELECT LEFT(@token, 32) AS debut, RIGHT(@token, 32) AS fin;
```

phpMyAdmin tronque à l'affichage tout texte de plus de 50 caractères : la requête montre donc le jeton en deux moitiés, à coller bout à bout, 64 caractères en tout. Ne pas raccourcir `RANDOM_BYTES(32)` : l'API refuse tout jeton qui ne fait pas 64 caractères. Le jeton n'est stocké nulle part en clair : le copier tout de suite. `UUID()` produit un UUID v1 et non v7, sans conséquence pour un Compte.

## Sauvegardes

On s'appuie sur les sauvegardes automatiques d'OVH (fichiers et bases). Elles se restaurent séparément : restaurer la base et `www/storage/` à la même date. Un dossier de `storage/` sans ligne en base est inoffensif ; une ligne sans dossier donnerait une erreur à la lecture du Message, d'où l'ordre « fichiers d'abord » au dépôt ([`data-model.md` § Fichiers](data-model.md#fichiers)). Le code, lui, se redéploie depuis `ovh`.
