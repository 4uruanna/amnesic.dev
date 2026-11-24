# J08-3 Original relevé par le Robot

**Dépend de** : J08-2, J06-3
**Docs** : [relais.md § Cycle](../../docs/relais.md#cycle), [§ Conversion](../../docs/relais.md#conversion), [§ Erreurs](../../docs/relais.md#erreurs), [§ Journal](../../docs/relais.md#journal), [ADR 0016](../../docs/adr/0016-original-pour-export-eml.md)

## Objectif

Chaque mail que dépose le Robot emporte son Original, sauf s'il est trop gros, et rien n'y nomme un destinataire caché.

## Contenu

- `Mailpit::raw()` : `GET /api/v1/message/{ID}/raw`.
- `Conversion` : `raw`, le mail de Mailpit sans ses en-têtes `Bcc` ni l'en-tête `Received` de Mailpit, en base64.
- `Relais` : sur un `413`, le mail redéposé aussitôt sans l'Original ; journal `deposited … without raw`.
- Une fixture capturée sur Mailpit 1.32 avec son mail `/raw` : image inline, `Cc`, destinataire caché.

## Terminé quand

- Tests verts :
  - l'export d'un mail remis en SMTP et déposé par le robot est ce mail, plus le `Return-Path` de Mailpit, sans `Bcc` ni `Received` de Mailpit ;
  - les fins de ligne et les octets hors UTF-8 du mail sont intacts ;
  - un mail de 16 Mo est déposé sans Original, journalisé `without raw`, et son export répond `404` ;
  - un `413` sans l'Original étiquette le mail `rejet`, comme avant.
