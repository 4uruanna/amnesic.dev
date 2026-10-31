# J07-1 Mise en service

**Dépend de** : J06-3, J05-3
**Docs** : [relais.md § Déploiement](../../docs/relais.md#déploiement), [§ Configuration](../../docs/relais.md#configuration), [§ Conteneurs](../../docs/relais.md#conteneurs)

## Objectif

Le Relais tourne sur la machine MailPit, à la place du Mailpit en place, sans perdre les mails que celui-ci garde.

## Contenu

- Le dépôt cloné sur la machine par une deploy key en lecture seule.
- Le Bot `relais-mx` et son jeton, créés par l'API de production.
- `robot/.env`, `auth.txt` et `data/` repris du Mailpit en place ; l'ancien conteneur arrêté, puis `docker compose up -d --build` dans `robot/`.
- relais.md § Déploiement corrigé de tout écart rencontré.

## Actions de ta part

- La clé SSH de la machine en deploy key du dépôt `amnesic.dev`, sans droit d'écriture.
- `APIMAIL_TOKEN` et `MAILPIT_PASS` dans `robot/.env`, et `MAILPIT_ALLOWED_RECIPIENTS` avec le domaine réel.
- Avant le premier démarrage, supprimer dans l'interface de Mailpit les mails à ne pas déposer : le robot dépose tous ceux qu'il trouve.

## Terminé quand

- `docker compose ps` dans `robot/` montre `mailpit` et `robot` en marche ; le journal du robot montre `started`, puis aucun `retry`.
- Un mail remis en SMTP depuis la machine elle-même pour `ci@<domaine>` arrive dans l'API de production.
- L'ancien dossier de Mailpit ne sert plus : ses données sont dans `robot/data/`.
