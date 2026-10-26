# J03-1 Tables des Messages

**Dépend de** : J02-1
**Docs** : [data-model.md § Tables](../../docs/data-model.md#tables)

## Objectif

Les tables qui reçoivent les métadonnées des Messages.

## Contenu

- `migrations/002_messages.sql` : `messages`, `recipients`, `attachments`, à l'identique de `data-model.md`.
- Models `Message\Model\Message`, `Recipient` et `Attachment`.

## Terminé quand

- La migration s'applique sur une base où `001` est déjà passée.
- Un test vérifie que supprimer une ligne `messages` emporte ses `recipients` et `attachments`.
