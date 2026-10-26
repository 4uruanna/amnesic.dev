# J02-2 Authentification par jeton

**Dépend de** : J01-3, J02-1
**Docs** : [api.md § Authentification et permissions](../../docs/api.md#authentification-et-permissions), [ADR 0004](../../docs/adr/0004-jetons-opaques.md), [infra.md § Créer un admin](../../docs/infra.md#créer-un-admin)

## Objectif

Un jeton connu authentifie son Compte, et chaque route n'admet que les rôles du tableau des permissions.

## Contenu

- La garde Bearer cherche `hash('sha256', $jeton)` dans `tokens` et attache le Compte (`id`, `role`) à la requête.
- Garde de rôle déclarée par route, qui répond `403` avant tout chargement de ressource.

## Terminé quand

- Tests verts : jeton inconnu → `401` ; mauvais rôle → `403` ; bon rôle → la route est atteinte.
- Un test exécute telle quelle la requête SQL de `infra.md` § Créer un admin, et le jeton qu'elle affiche est accepté.
