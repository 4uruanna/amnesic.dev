# Jetons Bearer opaques, stockés hachés

Chaque appel s'authentifie par `Authorization: Bearer <jeton>` : 32 octets aléatoires écrits en 64 caractères hexadécimaux minuscules, dont la base ne garde que l'empreinte SHA-256 (un hash lent est inutile pour un secret de 256 bits). L'hexadécimal permet de créer un admin en SQL seul (`LOWER(HEX(RANDOM_BYTES(32)))`, `SHA2(…, 256)`) : l'API n'a pas de commande d'amorçage. Révoquer un jeton, c'est supprimer sa ligne. Le JWT n'apporte rien sans fédération d'identité et complique la révocation. Pas de mot de passe ni d'OAuth en V1.

Sur OVH, l'en-tête `Authorization` n'atteint PHP qu'avec `CGIPassAuth On` dans `.htaccess`.
