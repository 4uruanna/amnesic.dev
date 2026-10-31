<?php

declare(strict_types=1);

// Le Robot du Relais (docs/relais.md) : vérifie la configuration, puis relève Mailpit toutes les POLL_SECONDS.

require __DIR__ . '/../vendor/autoload.php';

$config = [];
foreach (['APIMAIL_URL', 'APIMAIL_TOKEN', 'MAILPIT_URL', 'MAILPIT_USER', 'MAILPIT_PASS', 'POLL_SECONDS'] as $name) {
    $config[$name] = (string) getenv($name);
}
// Ne jamais afficher une valeur : APIMAIL_TOKEN et MAILPIT_PASS sont des secrets.
$missing = array_keys($config, '', true);
if ($missing !== []) {
    fwrite(STDERR, 'Variable absente ou vide dans .env : ' . implode(', ', $missing) . ".\n");
    exit(1);
}
if (preg_match('/\A[1-9][0-9]*\z/', $config['POLL_SECONDS']) !== 1) {
    fwrite(STDERR, "POLL_SECONDS doit être un nombre entier de secondes, 1 ou plus.\n");
    exit(1);
}
$poll = (int) $config['POLL_SECONDS'];

echo "started\n";

while (true) {
    sleep($poll);
}
