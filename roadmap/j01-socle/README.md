# J01 Socle

Le squelette Slim, la base et ses migrations, la garde Bearer et le premier déploiement sur OVH. À la sortie, la chaîne complète (poste → OVH → PHP) est prouvée, en particulier l'arrivée de l'en-tête `Authorization`, le piège connu de l'hébergement.

## Démo de sortie

```sh
curl -i https://api.amnesic.dev/v1/messages -H "Authorization: Bearer $(printf '0%.0s' {1..64})"
# 401, application/problem+json, detail « jeton invalide » (et non « jeton absent »)
curl -i http://api.amnesic.dev/v1/messages
# 301 vers https://
```

## Phases

- [x] [J01-1 Squelette Slim](1-squelette-slim.md)
- [x] [J01-5 Architecture en couches](5-architecture-en-couches.md)
- [x] [J01-2 Base et migrations](2-base-et-migrations.md)
- [x] [J01-3 Garde Bearer](3-garde-bearer.md)
- [x] [J01-6 Intégration continue et branche `ovh`](6-integration-continue-et-branche-ovh.md)
- [ ] [J01-4 Déploiement OVH](4-deploiement-ovh.md)
