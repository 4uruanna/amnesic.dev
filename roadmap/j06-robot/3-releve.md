# J06-3 Relève

**Dépend de** : J06-2
**Docs** : [relais.md § Cycle](../../docs/relais.md#cycle), [§ Erreurs](../../docs/relais.md#erreurs), [§ Journal](../../docs/relais.md#journal), [ADR 0015](../../docs/adr/0015-releve-toutes-les-5-secondes.md)

## Objectif

Le robot dépose dans l'API tout ce que Mailpit reçoit, sans perte, et sans doublon en marche normale.

## Contenu

- `Mailpit` (recherche, message, part, suppression, étiquettes), en authentification basique, et `Apimail` (`POST /v1/messages`), en `Bearer` : tous deux par `ext-curl`.
- `Relais::cycle()` : relève, conversion, dépôt, puis suppression ou étiquette `rejet` selon relais.md § Erreurs ; `bin/relais.php` le lance toutes les `POLL_SECONDS`.
- Le journal de relais.md § Journal.
- Tests d'intégration contre un vrai Mailpit et la vraie API (`php -S` sur `public/`, base `apimail_test`), mails injectés par `POST /api/v1/send`.

## Terminé quand

- Tests verts :
  - `201` : le mail quitte Mailpit et l'API le montre ;
  - `422` : le mail reste dans Mailpit, étiqueté `rejet`, et n'est plus jamais redéposé ;
  - jeton invalide (`401`) ou API arrêtée : le mail reste intact, puis il est déposé au premier cycle où l'API répond ;
  - trois mails arrivent dans l'API dans l'ordre de leur réception par Mailpit ;
  - le journal ne contient aucun jeton.
- La démo de sortie de J06 passe.
