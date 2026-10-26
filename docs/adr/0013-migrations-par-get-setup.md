# Migrations déclenchées par `GET /v1/setup`

Aucun script ne tourne au déploiement ([ADR 0012](0012-deploiement-git-ovh.md)) : les migrations s'appliquent en ouvrant `GET /v1/setup` dans un navigateur. La route exige le jeton d'un admin dans l'URL (`?token=`), sauf tant que la base est vide (pas de table `schema_migrations`), pour l'installation. Un verrou MySQL empêche deux exécutions simultanées.

Contreparties : le jeton admin passe par l'URL, donc par l'historique du navigateur et les logs d'accès d'OVH (d'où un jeton admin dédié, révocable) ; entre le premier déploiement et le premier appel, n'importe qui peut créer le schéma vide à ta place, sans rien obtenir de plus. La route n'est livrée qu'avec la première migration (J02), pour qu'un appel prématuré ne crée pas une `schema_migrations` vide qui exigerait ensuite un admin introuvable.

Options écartées : `POST` avec jeton en en-tête, jeton de setup propre dans `config.php`, mot de passe HTTP Basic, premier schéma appliqué à la main dans phpMyAdmin.
