# J08-2 Export EML

**Dépend de** : J08-1, J04-3
**Docs** : [api.md § `GET /v1/messages/{id}/eml`](../../docs/api.md#get-v1messagesideml), [§ En-têtes de sécurité](../../docs/api.md#en-têtes-de-sécurité), [ADR 0016](../../docs/adr/0016-original-pour-export-eml.md)

## Objectif

Le téléchargement de l'Original en `.eml`, qu'Outlook ouvre comme le mail reçu.

## Contenu

- `MessageController::eml` → `MessageService::original` : route `GET /v1/messages/{id}/eml` (rôles `admin` et `user`), contenu lu en flux par `MessageFiles`.
- En-têtes `Content-Type: message/rfc822`, `Content-Length`, `Content-Disposition: attachment; filename="<id>.eml"`, `Content-Security-Policy: default-src 'none'; sandbox`.
- Ne marque pas le Message lu.

## Terminé quand

- Tests verts : contenu identique, octet pour octet, à l'Original déposé ; en-têtes présents ; `404` pour un Message sans Original, invisible ou inconnu ; `403` pour un `bot` ; le Message reste non lu.
