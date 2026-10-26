# J03-3 Enregistrement du dépôt

**Dépend de** : J03-1, J03-2
**Docs** : [api.md § `POST /v1/messages`](../../docs/api.md#post-v1messages), [data-model.md § Fichiers](../../docs/data-model.md#fichiers), [ADR 0007](../../docs/adr/0007-contenu-sur-disque.md)

## Objectif

Un dépôt valide devient un Message complet : fichiers sur disque, puis lignes en base.

## Contenu

- Route `POST /v1/messages`, rôle `bot`.
- Corps limité à 25 Mo : `413` sur `Content-Length` annoncé ou sur la taille réellement lue.
- Écriture de `text`, `html` et `parts/{partId}` sous `storage/messages/{id}/`, puis insertion de `messages`, `recipients`, `attachments` dans une transaction ; si elle échoue, suppression du dossier.
- Réponse `201`, `Location: /v1/messages/{id}`, `{ "id" }`.

## Terminé quand

- Tests verts : le bot dépose un Message avec pièce jointe et tout est en base et sur disque, octet pour octet ; `admin` et `user` → `403` ; 26 Mo → `413` ; une transaction qui échoue ne laisse ni ligne ni dossier.
- La démo de sortie de J03 passe en local.
