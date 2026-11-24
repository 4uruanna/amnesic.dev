# J08 Export EML

Un Message s'ouvre dans Outlook tel qu'un vrai destinataire l'aurait reçu : le Robot joint au dépôt l'Original, que l'API garde et rend en `.eml` ([ADR 0016](../../docs/adr/0016-original-pour-export-eml.md)). Ce milestone est codé avant la clôture de J07, à ta demande : seule sa recette, J08-4, attend le Relais en production.

## Démo de sortie

1. Un mail envoyé depuis une boîte extérieure (Gmail…) à `ci@<domaine>`, avec une image dans le corps, une adresse en `Cc` et une autre en `Cci`, arrive dans l'API de production.
2. Son export, `GET /v1/messages/{id}/eml` de `ci`, s'ouvre dans Outlook : l'image s'affiche dans le corps, le `Cc` est visible, l'adresse en `Cci` n'apparaît nulle part, pas même dans les en-têtes.
3. Un mail de 16 Mo environ (une pièce jointe de 12 Mo) arrive lui aussi, sans Original : le journal du robot montre `deposited … without raw`, et son export répond `404`.

## Phases

- [x] [J08-1 Dépôt de l'Original](1-depot-de-l-original.md)
- [x] [J08-2 Export EML](2-export-eml.md)
- [x] [J08-3 Original relevé par le Robot](3-original-releve-par-le-robot.md)
- [ ] [J08-4 Recette de l'export](4-recette-de-l-export.md)
