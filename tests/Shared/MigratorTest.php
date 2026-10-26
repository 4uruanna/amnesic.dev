<?php

declare(strict_types=1);

namespace Apimail\Tests\Shared;

use Apimail\Shared\Migrator;
use Apimail\Tests\TestDatabase;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
        // Sans les migrations réelles notées par reset() : seules celles du test comptent.
        $this->pdo->exec('DROP TABLE schema_migrations');
    }

    public function testAppliesPendingMigrationsInOrderOnlyOnce(): void
    {
        $migrator = new Migrator($this->pdo, __DIR__ . '/migrations/ok');

        self::assertSame(['001_create.sql', '002_alter.sql'], $migrator->migrate());
        self::assertSame(['001_create.sql', '002_alter.sql'], $this->versions());
        self::assertSame(
            [['id' => 1, 'label' => 'b']],
            $this->pdo->query('SELECT id, label FROM migrator_ok')->fetchAll(PDO::FETCH_ASSOC)
        );

        self::assertSame([], $migrator->migrate());
        self::assertSame(['001_create.sql', '002_alter.sql'], $this->versions());
    }

    public function testFailingMigrationIsNotNotedAndEarlierOnesStay(): void
    {
        try {
            (new Migrator($this->pdo, __DIR__ . '/migrations/failing'))->migrate();
            self::fail('La migration en échec devait lever une exception.');
        } catch (PDOException) {
        }

        self::assertSame(['001_create.sql'], $this->versions());
    }

    /** @return list<string> */
    private function versions(): array
    {
        return $this->pdo->query('SELECT version FROM schema_migrations ORDER BY version')
            ->fetchAll(PDO::FETCH_COLUMN);
    }
}
