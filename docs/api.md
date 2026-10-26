# Contrat de l'API

Ce document est le contrat de l'API pour ses clients : le Relais (dépôt) et les Comptes qui lisent leurs Messages. Le vocabulaire est celui de [`CONTEXT.md`](../CONTEXT.md), les choix structurants sont dans [`docs/adr/`](adr/).

- [Conventions](#conventions)
- [Authentification et permissions](#authentification-et-permissions)
- [DTO](#dto)
- [Endpoints Messages](#endpoints-messages)
- [Endpoints Comptes](#endpoints-comptes)
- [En-têtes de sécurité](#en-têtes-de-sécurité)

## Conventions

- **URL de base** : `https://api.<domaine>/v1`. HTTPS seulement : une requête en HTTP reçoit une redirection `301` (qu'un client ne doit pas suivre pour un `POST`, il appelle directement en HTTPS).
- **Format** : JSON UTF-8. Un corps de requête porte `Content-Type: application/json`, sinon `415`. Un JSON mal formé reçoit `400`.
- **Champs inconnus** : refusés (`422`) dans tout corps de requête, à tous les niveaux. Les paramètres de requête inconnus sont ignorés.
- **Dates** : ISO 8601 / RFC 3339. En entrée, tout décalage horaire est accepté ; en sortie, toujours UTC à la milliseconde : `2026-10-26T09:59:58.000Z`.
- **Identifiants** : UUIDv7 en minuscules, `0192c8e4-7b1a-7c3e-9f00-5a4b3c2d1e0f`.
- **Adresses mail** : validées, puis mises en minuscules. Toute comparaison se fait sur l'adresse exacte en minuscules.
- **Pagination** : `?offset=` (défaut `0`) et `?limit=` (défaut `20`, de `1` à `100`). Une valeur hors bornes reçoit `422`. Réponse :

  ```json
  { "items": [ … ], "total": 42, "offset": 0, "limit": 20 }
  ```

  `total` compte tous les éléments qui satisfont les filtres, toutes pages confondues.
- **Erreurs** : `application/problem+json` ([RFC 9457](https://www.rfc-editor.org/rfc/rfc9457)), `type` toujours `about:blank`. Une `422` liste chaque erreur dans `errors` :

  ```json
  {
    "type": "about:blank",
    "title": "Unprocessable Content",
    "status": 422,
    "detail": "Le corps de la requête est invalide.",
    "errors": [
      { "field": "to[0].address", "message": "Adresse mail invalide." },
      { "field": "cc", "message": "Champ inconnu." }
    ]
  }
  ```

- **Codes** :

  | Code | Quand |
  |---|---|
  | `200` | lecture réussie |
  | `201` | création réussie |
  | `204` | suppression réussie, sans corps |
  | `400` | JSON mal formé |
  | `401` | jeton absent ou invalide |
  | `403` | le rôle du Compte n'a pas droit à cet endpoint |
  | `404` | route inconnue, ou ressource qui n'existe pas **ou** que le Compte ne voit pas (l'API ne distingue jamais les deux) |
  | `405` | méthode non permise sur cette route |
  | `409` | nom de Compte ou Binding déjà existant |
  | `413` | corps de plus de 25 Mo |
  | `415` | corps qui n'est pas `application/json` |
  | `422` | corps ou paramètre invalide |
  | `500` | erreur interne, sans détail technique dans la réponse |

## Authentification et permissions

Chaque route `/v1` exige `Authorization: Bearer <jeton>`, où le jeton fait 64 caractères hexadécimaux minuscules ([ADR 0004](adr/0004-jetons-opaques.md)). Sans en-tête, ou avec un jeton inconnu, l'API répond `401` avec `WWW-Authenticate: Bearer` ; le `detail` distingue « jeton absent » de « jeton invalide ». Une route inconnue répond `404` avant toute authentification.

L'API vérifie le rôle (`403`) avant de charger la ressource (`404`).

| Endpoint | `admin` | `user` | `bot` |
|---|---|---|---|
| `POST /v1/messages` | ✗ | ✗ | ✓ |
| `GET /v1/messages` | tous | les siens | ✗ |
| `GET /v1/messages/{id}` | tous | les siens | ✗ |
| `GET /v1/messages/{id}/attachment/{partId}` | tous | les siens | ✗ |
| `DELETE /v1/messages/{id}` | tous | les siens | ✗ |
| `/v1/accounts` et toutes ses sous-routes | ✓ | ✗ | ✗ |

« Les siens » : les Messages dont au moins un Destinataire (`To`) correspond à l'un des Bindings du Compte, y compris les Messages reçus avant la création du Binding ([ADR 0002](adr/0002-seul-to-donne-acces.md)). Un Message sans Destinataire lié n'est visible que des `admin`.

## DTO

### `Address`

| Champ | Type | Règle |
|---|---|---|
| `address` | string | obligatoire, adresse mail valide, 254 caractères au plus, rendue en minuscules |
| `name` | string \| null | facultatif, 255 caractères au plus |

### `MessageDeposit` (entrée de `POST /v1/messages`)

| Champ | Type | Règle |
|---|---|---|
| `from` | `Address` | obligatoire |
| `to` | `Address[]` | obligatoire, de 1 à 100 éléments ; une adresse répétée n'est gardée qu'une fois (le premier `name` l'emporte) |
| `subject` | string \| null | facultatif, 998 caractères au plus ; absent ou `null` devient `""` |
| `sentAt` | date \| null | facultatif : l'en-tête `Date` du mail |
| `text` | string \| null | facultatif : la partie `text/plain` |
| `html` | string \| null | facultatif : la partie `text/html` |
| `attachments` | `AttachmentDeposit[]` | facultatif, 100 éléments au plus ; absent vaut `[]` |

`cc`, `bcc` et tout autre champ sont refusés comme champs inconnus ([ADR 0006](adr/0006-depot-en-json-decoupe.md)). `receivedAt` est posé par l'API.

### `AttachmentDeposit`

| Champ | Type | Règle |
|---|---|---|
| `filename` | string | obligatoire, de 1 à 255 caractères, sans caractère de contrôle |
| `contentType` | string | obligatoire, de la forme `type/sous-type`, paramètres permis (`text/plain; charset=utf-8`), 255 caractères au plus, sans caractère de contrôle |
| `content` | string | obligatoire, base64 standard strict (un caractère hors alphabet donne `422`) |

L'API numérote les pièces jointes : `partId` vaut 1, 2, 3… dans l'ordre du tableau.

### `MessageSummary` (élément de `GET /v1/messages`)

| Champ | Type |
|---|---|
| `id` | UUIDv7 |
| `from` | `Address` |
| `to` | `Address[]` |
| `subject` | string |
| `sentAt` | date \| null |
| `receivedAt` | date |
| `hasAttachments` | bool |
| `unread` | bool : `true` tant que le Compte appelant n'a pas lu le détail |

### `MessageDetail` (réponse de `GET /v1/messages/{id}`)

Les champs de `MessageSummary` sauf `unread` (l'appel qui renvoie le détail marque le Message comme lu), plus :

| Champ | Type |
|---|---|
| `text` | string \| null |
| `html` | string \| null |
| `attachments` | `AttachmentInfo[]` |

### `AttachmentInfo`

| Champ | Type |
|---|---|
| `partId` | int |
| `filename` | string |
| `contentType` | string |
| `size` | int : taille décodée, en octets |

### `AccountSummary`

| Champ | Type |
|---|---|
| `id` | UUIDv7 |
| `name` | string |
| `role` | `"admin"` \| `"user"` \| `"bot"` |
| `createdAt` | date |

### `AccountDetail` (réponse de `GET /v1/accounts/{id}`)

Les champs d'`AccountSummary`, plus `addresses` (string[], les adresses des Bindings, triées) et `tokens` (`TokenInfo[]`, triés par `createdAt`).

### `TokenInfo`

| Champ | Type |
|---|---|
| `id` | UUIDv7 |
| `name` | string |
| `createdAt` | date |

### `AccountCreate`, `BindingCreate`, `TokenCreate` (entrées)

| DTO | Champ | Règle |
|---|---|---|
| `AccountCreate` | `name` | obligatoire, de 1 à 100 caractères, unique (`409`) |
| | `role` | obligatoire, `admin`, `user` ou `bot` |
| `BindingCreate` | `address` | obligatoire, adresse mail valide, 254 caractères au plus |
| `TokenCreate` | `name` | obligatoire, de 1 à 100 caractères |

### `TokenCreated` (réponse de `POST /v1/accounts/{id}/tokens`)

Les champs de `TokenInfo`, plus `token` : le secret, 64 caractères hexadécimaux. C'est la seule réponse qui le contient ; l'API ne peut plus le restituer ensuite.

## Endpoints Messages

### `POST /v1/messages`

Dépose un Message. Rôle `bot`.

```http
POST /v1/messages
Authorization: Bearer 3f9c…e1a0
Content-Type: application/json

{
  "from": { "address": "noreply@service.tld", "name": "Service" },
  "to": [ { "address": "ci@mondomaine.tld", "name": null } ],
  "subject": "Votre code",
  "sentAt": "2026-10-26T11:59:58+02:00",
  "text": "Code : 123456",
  "html": "<p>Code : <b>123456</b></p>",
  "attachments": [
    { "filename": "facture.pdf", "contentType": "application/pdf", "content": "JVBERi0x…" }
  ]
}
```

```http
HTTP/1.1 201 Created
Location: /v1/messages/0192c8e4-7b1a-7c3e-9f00-5a4b3c2d1e0f
Content-Type: application/json

{ "id": "0192c8e4-7b1a-7c3e-9f00-5a4b3c2d1e0f" }
```

Erreurs : `400`, `401`, `403`, `413`, `415`, `422`. Il n'y a pas de dédoublonnage : déposer deux fois le même mail crée deux Messages.

### `GET /v1/messages`

Liste paginée de `MessageSummary` visibles par le Compte, du plus récent au plus ancien (`receivedAt`, puis `id`). Rôles `admin` et `user`.

| Paramètre | Effet |
|---|---|
| `to` | seulement les Messages dont cette adresse est Destinataire |
| `since` | seulement les Messages reçus à cette date ou après |
| `unread` | `1` : seulement les Messages que le Compte n'a pas lus ; `0` : seulement ceux qu'il a lus ; absent : tous |
| `offset`, `limit` | pagination |

Une valeur invalide (adresse, date, `unread` autre que `0` ou `1`) reçoit `422`.

### `GET /v1/messages/{id}`

Renvoie le `MessageDetail` et marque le Message comme lu pour le Compte appelant (un `admin` qui lit un Message le marque lu pour lui seul). Rôles `admin` et `user`. `404` si le Message n'existe pas ou n'est pas visible.

### `GET /v1/messages/{id}/attachment/{partId}`

Renvoie le contenu décodé de la pièce jointe, tel quel. Rôles `admin` et `user`. Ne marque pas le Message comme lu.

```http
HTTP/1.1 200 OK
Content-Type: application/pdf
Content-Length: 48213
Content-Disposition: attachment; filename="facture.pdf"; filename*=UTF-8''facture.pdf
Content-Security-Policy: default-src 'none'; sandbox
```

`404` si le Message ou la pièce n'existe pas ou n'est pas visible.

### `DELETE /v1/messages/{id}`

Supprime définitivement le Message, pour tous les Comptes ([ADR 0008](adr/0008-suppression-definitive.md)). Rôles `admin` et `user` (sur les siens). `204`, ou `404`.

Indépendamment de toute suppression, l'API purge chaque heure les Messages reçus il y a plus de 30 jours ([ADR 0009](adr/0009-purge-apres-30-jours.md)).

## Endpoints Comptes

Toutes ces routes sont réservées au rôle `admin`. Le premier `admin` se crée en SQL ([`infra.md` § Créer un admin](infra.md#créer-un-admin)).

| Endpoint | Entrée | Réponse | Erreurs propres |
|---|---|---|---|
| `POST /v1/accounts` | `AccountCreate` | `201`, `Location`, `AccountSummary` | `409` nom pris |
| `GET /v1/accounts` | `offset`, `limit` | `200`, page d'`AccountSummary` triés par `name` | |
| `GET /v1/accounts/{id}` | | `200`, `AccountDetail` | `404` |
| `DELETE /v1/accounts/{id}` | | `204` ; supprime aussi ses Bindings, ses Jetons et ses lectures | `404` |
| `POST /v1/accounts/{id}/bindings` | `BindingCreate` | `201`, `{ "address": "…" }` | `404` Compte ; `409` déjà lié ; `422` Compte `bot` |
| `DELETE /v1/accounts/{id}/bindings/{address}` | | `204` | `404` |
| `POST /v1/accounts/{id}/tokens` | `TokenCreate` | `201`, `TokenCreated` | `404` Compte |
| `DELETE /v1/accounts/{id}/tokens/{tokenId}` | | `204` | `404` |

Un `admin` peut supprimer son propre Compte ou le Jeton qu'il utilise ; s'il ne reste aucun `admin`, on en recrée un en SQL.

## En-têtes de sécurité

Sur toutes les réponses :

- `Strict-Transport-Security: max-age=31536000`
- `X-Content-Type-Options: nosniff`
- `Cache-Control: no-store`

Aucun en-tête CORS : l'API n'est pas destinée aux navigateurs. Les pièces jointes portent en plus `Content-Disposition: attachment` et `Content-Security-Policy: default-src 'none'; sandbox`, pour qu'un HTML reçu ne s'exécute jamais sous le domaine de l'API.

L'API ne limite pas le débit et ne tient pas de journal d'audit : un jeton de 256 bits ne se devine pas, OVH filtre en amont et garde les journaux d'accès.
