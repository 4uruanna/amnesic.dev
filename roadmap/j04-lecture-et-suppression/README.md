# J04 Lecture et suppression

La lecture des Messages selon les Bindings, le statut lu, le téléchargement des pièces jointes et la suppression. À la sortie, l'API remplit tout son contrat.

## Démo de sortie

1. Avec le jeton de `ci` : `GET /v1/messages?unread=1` liste le Message déposé en J03.
2. `GET /v1/messages/{id}` le renvoie en détail ; `?unread=1` ne le liste plus, `?unread=0` si.
3. `GET /v1/messages/{id}/attachment/1` renvoie la pièce jointe, avec `Content-Disposition: attachment`.
4. Un second `user`, sans Binding sur `ci@<domaine>`, reçoit `404` sur ce Message.
5. `DELETE /v1/messages/{id}` → `204` ; ensuite `404` pour tout le monde, et `storage/messages/{id}/` a disparu.

## Phases

- [x] [J04-1 Liste des Messages](1-liste-des-messages.md)
- [x] [J04-2 Détail et marquage lu](2-detail-et-marquage-lu.md)
- [ ] [J04-3 Pièces jointes](3-pieces-jointes.md)
- [ ] [J04-4 Suppression](4-suppression.md)
