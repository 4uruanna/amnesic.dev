# J02-1 Tables des comptes

**Dépend de** : J01-2
**Docs** : [data-model.md § Tables](../../docs/data-model.md#tables)

## Objectif

Les tables des Comptes et le générateur d'identifiants.

## Contenu

- `migrations/001_comptes.sql` : `accounts`, `tokens`, `bindings`, à l'identique de `data-model.md`.
- Générateur UUIDv7 dans `src/` (horodatage en millisecondes et `random_bytes`), sans bibliothèque.

## Terminé quand

- La migration s'applique sur une base vide.
- Tests verts : un UUIDv7 généré a le bon format, la version 7, la variante RFC 9562, et deux appels successifs donnent des valeurs croissantes.
