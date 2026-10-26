# J02-3 Endpoints comptes

**Dépend de** : J02-2
**Docs** : [api.md § Endpoints Comptes](../../docs/api.md#endpoints-comptes), [§ DTO](../../docs/api.md#dto)

## Objectif

L'admin crée, liste, consulte et supprime des Comptes.

## Contenu

- `AccountController` (`create`, `list`, `show`, `delete`) → `AccountService` → `AccountRepository` : `POST /v1/accounts`, `GET /v1/accounts`, `GET /v1/accounts/{id}`, `DELETE /v1/accounts/{id}`.
- DTO `AccountCreate`, `AccountSummary`, `AccountDetail`, `TokenInfo`, et `Shared\Page` pour `{ items, total, offset, limit }`.
- `Shared\JsonBody`, lecture commune des corps JSON réutilisée par le dépôt : `415` hors `application/json`, `400` JSON mal formé.
- Exceptions `Shared\ValidationFailed` (`422`, avec `errors[{field, message}]`), `Shared\NotFound` (`404`) et `Shared\Conflict` (`409`), traduites par `ProblemJsonErrorHandler`.

## Terminé quand

- Tests verts pour chaque endpoint : `201` et `Location` ; `409` nom déjà pris ; `422` rôle inconnu ou champ en trop ; pagination et tri par `name` ; `404` Compte inconnu ; la suppression emporte Jetons et Bindings ; `user` et `bot` → `403`.
