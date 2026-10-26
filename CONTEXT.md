# apimail

API PHP qui reçoit des mails déposés par un relais externe et les expose en lecture aux comptes autorisés par les adresses du champ `To`.

## Language

**Message** :
Un mail reçu, stocké et exposé par l'API. Identifié par un UUIDv7.
_Avoid_ : email, courriel

**Destinataire** (`recipient`) :
Une adresse du champ `To` d'un **Message**. Seul `To` compte : `Cc` et `Bcc` n'existent pas pour l'API.

**Compte** (`Account`) :
Ce qui s'authentifie auprès de l'API. Il a exactement un rôle : `admin`, `user` ou `bot`.
_Avoid_ : utilisateur (ambigu avec le rôle `user`)

**Bot** :
**Compte** de rôle `bot`. Il ne peut que déposer des **Messages** ; il ne lit et ne supprime rien.

**Binding** :
Association entre un **Compte** et une adresse mail. Il donne accès aux **Messages** dont cette adresse est **Destinataire**. Correspondance exacte, adresses stockées en minuscules ; une adresse peut être liée à plusieurs **Comptes**.

**Jeton** (`token`) :
Secret opaque envoyé en `Authorization: Bearer`. La base n'en garde que l'empreinte SHA-256.
_Avoid_ : clé d'API, JWT

**Lu** :
Un **Message** est lu par un **Compte** dès que ce **Compte** a récupéré son détail (`GET /v1/messages/{id}`). L'état est propre à chaque **Compte** et ne revient jamais à non lu.

**Relais** :
Le service externe qui reçoit les mails du MX, les découpe en JSON et les dépose dans l'API avec le **Jeton** d'un **Bot**. C'est un projet séparé, hors de ce dépôt.

## Relationships

- Un **Message** a un ou plusieurs **Destinataires**.
- Un **Compte** `user` voit et supprime un **Message** si l'un de ses **Bindings** porte sur l'un de ses **Destinataires**.
- Un **Compte** `admin` voit et supprime tous les **Messages**, et gère les **Comptes**, **Bindings** et **Jetons**.
- Un **Compte** a zéro ou plusieurs **Jetons** et zéro ou plusieurs **Bindings**.
- Le **Relais** dépose chaque **Message** par `POST /v1/messages`, en tant que **Bot**.
- Un **Message** est purgé 30 jours après sa réception.

## Flagged ambiguities

- « bot » : résolu, c'est un **Compte** qui dépose les **Messages** (il remplace le rôle `relay` envisagé le 2026-10-19).
- « relay » désignait à la fois le service externe et son rôle dans l'API : le service est le **Relais**, le rôle est `bot`.
