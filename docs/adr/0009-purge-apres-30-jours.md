# Purge horaire des Messages de plus de 30 jours

Un script PHP lancé par le cron OVH, une fois par heure (la fréquence maximale de l'offre), supprime définitivement les Messages dont `receivedAt` date de plus de 30 jours, par le même chemin que `DELETE /v1/messages/{id}` (métadonnées puis fichiers). L'âge se mesure sur `receivedAt`, posé par l'API, et non sur `sentAt`, fourni par l'expéditeur.

Conséquence : aucun Message n'est conservé au-delà de 30 jours, lu ou non.
