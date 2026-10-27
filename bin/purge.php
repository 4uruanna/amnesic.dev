<?php

declare(strict_types=1);

// Lancé chaque heure par le cron OVH (infra.md § Purge) : un code non nul compte comme un échec.

use Apimail\Message\MessageService;

require __DIR__ . '/../vendor/autoload.php';

try {
    $container = require __DIR__ . '/../src/container.php';
    echo 'purged ' . $container->get(MessageService::class)->purge() . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
