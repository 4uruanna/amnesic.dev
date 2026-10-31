# Image du Robot : docs/relais.md § Conteneurs. ext-curl est déjà dans php:8.4-cli.
FROM php:8.4-cli
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
# Un mail de 25 Mo tient en mémoire plusieurs fois pendant son dépôt : brut, en base64, puis en JSON.
RUN echo 'memory_limit = 512M' > "$PHP_INI_DIR/conf.d/robot.ini"
WORKDIR /robot
COPY composer.json composer.lock ./
COPY bin bin
COPY src src
RUN composer install --no-dev --classmap-authoritative --no-interaction --no-progress
CMD ["php", "bin/relais.php"]
