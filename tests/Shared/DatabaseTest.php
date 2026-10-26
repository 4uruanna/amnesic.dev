<?php

declare(strict_types=1);

namespace Apimail\Tests\Shared;

use Apimail\Tests\TestDatabase;
use PDO;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    public function testConnectionIsUtcUtf8mb4WithNativePrepares(): void
    {
        $pdo = TestDatabase::reset();

        self::assertSame(
            ['+00:00', 'utf8mb4'],
            $pdo->query('SELECT @@session.time_zone, @@session.character_set_connection')->fetch(PDO::FETCH_NUM)
        );
        self::assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        self::assertFalse($pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
    }
}
