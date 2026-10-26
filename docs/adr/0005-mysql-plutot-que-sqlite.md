# MySQL 8.4 plutôt que SQLite

Les fichiers de l'hébergement OVH sont sur un stockage réseau partagé par plusieurs serveurs web, ce qui rend les verrous SQLite peu fiables, et le SQLite fourni (3.27) n'a ni `RETURNING` ni `STRICT`. On utilise une base MySQL 8.4 incluse dans l'offre Pro : 2 Go, 30 connexions simultanées, passage en lecture seule au-delà du quota.
