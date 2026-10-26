# Roadmap V1

apimail V1 : une API PHP (Slim 4, MySQL 8.4) sur l'Hébergement Web Pro OVH, qui reçoit les Messages déposés par un Relais externe et les expose en lecture aux Comptes autorisés par leurs Bindings. L'état cible est décrit dans la doc de référence : [contrat](../docs/api.md), [modèle de données](../docs/data-model.md), [infrastructure](../docs/infra.md), [glossaire](../CONTEXT.md), [ADR](../docs/adr/).

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
| [J01 Socle](j01-socle/README.md) | `curl https://api.<domaine>/v1/messages` avec un jeton bidon reçoit `401` « jeton invalide » en `problem+json` : l'en-tête `Authorization` arrive à PHP | 4 |
| [J02 Comptes et auth](j02-comptes-et-auth/README.md) | un admin créé en SQL crée un bot, un user, leurs jetons et un Binding | 4 |
| [J03 Dépôt](j03-depot/README.md) | le bot dépose un Message avec une pièce jointe ; un `cc` reçoit `422` | 3 |
| [J04 Lecture et suppression](j04-lecture-et-suppression/README.md) | un user lit ses Messages ; celui d'un autre Compte lui répond `404` | 4 |
| [J05 Purge et mise en prod](j05-purge-et-mise-en-prod/README.md) | un Message vieux de 31 jours disparaît à l'heure suivante ; la recette passe en production | 3 |
| **Total** | | **18** |

## Hors V1

- Envoi de mails, interface web.
- `Cc` et `Bcc` ([ADR 0002](../docs/adr/0002-seul-to-donne-acces.md)).
- Le Relais lui-même : projet séparé.
- Recherche plein texte, retour à « non lu », dédoublonnage des dépôts.
- Joker ou sous-adressage dans les Bindings.
- Expiration des jetons, gestion de ses jetons par un `user`.
- Limitation de débit, journal d'audit, sauvegarde applicative.
- Intégration continue et déploiement automatique.
