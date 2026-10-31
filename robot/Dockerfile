# Image du Robot : docs/relais.md § Conteneurs. ext-curl est déjà dans php:8.4-cli.
FROM php:8.4-cli
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
# Un mail de 25 Mo tient en mémoire plusieurs fois pendant son dépôt : le JSON de Mailpit, qui écrit « < » en six
# caractères et tire Text du HTML (environ 300 Mo pour 24 Mo de « < »), ses pièces, leur base64, puis le dépôt en JSON.
RUN echo 'memory_limit = 1G' > "$PHP_INI_DIR/conf.d/robot.ini"
WORKDIR /robot
COPY composer.json composer.lock ./
COPY bin bin
COPY src src
RUN composer install --no-dev --classmap-authoritative --no-interaction --no-progress
CMD ["php", "bin/relais.php"]
