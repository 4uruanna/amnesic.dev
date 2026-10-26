# J01-5 Architecture en couches

**Dépend de** : J01-1
**Docs** : [architecture.md](../../docs/architecture.md), [ADR 0010](../../docs/adr/0010-couches-par-fonctionnalite.md), [ADR 0011](../../docs/adr/0011-conteneur-php-di.md)

## Objectif

Le squelette suit l'architecture cible avant que les phases suivantes n'y ajoutent du code : conteneur php-di, code partagé dans `src/Shared/`.

## Contenu

- `php-di/php-di` 7 en dépendance.
- `src/container.php` : construit et renvoie le conteneur, sans définition à ce stade (J01-2 ajoute la configuration et PDO).
- `src/app.php` : `AppFactory::setContainer()` avec ce conteneur.
- Les deux fonctions anonymes de `app.php` deviennent `Shared\ProblemJsonErrorHandler` et `Shared\SecurityHeaders`, sans changement de comportement.

## Terminé quand

- Les tests de J01-1 passent sans modification.
- `src/app.php` ne contient plus de fonction anonyme, et `src/` suit l'arborescence d'`architecture.md`.
- `curl -i localhost:8080/v1/nope` renvoie la même réponse qu'avant.
