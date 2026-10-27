<?php

declare(strict_types=1);

namespace Apimail\Tests\Message;

use Apimail\Tests\TestApp;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

final class MessageControllerTest extends TestCase
{
    private const UUID = '/\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/';
    private const array MINIMAL = ['from' => ['address' => 'noreply@x.tld'], 'to' => [['address' => 'ci@x.tld']]];

    private TestApp $app;
    private string $bot;

    protected function setUp(): void
    {
        $this->app = new TestApp();
        $this->bot = $this->app->token('bot', 'relais-mx');
    }

    public function testBotDepositsAMessageStoredInDatabaseAndOnDisk(): void
    {
        $pdf = "%PDF-1.7\x00\xff\xfe" . random_bytes(1000);
        $before = gmdate('Y-m-d H:i:s');

        $response = $this->app->request('POST', '/v1/messages', $this->bot, [
            'from' => ['address' => 'NoReply@Service.tld', 'name' => 'Service'],
            'to' => [['address' => 'CI@amnesic.dev', 'name' => 'CI'], ['address' => 'ops@amnesic.dev'], ['address' => 'ci@amnesic.dev', 'name' => 'Doublon']],
            'subject' => 'Votre code',
            'sentAt' => '2026-10-26T11:59:58.5+02:00',
            'text' => "Code : 123456\r\n",
            'html' => '<p>Code : <b>123456</b></p>',
            'attachments' => [
                ['filename' => 'facture é.pdf', 'contentType' => 'application/pdf', 'content' => base64_encode($pdf)],
                ['filename' => 'vide.txt', 'contentType' => 'text/plain; charset=utf-8', 'content' => ''],
            ],
        ]);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $body = TestApp::json($response);
        self::assertSame(['id'], array_keys($body));
        $id = $body['id'];
        self::assertMatchesRegularExpression(self::UUID, $id);
        self::assertSame("/v1/messages/$id", $response->getHeaderLine('Location'));

        $message = $this->rows('SELECT id, from_address, from_name, subject, sent_at, received_at FROM messages')[0];
        self::assertSame([$id, 'noreply@service.tld', 'Service', 'Votre code', '2026-10-26 09:59:58.500'], array_slice(array_values($message), 0, 5));
        self::assertGreaterThanOrEqual($before, $message['received_at']);
        self::assertSame(
            [[$id, 'ci@amnesic.dev', 'CI'], [$id, 'ops@amnesic.dev', null]],
            $this->rows('SELECT message_id, address, name FROM recipients ORDER BY address', PDO::FETCH_NUM)
        );
        self::assertSame(
            [[$id, 1, 'facture é.pdf', 'application/pdf', strlen($pdf)], [$id, 2, 'vide.txt', 'text/plain; charset=utf-8', 0]],
            $this->rows('SELECT message_id, part_id, filename, content_type, size FROM attachments ORDER BY part_id', PDO::FETCH_NUM)
        );

        $directory = "{$this->app->storage}/messages/$id";
        self::assertSame(['html', 'parts', 'text'], self::list($directory));
        self::assertSame(['1', '2'], self::list("$directory/parts"));
        self::assertSame("Code : 123456\r\n", file_get_contents("$directory/text"));
        self::assertSame('<p>Code : <b>123456</b></p>', file_get_contents("$directory/html"));
        self::assertSame($pdf, file_get_contents("$directory/parts/1"));
        self::assertSame('', file_get_contents("$directory/parts/2"));
    }

    public function testMinimalDepositWritesNoFile(): void
    {
        $id = TestApp::json($this->app->request('POST', '/v1/messages', $this->bot, self::MINIMAL))['id'];

        self::assertSame([[$id, '', null]], $this->rows('SELECT id, subject, sent_at FROM messages', PDO::FETCH_NUM));
        self::assertSame([], self::list("{$this->app->storage}/messages/$id"));
    }

    public function testOnlyABotDeposits(): void
    {
        foreach (['admin', 'user'] as $role) {
            self::assertProblem($this->app->request('POST', '/v1/messages', $this->app->token($role), self::MINIMAL), 403, 'Rôle non autorisé.');
        }
        self::assertSame([], $this->rows('SELECT id FROM messages'));
    }

    public function testInvalidDepositIs422OnTheField(): void
    {
        $response = $this->app->request('POST', '/v1/messages', $this->bot, [...self::MINIMAL, 'cc' => [['address' => 'cc@x.tld']]]);

        self::assertProblem($response, 422, 'Le corps de la requête est invalide.');
        self::assertSame([['field' => 'cc', 'message' => 'Champ inconnu.']], TestApp::json($response)['errors']);
    }

    public function testBodyOver25MiBIs413(): void
    {
        // Taille réellement lue, sans Content-Length : 26 Mio.
        $response = $this->app->request('POST', '/v1/messages', $this->bot, '"' . str_repeat('a', 26 * 1024 * 1024 - 2) . '"');
        self::assertProblem($response, 413, 'Corps de plus de 25 Mio.');
        self::assertSame('Content Too Large', TestApp::json($response)['title']);

        // Content-Length annoncé, avant toute lecture.
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/v1/messages')
            ->withHeader('Authorization', "Bearer {$this->bot}")
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Length', (string) (26 * 1024 * 1024));
        $request->getBody()->write(json_encode(self::MINIMAL));
        self::assertProblem($this->app->handle($request), 413, 'Corps de plus de 25 Mio.');

        self::assertSame([], $this->rows('SELECT id FROM messages'));
    }

    public function testFailedTransactionLeavesNoRowNorDirectory(): void
    {
        // L'insertion des attachments échoue, après celles de messages et de recipients.
        $this->app->pdo->exec('DROP TABLE attachments');
        $this->expectErrorLog();

        $response = $this->app->request('POST', '/v1/messages', $this->bot, [
            ...self::MINIMAL,
            'text' => 'Code',
            'attachments' => [['filename' => 'a.pdf', 'contentType' => 'application/pdf', 'content' => 'SGVsbG8=']],
        ]);

        self::assertProblem($response, 500, 'Erreur interne.');
        self::assertSame([], $this->rows('SELECT id FROM messages'));
        self::assertSame([], $this->rows('SELECT message_id FROM recipients'));
        self::assertSame([], self::list("{$this->app->storage}/messages"));
    }

    /** @return list<array<mixed>> */
    private function rows(string $sql, int $mode = PDO::FETCH_ASSOC): array
    {
        return $this->app->pdo->query($sql)->fetchAll($mode);
    }

    /** @return list<string> le contenu du dossier, trié ; vide s'il n'existe pas */
    private static function list(string $directory): array
    {
        return is_dir($directory) ? array_values(array_diff(scandir($directory), ['.', '..'])) : [];
    }

    private static function assertProblem(ResponseInterface $response, int $status, string $detail): void
    {
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertSame($detail, TestApp::json($response)['detail']);
    }
}
