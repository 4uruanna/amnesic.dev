# PHP 8.4 avec Slim 4

L'API est en PHP 8.4 sur le micro-framework Slim 4 : routage, middlewares (authentification, erreurs `problem+json`) et PSR-7, sans la masse d'un framework complet. Sur un mutualisé à 1 vCore, où `vendor/` doit être construit hors du serveur (le SSH OVH n'a pas d'accès sortant), un `vendor/` court se déploie et se maintient mieux.

Options écartées : PHP sans framework (routeur et middlewares à écrire soi-même), Symfony, Laravel.
