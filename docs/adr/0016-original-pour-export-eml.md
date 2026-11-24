# L'Original conservé pour l'export EML

Les tests utilisateurs ouvrent les Messages dans Outlook, tels qu'un vrai destinataire les aurait reçus. Un mail reconstruit à partir du découpage de l'[ADR 0006](0006-depot-en-json-decoupe.md) n'y ressemble pas : ses images inline perdent leur `Content-ID`, et le HTML qui les cite par `cid:` s'affiche sans elles ; `Cc`, `Reply-To`, `X-Priority`, une invitation `text/calendar` et la structure MIME disparaissent.

Le Robot joint donc au dépôt l'Original, le mail que Mailpit a reçu en SMTP (`GET /api/v1/message/{ID}/raw`), en base64 dans le champ `raw`. L'API l'écrit tel quel à côté du découpage et le rend par `GET /v1/messages/{id}/eml`, en `message/rfc822`. Elle ne le lit jamais : le découpage reste la source de tout le reste du contrat, et l'API n'a toujours pas de parseur MIME.

L'Original garde son en-tête `Cc`, que le destinataire voit dans Outlook ; il ne donne toujours accès à rien ([ADR 0002](0002-seul-to-donne-acces.md)). Le Robot en retire ce que Mailpit ajoute et qu'aucun destinataire ne verrait : l'en-tête `Bcc`, qui liste les destinataires cachés, et l'en-tête `Received` de Mailpit, dont le `for` peut en nommer un.

Contreparties :

- un dépôt avec l'Original pèse environ 2,3 fois le mail. La limite du dépôt passe de 25 à 32 Mio ; au-delà, l'API répond `413` et le Robot redépose aussitôt le mail sans l'Original. La réception ne régresse jamais, mais un mail de plus de 14 Mo environ ne s'exporte pas ;
- le disque garde chaque mail deux fois, jusqu'à la purge à 30 jours ;
- un Message déposé sans Original (avant cette décision, ou trop gros) répond `404` à l'export.

Options écartées : un EML reconstruit par l'API à partir du découpage (infidèle, voir plus haut) ; le seul mail brut, décodé par l'API (l'option écartée de l'ADR 0006) ; l'Original par une seconde requête après le dépôt (le Robot devrait garder le mail dans Mailpit jusqu'à la seconde réponse, et un échec entre les deux laisserait un Message sans Original) ; une limite de 64 Mio, qui couvrirait les 25 Mo de Mailpit mais qu'il faudrait d'abord éprouver chez OVH.
