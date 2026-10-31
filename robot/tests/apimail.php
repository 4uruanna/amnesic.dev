<?php

declare(strict_types=1);

// La vraie API pour les tests du robot, sur la base apimail_test (relais.md § Développement local et tests) :
// - en CLI, remet la base à neuf, y crée le Bot relais et le user ci lié à ci@example.com, et affiche leurs jetons en JSON ;
// - en routeur de php -S, sert l'API sur cette base, avec APIMAIL_TEST_STORAGE pour storage/.

use Apimail\Message\MessageFiles;
use Apimail\Shared\Database;
use Apimail\Tests\TestApp;

require __DIR__ . '/../../vendor/autoload.php';

if (PHP_SAPI === 'cli') {
    $api = new TestApp();
    $tokens = ['bot' => $api->token('bot', 'relais'), 'ci' => $api->token('user', 'ci')];
    $api->pdo->exec("INSERT INTO bindings (account_id, address) SELECT id, 'ci@example.com' FROM accounts WHERE name = 'ci'");
    echo json_encode($tokens);
    exit;
}

$app = require __DIR__ . '/../../src/app.php';
$app->getContainer()->set(PDO::class, Database::connect(API_MAIL_DB_HOST, 'apimail_test', API_MAIL_DB_USER, API_MAIL_DB_PASSWORD));
$app->getContainer()->set(MessageFiles::class, new MessageFiles((string) getenv('APIMAIL_TEST_STORAGE')));
$app->run();
