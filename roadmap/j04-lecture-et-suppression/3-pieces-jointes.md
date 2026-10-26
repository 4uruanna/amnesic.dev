# J04-3 Pièces jointes

**Dépend de** : J04-2
**Docs** : [api.md § `GET /v1/messages/{id}/attachment/{partId}`](../../docs/api.md#get-v1messagesidattachmentpartid), [§ En-têtes de sécurité](../../docs/api.md#en-têtes-de-sécurité)

## Objectif

Le téléchargement d'une pièce jointe, sans qu'un navigateur puisse l'exécuter.

## Contenu

- Route `GET /v1/messages/{id}/attachment/{partId}` (rôles `admin` et `user`), contenu lu en flux depuis `parts/{partId}`.
- En-têtes `Content-Type` (celui du dépôt), `Content-Length`, `Content-Disposition: attachment; filename="…"; filename*=UTF-8''…`, `Content-Security-Policy: default-src 'none'; sandbox`.
- Ne marque pas le Message lu.

## Terminé quand

- Tests verts : contenu identique, octet pour octet, à la pièce déposée ; en-têtes présents, nom de fichier accentué correctement encodé ; `404` pour un `partId` inconnu ou un Message invisible ; le Message reste non lu.
