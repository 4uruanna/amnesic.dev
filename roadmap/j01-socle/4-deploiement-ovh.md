# J01-4 Déploiement OVH

**Dépend de** : J01-2, J01-3, J01-6
**Docs** : [infra.md § Mise en place](../../docs/infra.md#mise-en-place-une-fois), [§ Déploiement](../../docs/infra.md#déploiement), [ADR 0012](../../docs/adr/0012-deploiement-git-ovh.md)

## Objectif

L'API tourne sur `https://www.amnesic.dev`, déployée par l'intégration Git d'OVH depuis la branche `ovh`, sans que les déploiements touchent aux données du serveur.

## Contenu

- `.ovhconfig` (`app.engine.version=8.4`) et `.htaccess` (`CGIPassAuth`, redirection HTTPS, tout vers `public/index.php`) à la racine du dépôt, tels qu'`infra.md`.

## Actions de ta part

- Control Panel : la base MySQL 8.4 ; sur la ligne `www.amnesic.dev` de « Mes sites », SSL actif, pare-feu applicatif désactivé, dossier vidé.
- « Lier Git » sur la branche `ovh`, avec la clé SSH d'OVH en deploy key du dépôt (lecture seule) et le webhook.
- `www/config.php`, copié de `config.template.php` et rempli par toi.

## Terminé quand

- La démo de sortie de J01 passe.
- `https://www.amnesic.dev/.git/config`, `/config.php` et `/src/app.php` répondent `404`.
- Un fichier témoin dans `www/storage/` et `www/config.php` survivent à un déploiement par webhook, puis à un « Deploy Git » forcé.
