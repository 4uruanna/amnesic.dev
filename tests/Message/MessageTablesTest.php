<?php

declare(strict_types=1);

namespace Apimail\Tests\Message;

use Apimail\Shared\Migrator;
use Apimail\Shared\Uuid;
use Apimail\Tests\TestDatabase;
use PDO;
use PHPUnit\Framework\TestCase;

final class MessageTablesTest extends TestCase
{
    public function testMigrationAppliesAfterAccountsMigration(): void
    {
        $directory = sys_get_temp_dir() . '/apimail-' . bin2hex(random_bytes(4));
        mkdir($directory);
        copy(__DIR__ . '/../../migrations/001_comptes.sql', "$directory/001_comptes.sql");
        $pdo = TestDatabase::empty();
        try {
            (new Migrator($pdo, $directory))->migrate();
        } finally {
            unlink("$directory/001_comptes.sql");
            rmdir($directory);
        }

        self::assertSame(['002_messages.sql', '003_lectures.sql'], (new Migrator($pdo))->migrate());
    }

    public function testDeletingAMessageDeletesItsRecipientsAndAttachments(): void
    {
        $pdo = TestDatabase::reset();
        [$kept, $deleted] = [Uuid::v7(), Uuid::v7()];
        foreach ([$kept, $deleted] as $id) {
            $pdo->prepare('INSERT INTO messages (id, from_address, received_at) VALUES (?, \'noreply@x.tld\', UTC_TIMESTAMP(3))')->execute([$id]);
            $pdo->prepare('INSERT INTO recipients (message_id, address) VALUES (?, \'ci@x.tld\'), (?, \'ops@x.tld\')')->execute([$id, $id]);
            $pdo->prepare('INSERT INTO attachments (message_id, part_id, filename, content_type, size) VALUES (?, 1, \'a.pdf\', \'application/pdf\', 3)')->execute([$id]);
        }

        $pdo->prepare('DELETE FROM messages WHERE id = ?')->execute([$deleted]);

        foreach (['recipients' => 2, 'attachments' => 1] as $table => $rows) {
            self::assertSame(
                [$kept => $rows],
                $pdo->query("SELECT message_id, COUNT(*) FROM $table GROUP BY message_id")->fetchAll(PDO::FETCH_KEY_PAIR),
                $table
            );
        }
    }

    public function testReadsGoAwayWithTheirMessageOrTheirAccount(): void
    {
        $pdo = TestDatabase::reset();
        [$ci, $ops, $kept, $deleted] = [Uuid::v7(), Uuid::v7(), Uuid::v7(), Uuid::v7()];
        foreach ([$ci => 'ci', $ops => 'ops'] as $id => $name) {
            $pdo->prepare('INSERT INTO accounts (id, name, role, created_at) VALUES (?, ?, \'user\', UTC_TIMESTAMP(3))')->execute([$id, $name]);
        }
        foreach ([$kept, $deleted] as $id) {
            $pdo->prepare('INSERT INTO messages (id, from_address, received_at) VALUES (?, \'noreply@x.tld\', UTC_TIMESTAMP(3))')->execute([$id]);
            $pdo->prepare('INSERT INTO message_reads (account_id, message_id) VALUES (?, ?), (?, ?)')->execute([$ci, $id, $ops, $id]);
        }

        $pdo->prepare('DELETE FROM messages WHERE id = ?')->execute([$deleted]);
        $pdo->prepare('DELETE FROM accounts WHERE id = ?')->execute([$ops]);

        self::assertSame([[$ci, $kept]], $pdo->query('SELECT account_id, message_id FROM message_reads')->fetchAll(PDO::FETCH_NUM));
    }
}
