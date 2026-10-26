# J04-4 Suppression

**Dépend de** : J04-2
**Docs** : [api.md § `DELETE /v1/messages/{id}`](../../docs/api.md#delete-v1messagesid), [data-model.md § Fichiers](../../docs/data-model.md#fichiers), [ADR 0008](../../docs/adr/0008-suppression-definitive.md)

## Objectif

La suppression définitive d'un Message, par un seul code que la purge réutilisera.

## Contenu

- Route `DELETE /v1/messages/{id}` (rôles `admin` et `user` sur les siens).
- Une fonction de suppression : la ligne `messages` (les cascades font le reste), puis le dossier `storage/messages/{id}/`.

## Terminé quand

- Tests verts : un `user` supprime un Message partagé et l'autre Compte reçoit ensuite `404` ; le dossier a disparu ; `404` pour un Message invisible ; `bot` → `403`.
- La démo de sortie de J04 passe en local.
