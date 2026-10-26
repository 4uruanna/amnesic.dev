# J05-2 Cron OVH

**Dépend de** : J01-4, J05-1
**Docs** : [infra.md § Purge](../../docs/infra.md#purge)

## Objectif

La purge tourne chaque heure en production.

## Contenu

- `bin/purge.php` arrive en production par la branche `ovh`.
- Vérification en SSH : `/usr/local/php8.4/bin/php www/bin/purge.php` affiche `purged 0`.

## Actions de ta part

- Créer la tâche dans le Control Panel : commande `www/bin/purge.php`, PHP 8.4, toutes les heures.

## Terminé quand

- Le log cron d'OVH montre `purged <n>` sur deux heures consécutives, sans erreur.
