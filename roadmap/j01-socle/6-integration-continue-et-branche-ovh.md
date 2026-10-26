# J01-6 Intégration continue et branche `ovh`

**Dépend de** : J01-2
**Docs** : [infra.md § Déploiement](../../docs/infra.md#déploiement), [ADR 0012](../../docs/adr/0012-deploiement-git-ovh.md)

## Objectif

Le dépôt est sur GitHub ; chaque push y est testé, et chaque push vert sur `main` publie la branche `ovh` que déploie OVH.

## Contenu

- `.github/workflows/ci.yml` :
  - à chaque push et pull request : PHP 8.4 avec `pdo_mysql`, service MySQL 8.4, `config.php` écrit pour ce service (mot de passe jetable du conteneur de CI, pas un secret), `composer install`, PHPUnit ;
  - sur un push vers `main`, dans un job qui dépend des tests (`needs`) : `composer install --no-dev --optimize-autoloader`, puis un commit sur `ovh` qui ne contient que la liste d'`infra.md` § Déploiement, ajouté par-dessus le précédent (jamais de push forcé ; `ovh` est créée au premier passage) ;
  - `contents: write` seulement pour le job de publication.

## Actions de ta part

- Créer le dépôt privé `apimail` sur GitHub et me donner son URL SSH ; je pousse `main` et `dev`.

## Terminé quand

- Un push sur `dev` lance les tests sans rien publier.
- Un push sur `main` publie sur `ovh` un commit sans `docs/`, `roadmap/`, `tests/` ni dépendances de dev ; un second push ajoute un commit par-dessus le premier.
