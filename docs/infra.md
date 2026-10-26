# Infrastructure

L'API tourne sur l'Hébergement Web Pro OVHcloud qui héberge déjà un autre site. Les constantes de l'offre ont été relevées le 2026-10-26 dans la doc OVH (docs.ovhcloud.com) ; elles peuvent changer.

## Contraintes de l'offre Pro

| Point | Valeur | Effet sur l'API |
|---|---|---|
| PHP | 7.4 à 8.5, environnement `stable64` ; FPM derrière Apache 2.4 | PHP 8.4 |
| Limites PHP | `memory_limit` 512M, `max_execution_time` 165 s, `post_max_size` 130M, `php.ini` non modifiable | corps limité à 25 Mo par l'API |
| En-tête `Authorization` | n'arrive pas à PHP sans réglage | `CGIPassAuth On` dans `.htaccess` |
| Extensions | `pdo_mysql`, `mbstring`, `sodium`, `openssl`, `intl` ; pas de `mailparse` | sans objet : le Relais découpe le MIME |
| Bases | 10 MySQL 8.4 de 2 Go, 30 connexions simultanées, lecture seule au-delà du quota, inaccessibles hors d'OVH | une base dédiée ; contenu sur disque |
| Disque | 250 Go, stockage réseau partagé entre plusieurs serveurs web | pas de SQLite |
| SSH | oui, sans root ; **pas de trafic sortant depuis une session SSH** ; MySQL joignable | pas de `composer install` sur le serveur |
| Cron | une fois par heure au plus, minute non choisie, 60 min et 5 Mo de sortie par exécution, désactivé après 10 échecs | purge horaire |
| Mails MX Plan | ni pipe ni webhook vers un script | réception par push du Relais ([ADR 0001](adr/0001-reception-par-push.md)) |
| Filtres réseau | filtre amont non configurable (rafales, User-Agent) ; pare-feu ModSecurity optionnel qui peut bloquer `DELETE` | pare-feu applicatif désactivé |
| Sauvegardes | automatiques, fichiers et bases restaurés séparément | pas de sauvegarde applicative |

## Arborescence sur l'hébergement

```
/                          racine FTP : le site existant ; /.ovhconfig inchangé (stable64)
/apimail/
  public/                  racine du site api.<domaine>
    index.php
    .htaccess
    .ovhconfig
  src/
  bin/                     migrate.php, purge.php
  migrations/
  vendor/
  config.php               propre au serveur, jamais déployé
  storage/messages/        contenu des Messages, jamais touché par le déploiement
```

## Mise en place (une fois)

Actions dans le Control Panel OVH :

1. **Base** : Bases de données → créer une base MySQL 8.4 dédiée à l'API. Noter l'hôte (`<base>.mysql.db`), le nom et l'utilisateur ; le mot de passe, tu le saisis toi-même dans `config.php`.
2. **Multisite** : ajouter le sous-domaine `api.<domaine>`, dossier racine `apimail/public`, **pare-feu applicatif désactivé**, SSL activé (Let's Encrypt, automatique dès que le DNS pointe sur l'hébergement).
3. **SSH** : récupérer l'hôte (`ssh.clusterXXX.hosting.ovh.net`) et l'utilisateur FTP/SSH, et y déposer ta clé publique.
4. **Configuration** : copier `config.example.php` en `/apimail/config.php` sur le serveur et le remplir.
5. **Cron** : voir [Purge](#purge).

### `public/.ovhconfig`

Un `.ovhconfig` dans le dossier d'un site ne peut changer que sa version de PHP ; l'environnement (`stable64`), le moteur et le pare-feu viennent de `/.ovhconfig`, qui sert aussi l'autre site et reste tel quel.

```ini
app.engine.version=8.4
```

### `public/.htaccess`

```apache
CGIPassAuth On

RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,L]
```

Si `CGIPassAuth` est refusé (erreur 500 dans les logs), le remplacer par `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1`. Les en-têtes de sécurité (HSTS, `nosniff`, `no-store`) sont posés par un middleware PHP, pas par Apache.

### `config.php`

Hors du dossier public, absent du dépôt git ; le dépôt contient `config.example.php`, mêmes clés, valeurs vides.

```php
<?php
return [
    'db' => [
        'dsn' => 'mysql:host=<base>.mysql.db;dbname=<base>;charset=utf8mb4',
        'user' => '',
        'password' => '',
    ],
    'storage' => __DIR__ . '/storage',
];
```

## Développement local

Le PHP du poste (8.5, sans `pdo_mysql`, `dom` ni `mbstring`) ne suffit pas : Composer et les tests tournent dans Docker, en PHP 8.4 comme en production. `composer.json` épingle aussi la plateforme à 8.4.0, pour que les dépendances restent installables sur OVH.

```sh
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app composer:2 install
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app php:8.4-cli vendor/bin/phpunit
php -S localhost:8080 -t public
```

À partir de J01-2 : MySQL 8.4 dans un conteneur (`MYSQL_ROOT_PASSWORD` lu dans `APIMAIL_DB_PASSWORD`, à définir dans ton shell), une image PHP 8.4 avec `pdo_mysql` (que `php:8.4-cli` n'a pas), et les tests d'intégration contre une base `apimail_test`.

## Déploiement

`deploy.sh`, lancé à la main depuis le poste :

1. copie le dépôt dans un dossier temporaire et y lance `composer install --no-dev --optimize-autoloader` (le `vendor/` local garde PHPUnit) ;
2. envoie seulement `public/`, `src/`, `bin/`, `migrations/` et `vendor/` : `rsync -az --delete public src bin migrations vendor "$OVH_SSH:apimail/"`. Comme `storage/` et `config.php` ne sont pas dans la liste, `--delete` n'y touche jamais ;
3. applique les migrations : `ssh "$OVH_SSH" /usr/local/php8.4/bin/php apimail/bin/migrate.php`.

`OVH_SSH` (`utilisateur@ssh.clusterXXX.hosting.ovh.net`) est à définir dans ton shell. En SSH, appeler PHP par son chemin complet : le `php` par défaut n'est pas forcément la 8.4.

## Migrations

Fichiers `migrations/NNN_<slug>.sql`, appliqués dans l'ordre par `bin/migrate.php`, qui note chaque fichier appliqué dans `schema_migrations`. MySQL valide implicitement chaque `CREATE` ou `ALTER` : une migration qui échoue au milieu se corrige à la main avant de relancer. Une migration appliquée ne se modifie jamais ; on en ajoute une nouvelle.

## Purge

Control Panel → Hébergement → Tâches planifiées - Cron → Ajouter :

- commande : `apimail/bin/purge.php` (chemin depuis la racine FTP) ;
- langage : PHP 8.4 ;
- fréquence : toutes les heures.

Le script supprime par lots de 500 les Messages reçus il y a plus de 30 jours, par le même code que `DELETE /v1/messages/{id}`, et écrit `purged <n>` dans le log cron d'OVH ([ADR 0009](adr/0009-purge-apres-30-jours.md)). Après 10 échecs consécutifs, OVH désactive la tâche : surveiller le rapport quotidien par mail.

## Créer un admin

L'API n'a pas de commande d'amorçage : le premier `admin`, comme un `admin` de secours, se crée en SQL, dans phpMyAdmin (Control Panel → Bases de données → la base → phpMyAdmin) ou en SSH avec `mysql`. Changer `'admin'` si ce nom existe déjà.

```sql
SET @token = LOWER(HEX(RANDOM_BYTES(32)));
SET @id = UUID();
INSERT INTO accounts (id, name, role, created_at) VALUES (@id, 'admin', 'admin', UTC_TIMESTAMP(3));
INSERT INTO tokens (id, account_id, name, hash, created_at)
  VALUES (UUID(), @id, 'bootstrap', SHA2(@token, 256), UTC_TIMESTAMP(3));
SELECT @token AS token;
```

Le jeton affiché n'est stocké nulle part en clair : le copier tout de suite. `UUID()` produit un UUID v1 et non v7, sans conséquence pour un Compte.

## Sauvegardes

On s'appuie sur les sauvegardes automatiques d'OVH (fichiers et bases). Elles se restaurent séparément : restaurer la base et `storage/` à la même date. Un dossier de `storage/` sans ligne en base est inoffensif ; une ligne sans dossier donnerait une erreur à la lecture du Message, d'où l'ordre « fichiers d'abord » au dépôt ([`data-model.md` § Fichiers](data-model.md#fichiers)).
