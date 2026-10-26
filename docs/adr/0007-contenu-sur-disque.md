# Contenu des Messages sur disque, métadonnées dans MySQL

Le texte, le HTML et les pièces jointes d'un Message sont écrits dans des fichiers hors du dossier public ; MySQL ne garde que les métadonnées. Une base MySQL de l'offre Pro est plafonnée à 2 Go et passe en lecture seule au-delà, ce qui bloquerait toute réception, alors que le disque fait 250 Go.

Contrepartie : base et fichiers peuvent diverger (fichier orphelin après une suppression interrompue), et une sauvegarde doit couvrir les deux.
