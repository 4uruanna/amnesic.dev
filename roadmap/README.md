# Roadmap V1

apimail V1 : une API PHP (Slim 4, MySQL 8.4) sur l'Hébergement Web Pro OVH, qui reçoit les Messages déposés par un Relais et les expose en lecture aux Comptes autorisés par leurs Bindings (J01 à J05). Puis le Relais lui-même : Mailpit et un Robot PHP, dans `robot/`, sur la machine qui reçoit le MX (J06 et J07). L'état cible est décrit dans la doc de référence : [contrat](../docs/api.md), [modèle de données](../docs/data-model.md), [architecture](../docs/architecture.md), [infrastructure](../docs/infra.md), [Relais](../docs/relais.md), [glossaire](../CONTEXT.md), [ADR](../docs/adr/).

## Lire la roadmap

- **Milestone** : livre une capacité utilisable de bout en bout et se termine par une démo. Un milestone ne commence que lorsque le précédent est clos.
- **Phase** : unité de livraison d'un milestone ; elle se livre seule et se termine par des critères vérifiables (« Terminé quand »).
- **Fichiers** :
  - `roadmap/README.md`, ce fichier : les milestones et ce qui est hors V1 ;
  - `roadmap/jNN-<slug>/README.md` : l'intro du milestone, sa démo de sortie, la liste ordonnée de ses phases ;
  - `roadmap/jNN-<slug>/<K>-<slug>.md` : une phase.
- **Identifiant** d'une phase : `JNN-K` (`J02-3`). Une phase ajoutée prend le numéro qui suit le plus grand de son milestone ; un numéro n'est jamais réutilisé.
- **Statut** : la case de la phase dans le README de son milestone, `- [ ]` ou `- [x]`, et nulle part ailleurs.
- **Git** : une branche `feature/jNN-<slug-de-la-phase>` par phase, fusionnée en squash dans `dev` ; commits en Conventional Commits avec le pied `Refs: JNN-K`.

## Milestones

| Milestone | Démo de sortie | Phases |
|:---|:---|---:|
| [J01 Socle](j01-socle/README.md) | `curl https://api.amnesic.dev/v1/messages` avec un jeton bidon reçoit `401` « jeton invalide » en `problem+json` : l'en-tête `Authorization` arrive à PHP | 6 |
| [J02 Comptes et auth](j02-comptes-et-auth/README.md) | un admin créé en SQL crée un bot, un user, leurs jetons et un Binding | 4 |
| [J03 Dépôt](j03-depot/README.md) | le bot dépose un Message avec une pièce jointe ; un `cc` reçoit `422` | 3 |
| [J04 Lecture et suppression](j04-lecture-et-suppression/README.md) | un user lit ses Messages ; celui d'un autre Compte lui répond `404` | 4 |
| [J05 Purge et mise en prod](j05-purge-et-mise-en-prod/README.md) | un Message vieux de 31 jours disparaît à l'heure suivante ; la recette passe en production | 3 |
| [J06 Robot](j06-robot/README.md) | en local, un mail remis à Mailpit arrive en moins de 5 secondes dans l'API ; un mail sans `To` reste dans Mailpit, étiqueté `rejet` | 3 |
| [J07 Relais en production](j07-relais-en-production/README.md) | un mail envoyé de Gmail à `ci@<domaine>` arrive en moins d'une minute dans l'API de production | 2 |
| **Total** | | **25** |

## Lancer une phase

Dans une nouvelle session, à la racine du dépôt, en remplaçant `JNN-K` :

```text
Lance la phase JNN-K de la roadmap apimail.

1. Lis CONTEXT.md, docs/architecture.md, roadmap/README.md, le README du milestone et la fiche
   de la phase, puis les sections de docs/ et les ADR qu'elle cite.
2. Pars de `dev` à jour, sur la branche `feature/jNN-<slug-de-la-phase>`.
3. Écris les tests d'abord ; PHPUnit tourne en PHP 8.4 dans Docker (docs/infra.md § Développement local ;
   pour le Robot, docs/relais.md § Développement local et tests).
4. Respecte le contrat (docs/api.md), le modèle (docs/data-model.md) et l'architecture
   (docs/architecture.md). Si la phase contredit la doc ou impose un choix qu'elle ne tranche pas,
   arrête-toi et pose-moi la question.
5. Ne me demande jamais la valeur d'un secret : nomme la variable, dis où elle va, et attends que
   je confirme qu'elle est définie. Fais de même pour les « Actions de ta part » de la fiche.
6. Quand chaque point de « Terminé quand » est vérifié : coche la phase dans le README du
   milestone, mets à jour les docs touchées, commite en Conventional Commits avec le pied
   `Refs: JNN-K`, puis fusionne en squash dans `dev`.
```

L'ordre des phases est celui de la liste de chaque milestone : la prochaine est la première case vide.

## Hors V1

- Envoi de mails, interface web.
- `Cc` et `Bcc` ([ADR 0002](../docs/adr/0002-seul-to-donne-acces.md)).
- Le webhook de Mailpit, plusieurs Robots en parallèle ([ADR 0015](../docs/adr/0015-releve-toutes-les-5-secondes.md)).
- Recherche plein texte, retour à « non lu », dédoublonnage des dépôts.
- Joker ou sous-adressage dans les Bindings.
- Expiration des jetons, gestion de ses jetons par un `user`.
- Limitation de débit, journal d'audit, sauvegarde applicative.
- Appel automatique de `/v1/setup` après un déploiement.
