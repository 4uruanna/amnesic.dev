# J03 Dépôt

`POST /v1/messages` : le Relais, authentifié comme `bot`, dépose un Message en JSON déjà découpé ; l'API le valide strictement et l'enregistre (métadonnées en base, contenu sur disque).

## Démo de sortie

1. Avec le jeton de `relais-mx` : déposer un Message pour `ci@<domaine>` avec une pièce jointe → `201` et `Location`.
2. La base contient ses lignes `messages`, `recipients`, `attachments` ; `storage/messages/{id}/` contient `text`, `html` et `parts/1`.
3. Le même dépôt avec un champ `cc` → `422`, `errors[0].field = "cc"`.

## Phases

- [x] [J03-1 Tables des Messages](1-tables-des-messages.md)
- [x] [J03-2 Validation du dépôt](2-validation-du-depot.md)
- [x] [J03-3 Enregistrement du dépôt](3-enregistrement-du-depot.md)
