# J01-1 Squelette Slim

**Dépend de** : —
**Docs** : [api.md § Conventions](../../docs/api.md#conventions), [§ En-têtes de sécurité](../../docs/api.md#en-têtes-de-sécurité), [ADR 0003](../../docs/adr/0003-php-slim.md)

## Objectif

Une application Slim qui démarre en local et répond en `problem+json`.

## Contenu

- `composer.json` : PHP `^8.4`, `slim/slim` 4, `slim/psr7` ; `phpunit/phpunit` en dev ; autoload PSR-4 `Apimail\` → `src/`.
- `public/index.php` : crée l'application, ajoute le routage et les middlewares, sans conteneur d'injection.
- Middleware d'erreurs : toute erreur sort en `application/problem+json` (`type: about:blank`) ; `404` pour une route inconnue, `405`, `500` sans détail technique (le détail va dans le log d'erreurs PHP).
- Middleware d'en-têtes : `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `Cache-Control: no-store` sur toutes les réponses.
- `phpunit.xml` et un premier test.

## Terminé quand

- `php -S localhost:8080 -t public` puis `curl -i localhost:8080/v1/nope` renvoie `404` en `problem+json`, avec les trois en-têtes.
- `vendor/bin/phpunit` est vert, avec un test qui couvre ce cas et un `405`.
