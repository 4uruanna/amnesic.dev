# J02-2 Authentification par jeton

**Dépend de** : J01-3, J02-1
**Docs** : [api.md § Authentification et permissions](../../docs/api.md#authentification-et-permissions), [ADR 0004](../../docs/adr/0004-jetons-opaques.md), [infra.md § Créer un admin](../../docs/infra.md#créer-un-admin), [api.md § `GET /v1/setup`](../../docs/api.md#get-v1setup), [ADR 0013](../../docs/adr/0013-migrations-par-get-setup.md)

## Objectif

Un jeton connu authentifie son Compte, chaque route n'admet que les rôles du tableau des permissions, et `/v1/setup` applique les migrations.

## Contenu

- `Account\Authentication` cherche `hash('sha256', $jeton)` par `TokenRepository` et attache le Model `Account` à la requête (attribut `account`).
- `Account\RequireRole`, ajouté par route dans `app.php` (`new RequireRole('admin')`), lève `HttpForbiddenException` (`403`) avant tout chargement de ressource.
- `Setup\SetupController` : `GET /v1/setup`, hors de la garde par en-tête. Sans table `schema_migrations`, il appelle `Shared\Migrator` sans jeton ; sinon il exige `?token=` d'un `admin` (`401`, `403`). Réponse `{ "applied": [...] }`.

## Terminé quand

- Tests verts : jeton inconnu → `401` ; mauvais rôle → `403` ; bon rôle → la route est atteinte.
- Un test exécute telle quelle la requête SQL de `infra.md` § Créer un admin, et le jeton qu'elle affiche est accepté.
- Tests verts pour `/v1/setup` : base vide sans jeton → `200` et migrations appliquées ; ensuite sans jeton → `401`, jeton `user` → `403`, jeton `admin` en `?token=` → `200` et `applied` vide ; le même jeton en en-tête `Authorization`, sans `?token=` → `401`.
