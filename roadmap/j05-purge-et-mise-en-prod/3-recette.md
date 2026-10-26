# J05-3 Recette

**Dépend de** : J05-2
**Docs** : [api.md](../../docs/api.md), [infra.md](../../docs/infra.md)

## Objectif

Le contrat complet vérifié en production, avec `curl`.

## Scénario

1. Admin créé en SQL (`infra.md` § Créer un admin) → son jeton répond `200` sur `GET /v1/accounts`.
2. L'admin crée `relais-mx` (`bot`), `ci` et `autre` (`user`), leurs jetons, et le Binding `ci@<domaine>` → `ci`.
3. `relais-mx` dépose un Message pour `ci@<domaine>` avec une pièce jointe → `201` ; avec un champ `cc` → `422` ; sur `GET /v1/messages` → `403`.
4. `ci` : `GET /v1/messages?unread=1` le liste → détail `200` → `?unread=1` ne le liste plus → pièce jointe identique à l'envoi.
5. `autre` : `404` sur le détail ; `200` et liste vide sur `GET /v1/messages`.
6. `ci` : `DELETE` → `204` ; puis `404` pour `ci` comme pour l'admin.
7. Un nouveau Message, son `received_at` reculé de 31 jours en SQL, disparaît à l'heure suivante.
8. `http://` → `301` ; une réponse quelconque porte HSTS, `nosniff` et `no-store`.

## Terminé quand

- Chaque étape donne le code attendu ; la démo de sortie de J05 est faite ; les comptes de recette sont supprimés.
