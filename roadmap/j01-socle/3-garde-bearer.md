# J01-3 Garde Bearer

**Dépend de** : J01-5
**Docs** : [api.md § Authentification et permissions](../../docs/api.md#authentification-et-permissions)

## Objectif

Le groupe de routes `/v1` refuse toute requête sans jeton valide, avec un `detail` qui distingue « jeton absent » de « jeton invalide ».

## Contenu

- `Account\Authentication`, middleware du groupe `/v1` : lit `Authorization` ; sans en-tête, `401` « jeton absent » ; si ce n'est pas `Bearer` suivi de 64 caractères hexadécimaux minuscules, ou si le jeton est inconnu, `401` « jeton invalide ». Il lève `HttpUnauthorizedException`, que `ProblemJsonErrorHandler` complète de `WWW-Authenticate: Bearer`.
- À ce stade aucun jeton n'est connu : la recherche en base arrive en J02-2.
- Route `GET /v1/messages` déclarée derrière la garde, avec une fonction anonyme qui répond `501` jusqu'à J04-1, pour que la démo de J01 vise une route existante.

## Terminé quand

- Tests verts : pas d'en-tête → « jeton absent » ; `Basic …`, jeton trop court, jeton en majuscules, jeton bien formé → « jeton invalide » ; route inconnue → `404` sans authentification.
