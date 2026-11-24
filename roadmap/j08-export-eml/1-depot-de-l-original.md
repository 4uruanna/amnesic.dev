# J08-1 Dépôt de l'Original

**Dépend de** : J03-3
**Docs** : [api.md § `MessageDeposit`](../../docs/api.md#messagedeposit-entrée-de-post-v1messages), [§ Conventions](../../docs/api.md#conventions), [data-model.md § Fichiers](../../docs/data-model.md#fichiers), [ADR 0016](../../docs/adr/0016-original-pour-export-eml.md)

## Objectif

L'API garde l'Original que le Relais joint au dépôt, sans jamais le lire.

## Contenu

- `MessageDeposit` : le champ `raw`, en base64 standard strict comme `content` d'`AttachmentDeposit` ; `Shared\Base64` décode les deux.
- `MessageFiles` écrit l'Original dans `storage/messages/{id}/raw` avec les autres fichiers, avant la transaction ; la suppression et la purge l'emportent avec le dossier.
- `JsonBody` : corps limité à 32 Mio.

## Terminé quand

- Tests verts : l'Original déposé est sur disque, octet pour octet ; `raw` absent ou `null` n'écrit aucun fichier `raw` ; un `raw` hors base64 standard reçoit `422` sur `raw` ; la suppression efface l'Original ; 33 Mio → `413`.
