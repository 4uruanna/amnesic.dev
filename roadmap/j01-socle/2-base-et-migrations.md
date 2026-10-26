# J01-2 Base et migrations

**Dépend de** : J01-1
**Docs** : [infra.md § `config.php`](../../docs/infra.md#configphp), [§ Développement local](../../docs/infra.md#développement-local), [§ Migrations](../../docs/infra.md#migrations), [data-model.md § Tables](../../docs/data-model.md#tables)

## Objectif

Une connexion MySQL configurée par `config.php` et un outil de migration rejouable.

## Contenu

- `config.example.php` (clés de `infra.md`, valeurs vides) ; lecture de `config.php`, avec une erreur claire s'il manque.
- Connexion PDO : `charset=utf8mb4`, `SET time_zone = '+00:00'`, `ERRMODE_EXCEPTION`, `EMULATE_PREPARES` à `false`.
- `bin/migrate.php` : crée `schema_migrations` si besoin, puis applique dans l'ordre les `migrations/*.sql` qui n'y figurent pas, en notant chacun.
- Base `apimail_test` et un utilitaire de test qui la vide et la remigre.
- Environnement Docker local : MySQL 8.4 et une image PHP 8.4 avec `pdo_mysql` pour les tests et `bin/migrate.php` (ni `php:8.4-cli` ni le PHP du poste n'ont `pdo_mysql`) ; `infra.md` § Développement local mis à jour.

## Actions de ta part

- Définir `APIMAIL_DB_PASSWORD` dans ton shell et lancer le conteneur MySQL de `infra.md`.

## Terminé quand

- `php bin/migrate.php` lancé deux fois de suite n'applique rien la seconde fois.
- Un test d'intégration le vérifie sur `apimail_test`.
