# J06-1 Squelette du robot

**Dépend de** : J01-6
**Docs** : [relais.md § Arborescence](../../docs/relais.md#arborescence), [§ Conteneurs](../../docs/relais.md#conteneurs), [§ Configuration](../../docs/relais.md#configuration), [§ Développement local et tests](../../docs/relais.md#développement-local-et-tests), [ADR 0014](../../docs/adr/0014-relais-mailpit-et-robot.md)

## Objectif

`robot/` se construit, se teste et démarre à côté de Mailpit, sans jamais atteindre la branche `ovh`.

## Contenu

- `robot/composer.json` (autoload `Robot\` → `src/`, PHPUnit en dev, plateforme épinglée à PHP 8.4), `Dockerfile` (`php:8.4-cli`, `composer install --no-dev`), `compose.yaml` (relais.md § Conteneurs), `.env.template`.
- `.gitignore` : `robot/.env`, `robot/auth.txt`, `robot/data/`, `robot/vendor/`.
- `bin/relais.php` : lit et vérifie la configuration, journalise `started`, puis attend `POLL_SECONDS` en boucle ; le cycle arrive en J06-3.
- CI : un job `robot` qui lance ses tests ; le job de publication échoue si l'arbre publié sur `ovh` contient `robot/`.
- `infra.md` § Déploiement et `architecture.md` § Arborescence mentionnent `robot/`.

## Terminé quand

- `docker compose up -d --build` dans `robot/` démarre `mailpit`, dont l'interface sur `127.0.0.1:8025` exige l'authentification, et `robot`, qui journalise `started`.
- Sans `APIMAIL_TOKEN`, `robot` s'arrête avec un message qui nomme la variable, sans afficher aucune valeur.
- La CI lance les tests du robot ; la branche `ovh` publiée ne contient pas `robot/`.
