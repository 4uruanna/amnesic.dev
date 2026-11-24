# J08-4 Recette de l'export

**Dépend de** : J08-3, J07-2
**Docs** : [infra.md § Déploiement](../../docs/infra.md#déploiement), [relais.md § Déploiement](../../docs/relais.md#déploiement)

## Objectif

La démo de sortie de J08 passe en production.

## Contenu

- L'API déployée par la fusion dans `main`, le Relais mis à jour sur la machine : `git pull && docker compose up -d --build` dans `robot/`.
- La démo de sortie de J08, en production ; relais.md et api.md corrigés de tout écart rencontré.

## Actions de ta part

- Fusionner dans `main`, puis mettre à jour le Relais sur la machine.
- Envoyer les deux mails de la démo depuis une boîte extérieure, et ouvrir les exports dans Outlook.

## Terminé quand

- La démo de sortie de J08 passe en production : l'image, le `Cc` et l'absence du `Cci` sont vérifiés dans Outlook.
- Le dépôt du mail de 16 Mo avec son Original, qui dépasse 32 Mio, reçoit bien `413` d'OVH, et non une erreur réseau ou une `5xx` qui bloquerait la relève.
