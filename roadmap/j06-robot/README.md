# J06 Robot

Le robot du Relais, sur le poste : il relève un Mailpit en conteneur, convertit chaque mail et le dépose dans l'API ([relais.md](../../docs/relais.md)). À la sortie, la chaîne Mailpit → robot → API tourne en local, et la branche `ovh` n'en contient rien.

## Démo de sortie

1. L'API locale (`php -S`, MySQL de `compose.yaml`) a un Bot et un user `ci` lié à `ci@example.com`. Dans `robot/`, avec un `.env` qui vise l'API locale : `docker compose up -d --build`.
2. Un mail avec une pièce jointe, remis à Mailpit pour `ci@example.com`, est listé moins de 5 secondes plus tard par `GET /v1/messages` de `ci`, pièce jointe identique ; Mailpit ne le garde pas.
3. Un mail sans `To` reste dans Mailpit avec l'étiquette `rejet` ; `docker compose logs robot` montre `rejected … 422 to: …`.
4. API arrêtée : le mail suivant attend dans Mailpit ; API relancée : il est déposé au cycle suivant.

## Phases

- [x] [J06-1 Squelette du robot](1-squelette-du-robot.md)
- [x] [J06-2 Conversion](2-conversion.md)
- [x] [J06-3 Relève](3-releve.md)
