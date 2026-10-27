<?php

declare(strict_types=1);

namespace Apimail\Tests;

use Apimail\Shared\Database;
use Apimail\Shared\Migrator;
use PDO;

/** La base apimail_test, sur le serveur de config.php : jamais celle de API_MAIL_DB_NAME. */
final class TestDatabase
{
    /** Vide la base, puis lui applique migrations/. */
    public static function reset(): PDO
    {
        $pdo = self::empty();
        (new Migrator($pdo))->migrate();

        return $pdo;
    }

    /** Vide la base, sans même schema_migrations : l'état d'une installation neuve. */
    public static function empty(): PDO
    {
        require_once __DIR__ . '/../config.php';
        $pdo = Database::connect(API_MAIL_DB_HOST, 'apimail_test', API_MAIL_DB_USER, API_MAIL_DB_PASSWORD);

        $tables = $pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()')
            ->fetchAll(PDO::FETCH_COLUMN);
        if ($tables !== []) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $pdo->exec('DROP TABLE `' . implode('`, `', $tables) . '`');
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        return $pdo;
    }
}
