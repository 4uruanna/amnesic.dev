# J01 Socle

Le squelette Slim, la base et ses migrations, la garde Bearer et le premier déploiement sur OVH. À la sortie, la chaîne complète (poste → OVH → PHP) est prouvée, en particulier l'arrivée de l'en-tête `Authorization`, le piège connu de l'hébergement.

## Démo de sortie

```sh
curl -i https://api.<domaine>/v1/messages -H "Authorization: Bearer $(printf '0%.0s' {1..64})"
# 401, application/problem+json, detail « jeton invalide » (et non « jeton absent »)
curl -i http://api.<domaine>/v1/messages
# 301 vers https://
```

## Phases

- [ ] [J01-1 Squelette Slim](1-squelette-slim.md)
- [ ] [J01-2 Base et migrations](2-base-et-migrations.md)
- [ ] [J01-3 Garde Bearer](3-garde-bearer.md)
- [ ] [J01-4 Déploiement OVH](4-deploiement-ovh.md)
