# J04-1 Liste des Messages

**Dépend de** : J03-3
**Docs** : [api.md § `GET /v1/messages`](../../docs/api.md#get-v1messages), [data-model.md § Requêtes clés](../../docs/data-model.md#requêtes-clés), [ADR 0002](../../docs/adr/0002-seul-to-donne-acces.md)

## Objectif

Chaque Compte liste exactement les Messages qu'il a le droit de voir.

## Contenu

- `migrations/003_lectures.sql` : `message_reads`, à l'identique de `data-model.md`.
- Route `GET /v1/messages` (rôles `admin` et `user`) : requête de visibilité, filtres `to`, `since`, `unread`, pagination, tri `receivedAt` puis `id` décroissants ; DTO `MessageSummary`.
- Elle remplace la réponse `501` de J01-3.

## Terminé quand

- Tests verts : l'admin voit tout ; un `user` ne voit que les Messages de ses Bindings, y compris ceux reçus avant le Binding ; un Message sans Destinataire lié n'est vu que de l'admin ; chaque filtre ; `total` juste ; `422` sur `unread=2`, `since=hier`, `limit=101`.
