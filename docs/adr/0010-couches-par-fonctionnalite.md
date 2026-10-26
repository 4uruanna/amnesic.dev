# Architecture en couches, rangée par fonctionnalité

Le code est rangé par fonctionnalité (`src/Account/`, `src/Message/`, plus `src/Shared/`) et, dans chacune, en couches : le Controller traduit HTTP en DTO et inversement, le Service porte les règles métier, les transactions et les fichiers, le Repository fait le SQL et renvoie des Models. Les Models sont calqués sur les tables, les DTO sur le contrat d'`api.md` : les deux évoluent séparément, au prix d'une conversion `fromModel()` dans le Controller.

Options écartées : rangement par couche (`src/Controller/`, `src/Service/`…), Repositories qui renvoient directement les DTO ou des tableaux.
