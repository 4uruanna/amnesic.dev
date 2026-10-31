# J06-2 Conversion

**Dépend de** : J06-1
**Docs** : [relais.md § Conversion](../../docs/relais.md#conversion), [api.md § `MessageDeposit`](../../docs/api.md#messagedeposit-entrée-de-post-v1messages), [ADR 0002](../../docs/adr/0002-seul-to-donne-acces.md)

## Objectif

Un message Mailpit devient un `MessageDeposit` conforme au contrat de l'API.

## Contenu

- `Conversion` : le JSON de `GET /api/v1/message/{ID}` et le contenu de ses parts → le tableau `MessageDeposit`, selon relais.md § Conversion.
- Fixtures JSON capturées sur un vrai Mailpit : texte seul, HTML seul, pièce jointe, image inline sans nom, `Cc` et `Bcc`, expéditeur sans nom, mail sans `To`.

## Terminé quand

- Tests verts : chaque règle de relais.md § Conversion ; le dépôt tiré de chaque fixture est accepté (`201`) par l'API locale, sauf celui du mail sans `To` (`422`).
