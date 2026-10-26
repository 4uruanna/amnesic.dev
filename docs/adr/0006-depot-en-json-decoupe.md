# Dépôt des Messages en JSON déjà découpé

`POST /v1/messages` reçoit un JSON déjà découpé par le Relais (expéditeur, destinataires `To`, sujet, texte, HTML, pièces jointes en base64), pas le mail brut RFC 5322. L'API n'embarque donc aucun parseur MIME, et le contrat d'entrée ne contient que ce que l'API garde : `Cc` et `Bcc` n'y figurent pas.

Contrepartie : chaque Relais doit savoir décoder le MIME, et le mail brut n'est jamais conservé (aucun en-tête d'origine n'est récupérable).

Option écartée : mail brut `message/rfc822` décodé par l'API avec `zbateson/mail-mime-parser`.
