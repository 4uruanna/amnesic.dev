# J02 Comptes et auth

Les Comptes, leurs Jetons et leurs Bindings, l'authentification réelle et le contrôle des rôles. À la sortie, un admin gère tout par l'API, sans plus toucher à la base.

## Démo de sortie

1. Sur une base vide, ouvrir `/v1/setup` sans jeton : `001_comptes.sql` est appliquée. Créer un admin avec la requête SQL de [`infra.md` § Créer un admin](../../docs/infra.md#créer-un-admin) ; `/v1/setup?token=<son jeton>` répond alors `200` et `applied` vide.
2. Avec son jeton : `POST /v1/accounts` crée `relais-mx` (`bot`) et `ci` (`user`), `POST …/tokens` leur donne un jeton, `POST …/bindings` lie `ci@<domaine>` à `ci`.
3. `GET /v1/accounts/{id}` de `ci` montre l'adresse et le jeton (sans secret) ; le jeton de `ci` reçoit `403` sur `/v1/accounts`.

## Phases

- [ ] [J02-1 Tables des comptes](1-tables-des-comptes.md)
- [ ] [J02-2 Authentification par jeton](2-authentification-par-jeton.md)
- [ ] [J02-3 Endpoints comptes](3-endpoints-comptes.md)
- [ ] [J02-4 Bindings et jetons](4-bindings-et-jetons.md)
