# J04-2 Détail et marquage lu

**Dépend de** : J04-1
**Docs** : [api.md § `GET /v1/messages/{id}`](../../docs/api.md#get-v1messagesid), [CONTEXT.md § Lu](../../CONTEXT.md)

## Objectif

Le détail d'un Message, qui le marque lu pour le Compte appelant.

## Contenu

- Route `GET /v1/messages/{id}` (rôles `admin` et `user`) ; DTO `MessageDetail` et `Attachment`, `text` et `html` lus sur disque (fichier absent : `null`).
- `INSERT IGNORE INTO message_reads` à chaque appel réussi.

## Terminé quand

- Tests verts : après le détail, `?unread=1` ne liste plus le Message pour ce Compte, mais toujours pour un autre Compte qui y a accès ; deux appels ne créent qu'une ligne ; `404` pour un Message inconnu ou invisible, sans marquage.
