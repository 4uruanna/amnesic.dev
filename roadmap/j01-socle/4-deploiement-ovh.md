# J01-4 Déploiement OVH

**Dépend de** : J01-2, J01-3
**Docs** : [infra.md § Mise en place](../../docs/infra.md#mise-en-place-une-fois), [§ Déploiement](../../docs/infra.md#déploiement)

## Objectif

L'API tourne sur `api.<domaine>`, déployée par un script rejouable qui ne touche jamais aux données du serveur.

## Contenu

- `public/.ovhconfig` (`app.engine.version=8.4`) et `public/.htaccess` (`CGIPassAuth`, redirection HTTPS, front controller), tels que `infra.md`.
- `deploy.sh` : `composer install --no-dev` dans un dossier temporaire, `rsync --delete` des seuls `public/`, `src/`, `bin/`, `migrations/`, `vendor/`, puis migrations en SSH avec `/usr/local/php8.4/bin/php`.

## Actions de ta part

- Dans le Control Panel : la base MySQL 8.4, le Multisite `api.<domaine>` → `apimail/public` avec pare-feu désactivé, ta clé SSH.
- Sur le serveur : `/apimail/config.php`, rempli par toi.
- Dans ton shell : `OVH_SSH`.

## Terminé quand

- La démo de sortie de J01 passe.
- Un fichier témoin posé dans `/apimail/storage/` et `config.php` survivent à un second `deploy.sh`.
