# Déploiement par l'intégration Git d'OVH, depuis une branche `ovh`

OVH clone la branche `ovh` du dépôt GitHub dans le dossier du site `www.amnesic.dev` et la met à jour par `git pull`, sur webhook ou par « Deploy Git ». Ce `pull` ne lance ni Composer ni aucun script : une GitHub Action, après des tests verts sur `main`, construit `vendor/` sans les dépendances de dev et ajoute sur `ovh` un commit qui ne contient que ce qui tourne en production. Elle ne pousse jamais en force, pour que le `pull` d'OVH reste une avance rapide.

Comme OVH sert le clone entier, un `.htaccess` à la racine envoie toute requête à `public/index.php` : `.git/`, `src/` et `config.php` n'ont pas d'URL.

Options écartées : `deploy.sh` en `rsync` depuis le poste, `vendor/` commité dans `main`.
