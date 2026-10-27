<?php

declare(strict_types=1);

namespace Apimail\Tests\Message;

use Apimail\Message\MessageService;
use Apimail\Shared\Uuid;
use Apimail\Tests\TestApp;
use PDO;
use PHPUnit\Framework\TestCase;

/** La purge, qui n'a pas de route : bin/purge.php l'appelle sur le MessageService du conteneur. */
final class MessageServiceTest extends TestCase
{
    private TestApp $app;

    protected function setUp(): void
    {
        $this->app = new TestApp();
    }

    public function testPurgeErasesTheMessagesReceivedMoreThan30DaysAgo(): void
    {
        $bot = $this->app->token('bot');
        [$old, $recent] = array_map(fn () => TestApp::json($this->app->request('POST', '/v1/messages', $bot, [
            'from' => ['address' => 'noreply@x.tld'],
            'to' => [['address' => 'ci@x.tld']],
            'text' => 'Code',
            'attachments' => [['filename' => 'a.txt', 'contentType' => 'text/plain', 'content' => 'SGVsbG8=']],
        ]))['id'], [1, 2]);
        $this->app->pdo->prepare('UPDATE messages SET received_at = UTC_TIMESTAMP(3) - INTERVAL (CASE id WHEN ? THEN 31 ELSE 29 END) DAY')->execute([$old]);

        self::assertSame(1, $this->purge());

        self::assertSame([$recent], $this->ids());
        self::assertDirectoryDoesNotExist("{$this->app->storage}/messages/$old");
        self::assertFileExists("{$this->app->storage}/messages/$recent/text");
        self::assertFileExists("{$this->app->storage}/messages/$recent/parts/1");
        self::assertSame(0, $this->purge());
    }

    public function testPurgeErasesMoreThanABatchInOnePass(): void
    {
        $old = array_map(fn () => Uuid::v7(), range(1, 501));
        $recent = Uuid::v7();
        $this->app->pdo->prepare(
            'INSERT INTO messages (id, from_address, received_at) VALUES '
            . implode(', ', array_fill(0, count($old), "(?, 'noreply@x.tld', UTC_TIMESTAMP(3) - INTERVAL 31 DAY)"))
            . ", (?, 'noreply@x.tld', UTC_TIMESTAMP(3))"
        )->execute([...$old, $recent]);

        self::assertSame(501, $this->purge());
        self::assertSame([$recent], $this->ids());
    }

    private function purge(): int
    {
        return $this->app->get(MessageService::class)->purge();
    }

    /** @return list<string> */
    private function ids(): array
    {
        return $this->app->pdo->query('SELECT id FROM messages')->fetchAll(PDO::FETCH_COLUMN);
    }
}
