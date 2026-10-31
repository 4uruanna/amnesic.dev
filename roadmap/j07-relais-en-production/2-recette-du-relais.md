# J07-2 Recette du Relais

**Dépend de** : J07-1
**Docs** : [relais.md](../../docs/relais.md), [api.md § `POST /v1/messages`](../../docs/api.md#post-v1messages)

## Objectif

Le Relais vérifié en production, de la boîte d'un expéditeur extérieur jusqu'à l'API.

## Scénario

1. Depuis une boîte extérieure, un mail à `ci@<domaine>` avec une pièce jointe : listé pour `ci` en moins d'une minute, pièce jointe identique ; absent de Mailpit.
2. Un mail à `ci@<domaine>` en `Bcc` seul : il reste dans Mailpit, étiqueté `rejet` ; le journal montre `rejected … 422`.
3. Un mail à une adresse hors de `MAILPIT_ALLOWED_RECIPIENTS` : Mailpit le refuse en SMTP, l'API ne le voit jamais.
4. Le jeton de `relais-mx` révoqué : les mails attendent dans Mailpit et le journal montre `retry` ; un nouveau jeton dans `.env`, puis `docker compose up -d` : ils sont déposés.
5. La machine redémarrée : `mailpit` et `robot` reviennent seuls ; un mail envoyé ensuite est déposé.
6. `docker compose logs robot` ne contient ni jeton ni contenu de mail.

## Terminé quand

- Chaque étape donne le résultat attendu ; la démo de sortie de J07 est faite ; les mails de recette sont supprimés de l'API et de Mailpit.
