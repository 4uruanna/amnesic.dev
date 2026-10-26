# J01-2 Base et migrations

**Dépend de** : J01-5
**Docs** : [infra.md § `config.php`](../../docs/infra.md#configphp), [§ Développement local](../../docs/infra.md#développement-local), [§ Migrations](../../docs/infra.md#migrations), [data-model.md § Tables](../../docs/data-model.md#tables)

## Objectif

Une connexion MySQL configurée par `config.php` et un outil de migration rejouable.

## Contenu

- `container.php` charge `config.php` par `require_once` (constantes `API_MAIL_*` de `config.template.php`, déjà dans le dépôt), avec une erreur claire s'il manque.
- `Shared\Database`, qui fournit le `PDO` du conteneur : `charset=utf8mb4`, `SET time_zone = '+00:00'`, `ERRMODE_EXCEPTION`, `EMULATE_PREPARES` à `false`.
- `bin/migrate.php`, qui prend le PDO dans le conteneur : crée `schema_migrations` si besoin, puis applique dans l'ordre les `migrations/*.sql` qui n'y figurent pas, en notant chacun.
- Base `apimail_test` et un utilitaire de test qui la vide et la remigre.
- Environnement Docker local : MySQL 8.4 et une image PHP 8.4 avec `pdo_mysql` pour les tests et `bin/migrate.php` (ni `php:8.4-cli` ni le PHP du poste n'ont `pdo_mysql`) ; `infra.md` § Développement local mis à jour.

## Actions de ta part

- Copier `config.template.php` en `config.php` et le remplir pour le MySQL local (`API_MAIL_DB_PASSWORD` : la valeur de ton choix).

## Terminé quand

- `php bin/migrate.php` lancé deux fois de suite n'applique rien la seconde fois.
- Un test d'intégration le vérifie sur `apimail_test`.
