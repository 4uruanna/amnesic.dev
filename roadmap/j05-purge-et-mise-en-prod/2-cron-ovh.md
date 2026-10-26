# J05-2 Cron OVH

**Dépend de** : J01-4, J05-1
**Docs** : [infra.md § Purge](../../docs/infra.md#purge)

## Objectif

La purge tourne chaque heure en production.

## Contenu

- Déploiement de `bin/purge.php` par `deploy.sh`.
- Vérification en SSH : `/usr/local/php8.4/bin/php apimail/bin/purge.php` affiche `purged 0`.

## Actions de ta part

- Créer la tâche dans le Control Panel : commande `apimail/bin/purge.php`, PHP 8.4, toutes les heures.

## Terminé quand

- Le log cron d'OVH montre `purged <n>` sur deux heures consécutives, sans erreur.
