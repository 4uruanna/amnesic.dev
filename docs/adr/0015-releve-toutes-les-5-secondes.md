# Relève de Mailpit toutes les 5 secondes, suppression après dépôt

Le robot interroge l'API de Mailpit toutes les 5 secondes, dépose chaque mail, puis le supprime de Mailpit dès que l'API répond `201`. Mailpit n'est qu'une file d'attente : un mail qui y reste n'est pas encore déposé, ou a été refusé et porte alors l'étiquette `rejet`. La suppression tient lieu du dédoublonnage que l'API ne fait pas. Une panne de l'API ou du robot ne perd rien : le cycle suivant reprend ce qui attend, dans la limite de `--max-age` (7 jours) et de `--max` (2000 mails, étiquetés `rejet` compris), au-delà desquelles Mailpit supprime les plus anciens.

Option écartée : le webhook de Mailpit. Il ne réessaie jamais un appel raté et ignore les mails arrivés pendant sa limite de débit ; il aurait fallu une relève de rattrapage en plus, et un serveur HTTP dans le robot.

Conséquence : un mail met jusqu'à 5 secondes, plus la durée du dépôt, à arriver dans l'API.
