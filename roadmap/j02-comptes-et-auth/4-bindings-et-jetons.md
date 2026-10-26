# J02-4 Bindings et jetons

**Dépend de** : J02-3
**Docs** : [api.md § Endpoints Comptes](../../docs/api.md#endpoints-comptes), [CONTEXT.md](../../CONTEXT.md)

## Objectif

L'admin lie des adresses aux Comptes et leur émet ou révoque des Jetons.

## Contenu

- `AccountController` → `AccountService` → `BindingRepository` et `TokenRepository` ; DTO `BindingCreate`, `TokenCreate`, `TokenCreated`.
- `POST /v1/accounts/{id}/bindings`, `DELETE /v1/accounts/{id}/bindings/{address}` : adresse validée et mise en minuscules.
- `POST /v1/accounts/{id}/tokens`, `DELETE /v1/accounts/{id}/tokens/{tokenId}` : secret `bin2hex(random_bytes(32))`, renvoyé une seule fois dans `TokenCreated`.

## Terminé quand

- Tests verts : `Ci@X.tld` est stockée `ci@x.tld` ; `409` Binding déjà présent ; `422` Binding sur un `bot` ; `404` Compte ou Binding inconnu.
- Un Jeton créé par l'API s'authentifie ; après son `DELETE`, il reçoit `401`.
- La démo de sortie de J02 passe en local.
