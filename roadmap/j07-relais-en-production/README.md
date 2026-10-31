# J07 Relais en production

Le Relais remplace le Mailpit en place sur la machine MailPit : les mails venus d'Internet arrivent dans l'API de production ([relais.md § Déploiement](../../docs/relais.md#déploiement)).

## Démo de sortie

1. Un mail envoyé depuis une boîte extérieure (Gmail…) à `ci@<domaine>`, avec une pièce jointe, est listé en moins d'une minute par `GET /v1/messages` de `ci` sur `https://api.amnesic.dev` ; Mailpit ne le garde pas.
2. Après un redémarrage de la machine, `mailpit` et `robot` reviennent seuls, et le mail suivant est déposé.

## Phases

- [ ] [J07-1 Mise en service](1-mise-en-service.md)
- [ ] [J07-2 Recette du Relais](2-recette-du-relais.md)
