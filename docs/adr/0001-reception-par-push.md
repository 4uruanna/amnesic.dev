# Réception des mails par push d'un relais externe

L'Hébergement Web Pro OVH ne peut pas écouter en SMTP, ses boîtes MX Plan ne savent ni rediriger vers un script ni appeler un webhook, et son cron tourne au mieux toutes les heures. Les mails arrivent donc par `POST /v1/messages`, envoyés par un relais externe qui reçoit le MX. Le relais est un projet séparé : cette API n'en connaît que le contrat d'entrée.

Le Relais n'est plus un projet séparé depuis l'[ADR 0014](0014-relais-mailpit-et-robot.md).

Option écartée : relève IMAP par cron (une heure de latence, dépendance à `ext/imap`, sorti du cœur de PHP 8.4).
