# J05-1 Purge

**Dépend de** : J04-4
**Docs** : [infra.md § Purge](../../docs/infra.md#purge), [ADR 0009](../../docs/adr/0009-purge-apres-30-jours.md)

## Objectif

Un script qui supprime les Messages reçus il y a plus de 30 jours.

## Contenu

- `bin/purge.php` prend `MessageService` dans le conteneur ; `MessageService::purge()` sélectionne par lots de 500 les Messages dont `received_at` a plus de 30 jours et les supprime par le même chemin que J04-4, jusqu'à épuisement.
- Écrit `purged <n>` sur la sortie standard ; en cas d'erreur, écrit le message sur la sortie d'erreur et sort avec un code non nul, pour qu'OVH compte l'échec.

## Terminé quand

- Tests verts : un Message de 31 jours est supprimé (ligne et dossier), un de 29 jours est gardé ; plus de 500 Messages à purger le sont tous en un passage.
