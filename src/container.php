<?php

declare(strict_types=1);

use Apimail\Message\MessageFiles;
use Apimail\Shared\Database;
use DI\ContainerBuilder;

if (!is_file(__DIR__ . '/../config.php')) {
    throw new RuntimeException('config.php manquant à la racine de l\'API : copier config.template.php en config.php et le remplir.');
}
// Un config.php qui commence par « <php » au lieu de « <?php » est du texte : PHP l'écrirait, mot de passe compris, en tête de chaque réponse.
ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();
if (!defined('API_MAIL_DB_PASSWORD')) {
    throw new RuntimeException('config.php invalide : il doit commencer par <?php et définir les constantes de config.template.php.');
}

return (new ContainerBuilder())
    ->addDefinitions([
        PDO::class => fn () => Database::connect(API_MAIL_DB_HOST, API_MAIL_DB_NAME, API_MAIL_DB_USER, API_MAIL_DB_PASSWORD),
        // storage/ est toujours le voisin de config.php (infra.md § config.php).
        MessageFiles::class => fn () => new MessageFiles(__DIR__ . '/../storage'),
    ])
    ->build();
