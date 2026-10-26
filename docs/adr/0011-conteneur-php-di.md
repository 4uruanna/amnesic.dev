# Conteneur php-di

Les objets sont créés par `php-di/php-di` 7, qui les assemble d'après les types de leurs constructeurs ; `src/container.php` ne définit que la configuration et la connexion PDO. Le même conteneur sert Slim (`AppFactory::setContainer`, routes `[Controller::class, 'méthode']`) et les scripts `bin/`, et ne construit que ce que la requête utilise. Cette décision remplace l'assemblage à la main prévu au départ, devenu trop long avec une quinzaine de classes et des scripts `bin/` qui les partagent.

Contreparties : une dépendance de plus, et une erreur d'assemblage n'apparaît qu'à l'exécution. Pas de `php-di/slim-bridge` ni de compilation du conteneur pour l'instant.
