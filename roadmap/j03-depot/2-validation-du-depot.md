# J03-2 Validation du dépôt

**Dépend de** : J02-3
**Docs** : [api.md § DTO](../../docs/api.md#dto) (`MessageDeposit`, `Address`, `AttachmentDeposit`), [ADR 0006](../../docs/adr/0006-depot-en-json-decoupe.md)

## Objectif

Un dépôt est accepté s'il respecte exactement le contrat, et chaque écart est signalé sur le bon champ.

## Contenu

- DTO `MessageDeposit`, `Address`, `AttachmentDeposit` en `readonly class`, construits depuis le tableau JSON avec toutes les règles d'`api.md` : champs obligatoires, longueurs, adresses validées et mises en minuscules, `to` dédoublonné, dates RFC 3339 converties en UTC, base64 strict (`base64_decode($s, true)`), caractères de contrôle refusés dans `filename` et `contentType`.
- Tout champ inconnu, `cc` et `bcc` compris, est une erreur `Champ inconnu.`

## Terminé quand

- Tests unitaires verts : un dépôt complet valide ; un dépôt minimal (`from` et un `to`) valide ; puis, pour chaque règle, un dépôt invalide dont l'erreur porte le bon `field` (`to`, `to[1].address`, `attachments[0].content`, `cc`…).
