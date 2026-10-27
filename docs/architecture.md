# Architecture

Le code est rangé par fonctionnalité, puis en couches Controller → Service → Repository ([ADR 0010](adr/0010-couches-par-fonctionnalite.md)), et assemblé par php-di ([ADR 0011](adr/0011-conteneur-php-di.md)). Le vocabulaire est celui de [`CONTEXT.md`](../CONTEXT.md).

## Arborescence

```
src/
  Account/
    AccountController.php      /v1/accounts/**
    AccountService.php         comptes, bindings, jetons
    AccountRepository.php      table accounts
    BindingRepository.php      table bindings
    TokenRepository.php        table tokens
    Authentication.php         middleware Bearer (401)
    RequireRole.php            middleware de rôle (403)
    Model/                     Account, Token
    Dto/                       AccountCreate, BindingCreate, TokenCreate,
                               AccountSummary, AccountDetail, TokenInfo, TokenCreated
  Message/
    MessageController.php      /v1/messages/**
    MessageService.php         dépôt, visibilité, lecture, suppression, purge
    MessageRepository.php      tables messages, recipients, attachments
    MessageReadRepository.php  table message_reads
    MessageFiles.php           storage/messages/{id}/
    Model/                     Message, Recipient, Attachment
    Dto/                       MessageDeposit, Address, AttachmentDeposit,
                               MessageSummary, MessageDetail, AttachmentInfo
  Setup/
    SetupController.php        GET /v1/setup (jeton admin dans l'URL, ou base vide)
  Shared/
    Database.php               connexion PDO
    Migrator.php               migrations/*.sql et schema_migrations
    Uuid.php                   UUIDv7
    JsonBody.php               lecture du corps JSON (400, 415)
    MailAddress.php            adresse validée et mise en minuscules (api.md § Conventions)
    Page.php                   DTO de pagination ; bounds() lit offset et limit (422)
    NotFound.php, Conflict.php, ValidationFailed.php
    ProblemJsonErrorHandler.php
    SecurityHeaders.php
  app.php                      middlewares et routes
  container.php                définitions php-di
bin/                           purge.php
.github/workflows/ci.yml       tests, puis publication de la branche ovh (infra.md § Déploiement)
.htaccess, .ovhconfig          configuration OVH du dossier du site
compose.yaml                   MySQL et PHP des tests locaux (infra.md § Développement local)
tests/                         même arborescence que src/
```

## Couches

| Couche | Fait | Ne fait jamais |
|---|---|---|
| **Controller** | lit le corps par `JsonBody`, construit le DTO de requête (`fromArray()`), récupère le Compte connecté (attribut de requête `account`), appelle **une** méthode de Service, convertit le résultat en DTO de réponse (`fromModel()`) et choisit le code HTTP | du SQL, une règle métier |
| **Service** | les règles métier (visibilité, conflits, Binding sur un `bot`), les transactions PDO, les fichiers par `MessageFiles`, les UUIDv7 ; lève `NotFound`, `Conflict`, `ValidationFailed` | manipuler un objet PSR-7 ou du JSON |
| **Repository** | du SQL préparé par PDO ; renvoie des Models, ou `null` | une règle métier, ouvrir une transaction (c'est le Service qui l'ouvre) |
| **Model** | `readonly class` calquée sur une table | de la logique |
| **DTO de requête** | `readonly class` ; `static fromArray(array $data): self` valide tout et lève `ValidationFailed` avec toutes les erreurs d'un coup | accéder à la base |
| **DTO de réponse** | `readonly class` `JsonSerializable` ; `static fromModel(…)` ; champs et noms d'`api.md` | contenir un champ absent du contrat |
| **Middleware** | `Authentication` (401), `RequireRole` (403), `SecurityHeaders` ; `ProblemJsonErrorHandler` traduit les exceptions | une règle métier |

## Dépendances

- Controller → Service → Repository → Model, jamais dans l'autre sens. Un Controller n'appelle pas un Repository.
- `Message` peut utiliser `Account` (le Model du Compte connecté, `RequireRole`), jamais l'inverse.
- `Setup` peut utiliser `Account` (vérifier le jeton admin par `Authentication::authenticate()`) et `Shared\Migrator` ; aucun autre dossier n'utilise `Setup`.
- `Shared` ne dépend d'aucun autre dossier de `src/`.

## Erreurs

| Exception | Réponse |
|---|---|
| `Shared\ValidationFailed` (porte la liste `errors`) | `422` |
| `Shared\NotFound` | `404` |
| `Shared\Conflict` | `409` |
| `Slim\Exception\HttpException`, levée par les middlewares et `JsonBody` (`400`, `401`, `403`, `404`, `405`, `413`, `415`) | son code ; une `401` reçoit en plus `WWW-Authenticate: Bearer` |
| toute autre | `500`, journalisée, sans détail dans la réponse |

## Conteneur

- php-di crée les classes d'après les types de leurs constructeurs ; `container.php` charge `config.php` (constantes `API_MAIL_*`, [`infra.md` § `config.php`](infra.md#configphp)) et ne définit que `PDO` (par `Shared\Database`) et le chemin de `storage/`.
- `app.php` reçoit le conteneur par `AppFactory::setContainer()`. Les routes s'écrivent `[MessageController::class, 'list']`. Un middleware qui prend des arguments est créé sur place : `->add(new RequireRole('admin'))`.
- Les scripts de `bin/` font `$container = require __DIR__ . '/../src/container.php';` puis `$container->get(MessageService::class)`.
- Pas d'interface pour un Repository ou un Service : chacun n'a qu'une implémentation.

## Conventions

- `declare(strict_types=1);` dans chaque fichier ; classes `final`.
- Espaces de noms : `Apimail\Account`, `Apimail\Account\Model`, `Apimail\Account\Dto`, `Apimail\Message`…, `Apimail\Shared`.
- Un Model porte le nom du glossaire (`Account`, `Message`) ; un DTO porte le nom d'`api.md` et ne reprend jamais celui d'un Model (`AccountSummary`, `AttachmentInfo`).

## Tests

- **Unitaires** : chaque règle des DTO de requête, `Uuid`.
- **Intégration** : par `$app->handle()` contre la base `apimail_test` (`tests/TestApp.php` y branche l'application), qui couvre ensemble Controller, Service et Repository. Pas de mocks.
- `tests/` reprend l'arborescence de `src/` (`tests/Account/…`, `tests/Message/…`, `tests/Shared/…`).
