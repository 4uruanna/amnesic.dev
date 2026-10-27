<?php

declare(strict_types=1);

namespace Apimail\Shared;

use PDO;
use SensitiveParameter;

final class Database
{
    public static function connect(string $host, string $name, string $user, #[SensitiveParameter] string $password): PDO
    {
        $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    }
}
