# J05 Purge et mise en prod

La purge horaire des Messages de plus de 30 jours, sa tâche cron sur OVH, et la recette complète en production.

## Démo de sortie

1. En production, reculer de 31 jours le `received_at` d'un Message de test (SQL) : il disparaît, fichiers compris, à l'heure suivante ; le log cron d'OVH affiche `purged 1`.
2. Le scénario de [J05-3](3-recette.md) passe de bout en bout sur `https://www.api.amnesic.dev`.

## Phases

- [x] [J05-1 Purge](1-purge.md)
- [ ] [J05-2 Cron OVH](2-cron-ovh.md)
- [ ] [J05-3 Recette](3-recette.md)
