# J01-2 Base et migrations

**Dépend de** : J01-5
**Docs** : [infra.md § `config.php`](../../docs/infra.md#configphp), [§ Développement local](../../docs/infra.md#développement-local), [§ Migrations](../../docs/infra.md#migrations), [data-model.md § Tables](../../docs/data-model.md#tables)

## Objectif

Une connexion MySQL configurée par `config.php` et un outil de migration rejouable.

## Contenu

- `container.php` charge `config.php` par `require_once` (constantes `API_MAIL_*` de `config.template.php`, déjà dans le dépôt), avec une erreur claire s'il manque.
- `Shared\Database`, qui fournit le `PDO` du conteneur : `charset=utf8mb4`, `SET time_zone = '+00:00'`, `ERRMODE_EXCEPTION`, `EMULATE_PREPARES` à `false`.
- `Shared\Migrator` : crée `schema_migrations` si besoin, puis applique dans l'ordre, sous verrou `GET_LOCK`, les `migrations/*.sql` qui n'y figurent pas, en notant chacun ; renvoie la liste des fichiers appliqués. L'endpoint `GET /v1/setup` qui l'appelle n'arrive qu'en J02-2, avec la première migration ([ADR 0013](../../docs/adr/0013-migrations-par-get-setup.md)).
- Base `apimail_test` et un utilitaire de test qui la vide et la remigre.
- Environnement Docker local : MySQL 8.4 et une image PHP 8.4 avec `pdo_mysql` pour les tests (ni `php:8.4-cli` ni le PHP du poste n'ont `pdo_mysql`) ; `infra.md` § Développement local mis à jour.

## Actions de ta part

- Copier `config.template.php` en `config.php` et le remplir pour le MySQL local (`API_MAIL_DB_PASSWORD` : la valeur de ton choix).

## Terminé quand

- Tests d'intégration verts sur `apimail_test` : des migrations de test s'appliquent dans l'ordre et sont notées ; un second passage n'applique rien.
