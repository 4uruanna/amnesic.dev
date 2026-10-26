<?php

declare(strict_types=1);

use Apimail\Shared\Database;
use DI\ContainerBuilder;

if (!is_file(__DIR__ . '/../config.php')) {
    throw new RuntimeException('config.php manquant à la racine de l\'API : copier config.template.php en config.php et le remplir.');
}
require_once __DIR__ . '/../config.php';

return (new ContainerBuilder())
    ->addDefinitions([
        PDO::class => fn () => Database::connect(API_MAIL_DB_HOST, API_MAIL_DB_NAME, API_MAIL_DB_USER, API_MAIL_DB_PASSWORD),
    ])
    ->build();
