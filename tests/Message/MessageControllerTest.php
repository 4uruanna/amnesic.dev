<?php

declare(strict_types=1);

namespace Apimail\Tests\Message;

use Apimail\Shared\Uuid;
use Apimail\Tests\TestApp;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /** MySQL ne garde dans un DATETIME que les années 0000 à 9999 : au-delà en UTC, la date est refusée au lieu d'une 500. */
    public function testSentAtIsStoredFromYear0000To9999InUtc(): void
    {
        foreach (['0000-01-01T00:00:00Z' => '0000-01-01 00:00:00.000', '9999-12-31T23:59:59.999Z' => '9999-12-31 23:59:59.999'] as $sentAt => $stored) {
            $id = TestApp::json($this->app->request('POST', '/v1/messages', $this->bot, [...self::MINIMAL, 'sentAt' => $sentAt]))['id'];
            self::assertSame([$stored], $this->rows("SELECT sent_at FROM messages WHERE id = '$id'", PDO::FETCH_COLUMN));
        }
        self::assertProblem($this->app->request('POST', '/v1/messages', $this->bot, [...self::MINIMAL, 'sentAt' => '9999-12-31T23:00:00-05:00']), 422, 'Le corps de la requête est invalide.');
        self::assertProblem($this->app->request('GET', '/v1/messages?since=' . rawurlencode('9999-12-31T23:00:00-05:00'), $this->app->token('admin')), 422, 'Les paramètres de la requête sont invalides.');
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

    public function testListGivesSummariesNewestFirst(): void
    {
        $older = $this->deposit(['ci@amnesic.dev']);
        $newer = $this->deposit([], [
            'from' => ['address' => 'NoReply@Service.tld', 'name' => 'Service'],
            'to' => [['address' => 'Ops@amnesic.dev', 'name' => 'Ops'], ['address' => 'ci@amnesic.dev']],
            'subject' => 'Votre code',
            'sentAt' => '2026-10-26T11:59:58.5+02:00',
            'attachments' => [['filename' => 'a.pdf', 'contentType' => 'application/pdf', 'content' => 'SGVsbG8=']],
        ]);
        $this->receivedAt($older, '2026-10-27 07:00:00.000');
        $this->receivedAt($newer, '2026-10-27 08:00:00.250');

        $response = $this->app->request('GET', '/v1/messages', $this->app->token('admin'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame([
            'items' => [
                [
                    'id' => $newer,
                    'from' => ['address' => 'noreply@service.tld', 'name' => 'Service'],
                    'to' => [['address' => 'ci@amnesic.dev', 'name' => null], ['address' => 'ops@amnesic.dev', 'name' => 'Ops']],
                    'subject' => 'Votre code',
                    'sentAt' => '2026-10-26T09:59:58.500Z',
                    'receivedAt' => '2026-10-27T08:00:00.250Z',
                    'hasAttachments' => true,
                    'unread' => true,
                ],
                [
                    'id' => $older,
                    'from' => ['address' => 'noreply@x.tld', 'name' => null],
                    'to' => [['address' => 'ci@amnesic.dev', 'name' => null]],
                    'subject' => '',
                    'sentAt' => null,
                    'receivedAt' => '2026-10-27T07:00:00.000Z',
                    'hasAttachments' => false,
                    'unread' => true,
                ],
            ],
            'total' => 2,
            'offset' => 0,
            'limit' => 20,
        ], TestApp::json($response));
    }

    public function testUserListsOnlyTheMessagesOfItsBindingsEvenThoseReceivedBefore(): void
    {
        $ci = $this->deposit(['ci@amnesic.dev']);
        $ops = $this->deposit(['ops@amnesic.dev']);
        $both = $this->deposit(['ops@amnesic.dev', 'ci@amnesic.dev']);
        $unbound = $this->deposit(['personne@amnesic.dev']);
        // Bindings créés après la réception des Messages.
        $ciToken = $this->user('ci', 'ci@amnesic.dev');
        $opsToken = $this->user('ops', 'ops@amnesic.dev', 'ci@amnesic.dev');

        self::assertSame([$both, $ci], $this->ids($ciToken));
        // Lié à deux Destinataires de $both, ops ne le voit qu'une fois.
        self::assertSame([$both, $ops, $ci], $this->ids($opsToken));
        self::assertSame(3, $this->page($opsToken)['total']);
        self::assertSame([], $this->ids($this->user('seul')));
        // Un Message sans Destinataire lié n'est vu que de l'admin.
        self::assertSame([$unbound, $both, $ops, $ci], $this->ids($this->app->token('admin')));
    }

    public function testListFiltersOnARecipient(): void
    {
        $ci = $this->deposit(['ci@amnesic.dev']);
        $both = $this->deposit(['ops@amnesic.dev', 'ci@amnesic.dev']);
        $this->deposit(['ops@amnesic.dev']);
        $user = $this->user('ci', 'ci@amnesic.dev');

        self::assertSame([$both, $ci], $this->ids($this->app->token('admin'), '?to=CI@amnesic.dev'));
        // Le filtre restreint ce que le Compte voit déjà, sans rien y ajouter.
        self::assertSame([$both], $this->ids($user, '?to=ops@amnesic.dev'));
        self::assertSame([], $this->ids($user, '?to=personne@amnesic.dev'));
    }

    public function testListFiltersOnTheReceptionDateIncluded(): void
    {
        [$before, $at, $after] = [$this->deposit(['ci@x.tld']), $this->deposit(['ci@x.tld']), $this->deposit(['ci@x.tld'])];
        $this->receivedAt($before, '2026-10-26 09:59:59.999');
        $this->receivedAt($at, '2026-10-26 10:00:00.000');
        $this->receivedAt($after, '2026-10-26 10:00:00.001');
        $admin = $this->app->token('admin');

        self::assertSame([$after, $at], $this->ids($admin, '?since=' . rawurlencode('2026-10-26T12:00:00+02:00')));
        self::assertSame([$after], $this->ids($admin, '?since=2026-10-26T10:00:00.0005Z'));
    }

    public function testUnreadFilterAndFlagAreThoseOfTheCaller(): void
    {
        $read = $this->deposit(['ci@x.tld']);
        $unread = $this->deposit(['ci@x.tld']);
        $ci = $this->user('ci', 'ci@x.tld');
        $ops = $this->user('ops', 'ci@x.tld');
        $this->app->pdo->prepare('INSERT INTO message_reads (account_id, message_id) SELECT id, ? FROM accounts WHERE name = \'ci\'')->execute([$read]);

        self::assertSame([$unread], $this->ids($ci, '?unread=1'));
        self::assertSame([$read], $this->ids($ci, '?unread=0'));
        self::assertSame([$unread => true, $read => false], array_column($this->page($ci)['items'], 'unread', 'id'));
        self::assertSame([$unread, $read], $this->ids($ops, '?unread=1'));
        self::assertSame([], $this->ids($ops, '?unread=0'));
    }

    public function testListIsPagedWithTheTotalOfTheFilters(): void
    {
        $ids = array_map(fn (int $i) => $this->deposit([$i % 2 === 0 ? 'ci@x.tld' : 'ops@x.tld']), range(0, 4));
        // Même receivedAt : l'id départage, du plus grand au plus petit.
        $this->app->pdo->exec('UPDATE messages SET received_at = \'2026-10-26 10:00:00.000\'');
        $admin = $this->app->token('admin');

        $page = $this->page($admin, '?offset=1&limit=2');
        self::assertSame([$ids[3], $ids[2]], array_column($page['items'], 'id'));
        self::assertSame([5, 1, 2], [$page['total'], $page['offset'], $page['limit']]);

        $page = $this->page($admin, '?to=ci@x.tld&offset=2');
        self::assertSame([$ids[0]], array_column($page['items'], 'id'));
        self::assertSame([3, 2, 20], [$page['total'], $page['offset'], $page['limit']]);

        $page = $this->page($admin, '?offset=10');
        self::assertSame([[], 5], [$page['items'], $page['total']]);
    }

    public function testDetailGivesTheContentAndTheAttachmentInfos(): void
    {
        $id = $this->deposit([], [
            'from' => ['address' => 'NoReply@Service.tld', 'name' => 'Service'],
            'to' => [['address' => 'ci@amnesic.dev', 'name' => 'CI']],
            'subject' => 'Votre code',
            'sentAt' => '2026-10-26T11:59:58.5+02:00',
            'text' => "Code : 123456\r\n",
            'html' => '<p>Code : <b>123456</b></p>',
            'attachments' => [
                ['filename' => 'facture é.pdf', 'contentType' => 'application/pdf', 'content' => base64_encode('%PDF-1.7')],
                ['filename' => 'vide.txt', 'contentType' => 'text/plain; charset=utf-8', 'content' => ''],
            ],
        ]);
        $this->receivedAt($id, '2026-10-27 08:00:00.250');

        $response = $this->app->request('GET', "/v1/messages/$id", $this->user('ci', 'ci@amnesic.dev'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame([
            'id' => $id,
            'from' => ['address' => 'noreply@service.tld', 'name' => 'Service'],
            'to' => [['address' => 'ci@amnesic.dev', 'name' => 'CI']],
            'subject' => 'Votre code',
            'sentAt' => '2026-10-26T09:59:58.500Z',
            'receivedAt' => '2026-10-27T08:00:00.250Z',
            'hasAttachments' => true,
            'text' => "Code : 123456\r\n",
            'html' => '<p>Code : <b>123456</b></p>',
            'attachments' => [
                ['partId' => 1, 'filename' => 'facture é.pdf', 'contentType' => 'application/pdf', 'size' => 8],
                ['partId' => 2, 'filename' => 'vide.txt', 'contentType' => 'text/plain; charset=utf-8', 'size' => 0],
            ],
        ], TestApp::json($response));
    }

    public function testDetailGivesNullForAPartThatWasNotDeposited(): void
    {
        $id = $this->deposit(['ci@x.tld'], ['text' => '']);

        $detail = TestApp::json($this->app->request('GET', "/v1/messages/$id", $this->app->token('admin')));

        self::assertSame(['', null, [], false], [$detail['text'], $detail['html'], $detail['attachments'], $detail['hasAttachments']]);
    }

    public function testDetailMarksTheMessageReadForTheCallerOnly(): void
    {
        $id = $this->deposit(['ci@x.tld']);
        $ci = $this->user('ci', 'ci@x.tld');
        $ops = $this->user('ops', 'ci@x.tld');
        $admin = $this->app->token('admin');

        foreach ([$ci, $ci, $admin] as $token) {
            self::assertSame(200, $this->app->request('GET', "/v1/messages/$id", $token)->getStatusCode());
        }

        self::assertSame([], $this->ids($ci, '?unread=1'));
        self::assertSame([$id], $this->ids($ci, '?unread=0'));
        self::assertSame([$id], $this->ids($ops, '?unread=1'));
        self::assertSame([], $this->ids($admin, '?unread=1'));
        // Deux lectures de ci, une seule ligne.
        self::assertSame(['admin', 'ci'], $this->rows('SELECT a.name FROM message_reads mr JOIN accounts a ON a.id = mr.account_id ORDER BY a.name', PDO::FETCH_COLUMN));
    }

    public function testDetailOfAnUnknownOrInvisibleMessageIs404WithoutMarking(): void
    {
        $id = $this->deposit(['ops@x.tld']);
        $ci = $this->user('ci', 'ci@x.tld');

        self::assertProblem($this->app->request('GET', "/v1/messages/$id", $ci), 404, 'Message introuvable.');
        self::assertProblem($this->app->request('GET', '/v1/messages/' . Uuid::v7(), $ci), 404, 'Message introuvable.');
        self::assertProblem($this->app->request('GET', '/v1/messages/' . Uuid::v7(), $this->app->token('admin')), 404, 'Message introuvable.');
        // Hors de l'alphabet des UUID, aucune route : MySQL ne compare pas un « é » à une colonne ascii.
        self::assertSame(404, $this->app->request('GET', '/v1/messages/%C3%A9', $ci)->getStatusCode());
        self::assertSame([], $this->rows('SELECT message_id FROM message_reads'));
    }

    public function testOnlyAdminsAndUsersReadTheDetail(): void
    {
        $id = $this->deposit(['ci@x.tld']);

        self::assertProblem($this->app->request('GET', "/v1/messages/$id", $this->bot), 403, 'Rôle non autorisé.');
    }

    public function testAttachmentIsTheDepositedContentServedAsADownload(): void
    {
        $pdf = "%PDF-1.7\x00\xff\xfe" . random_bytes(1000);
        $id = $this->deposit(['ci@x.tld'], ['attachments' => [
            ['filename' => 'vide.txt', 'contentType' => 'text/plain; charset=utf-8', 'content' => ''],
            ['filename' => 'facture "été" 2026.pdf', 'contentType' => 'application/pdf', 'content' => base64_encode($pdf)],
        ]]);
        $ci = $this->user('ci', 'ci@x.tld');

        $response = $this->app->request('GET', "/v1/messages/$id/attachment/2", $ci);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($pdf, (string) $response->getBody());
        self::assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        self::assertSame((string) strlen($pdf), $response->getHeaderLine('Content-Length'));
        // Entre guillemets, l'ASCII imprimable seul ; filename* porte le nom exact (RFC 6266).
        self::assertSame(
            'attachment; filename="facture __t__ 2026.pdf"; filename*=UTF-8\'\'facture%20%22%C3%A9t%C3%A9%22%202026.pdf',
            $response->getHeaderLine('Content-Disposition')
        );
        self::assertSame("default-src 'none'; sandbox", $response->getHeaderLine('Content-Security-Policy'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));

        $response = $this->app->request('GET', "/v1/messages/$id/attachment/1", $ci);
        self::assertSame(['', 'text/plain; charset=utf-8', '0'], [(string) $response->getBody(), $response->getHeaderLine('Content-Type'), $response->getHeaderLine('Content-Length')]);
    }

    public function testAttachmentOfAnUnknownPartOrAnInvisibleMessageIs404(): void
    {
        $id = $this->deposit(['ops@x.tld'], ['attachments' => [['filename' => 'a.txt', 'contentType' => 'text/plain', 'content' => 'SGVsbG8=']]]);
        $ops = $this->user('ops', 'ops@x.tld');

        self::assertProblem($this->app->request('GET', "/v1/messages/$id/attachment/2", $ops), 404, 'Pièce jointe introuvable.');
        self::assertProblem($this->app->request('GET', "/v1/messages/$id/attachment/1", $this->user('ci', 'ci@x.tld')), 404, 'Message introuvable.');
        self::assertProblem($this->app->request('GET', '/v1/messages/' . Uuid::v7() . '/attachment/1', $ops), 404, 'Message introuvable.');
        foreach (['0', '01', '1x', '-1'] as $partId) {
            self::assertSame(404, $this->app->request('GET', "/v1/messages/$id/attachment/$partId", $ops)->getStatusCode(), $partId);
        }
        self::assertProblem($this->app->request('GET', "/v1/messages/$id/attachment/1", $this->bot), 403, 'Rôle non autorisé.');
    }

    public function testAttachmentDoesNotMarkTheMessageRead(): void
    {
        $id = $this->deposit(['ci@x.tld'], ['attachments' => [['filename' => 'a.txt', 'contentType' => 'text/plain', 'content' => 'SGVsbG8=']]]);
        $ci = $this->user('ci', 'ci@x.tld');

        self::assertSame('Hello', (string) $this->app->request('GET', "/v1/messages/$id/attachment/1", $ci)->getBody());
        self::assertSame([$id], $this->ids($ci, '?unread=1'));
    }

    public function testUserDeletesASharedMessageForEveryAccount(): void
    {
        $id = $this->deposit(['ci@x.tld', 'ops@x.tld'], ['text' => 'Code', 'attachments' => [['filename' => 'a.txt', 'contentType' => 'text/plain', 'content' => 'SGVsbG8=']]]);
        $kept = $this->deposit(['ci@x.tld'], ['text' => 'Gardé']);
        $ci = $this->user('ci', 'ci@x.tld');
        $ops = $this->user('ops', 'ops@x.tld');
        $this->app->request('GET', "/v1/messages/$id", $ops);

        $response = $this->app->request('DELETE', "/v1/messages/$id", $ci);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertProblem($this->app->request('GET', "/v1/messages/$id", $ops), 404, 'Message introuvable.');
        self::assertProblem($this->app->request('DELETE', "/v1/messages/$id", $ops), 404, 'Message introuvable.');
        self::assertSame([], $this->ids($ops));
        self::assertSame([$kept], $this->ids($this->app->token('admin')));
        self::assertDirectoryDoesNotExist("{$this->app->storage}/messages/$id");
        self::assertFileExists("{$this->app->storage}/messages/$kept/text");
        // Les cascades emportent ses Destinataires, sa pièce jointe et la lecture de ops.
        foreach (['recipients', 'attachments', 'message_reads'] as $table) {
            self::assertNotContains($id, $this->rows("SELECT message_id FROM $table", PDO::FETCH_COLUMN), $table);
        }
    }

    public function testAdminDeletesAMessageNoAccountIsBoundTo(): void
    {
        $id = $this->deposit(['personne@x.tld']);

        self::assertSame(204, $this->app->request('DELETE', "/v1/messages/$id", $this->app->token('admin'))->getStatusCode());
        self::assertSame([], $this->rows('SELECT id FROM messages'));
    }

    public function testDeletingAnInvisibleOrUnknownMessageIs404AndABotIs403(): void
    {
        $id = $this->deposit(['ops@x.tld'], ['text' => 'Code']);

        self::assertProblem($this->app->request('DELETE', "/v1/messages/$id", $this->user('ci', 'ci@x.tld')), 404, 'Message introuvable.');
        self::assertProblem($this->app->request('DELETE', '/v1/messages/' . Uuid::v7(), $this->app->token('admin')), 404, 'Message introuvable.');
        self::assertProblem($this->app->request('DELETE', "/v1/messages/$id", $this->bot), 403, 'Rôle non autorisé.');
        self::assertSame([$id], $this->rows('SELECT id FROM messages', PDO::FETCH_COLUMN));
        self::assertFileExists("{$this->app->storage}/messages/$id/text");
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidListQueries(): iterable
    {
        yield 'unread=2' => ['unread=2', 'unread'];
        yield 'since=hier' => ['since=hier', 'since'];
        yield 'limit=101' => ['limit=101', 'limit'];
        yield 'to=ci' => ['to=ci', 'to'];
    }

    #[DataProvider('invalidListQueries')]
    public function testListWithAnInvalidParameterIs422(string $query, string $field): void
    {
        $response = $this->app->request('GET', "/v1/messages?$query", $this->app->token('user'));

        self::assertProblem($response, 422, 'Les paramètres de la requête sont invalides.');
        self::assertSame([$field], array_column(TestApp::json($response)['errors'], 'field'));
    }

    /**
     * Dépose par le bot un Message pour ces adresses ; $fields complète ou remplace le corps. Renvoie son id.
     *
     * @param list<string> $to
     * @param array<string, mixed> $fields
     */
    private function deposit(array $to, array $fields = []): string
    {
        $body = [...self::MINIMAL, 'to' => array_map(fn (string $address) => ['address' => $address], $to), ...$fields];

        return TestApp::json($this->app->request('POST', '/v1/messages', $this->bot, $body))['id'];
    }

    private function receivedAt(string $id, string $date): void
    {
        $this->app->pdo->prepare('UPDATE messages SET received_at = ? WHERE id = ?')->execute([$date, $id]);
    }

    /** Crée un Compte user lié à ces adresses, et renvoie le secret de son Jeton. */
    private function user(string $name, string ...$addresses): string
    {
        $token = $this->app->token('user', $name);
        $bind = $this->app->pdo->prepare('INSERT INTO bindings (account_id, address) SELECT id, ? FROM accounts WHERE name = ?');
        foreach ($addresses as $address) {
            $bind->execute([$address, $name]);
        }

        return $token;
    }

    /** @return array<mixed> la réponse de GET /v1/messages, qui doit être 200 */
    private function page(string $token, string $query = ''): array
    {
        $response = $this->app->request('GET', "/v1/messages$query", $token);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return TestApp::json($response);
    }

    /** @return list<string> les id de la page, dans l'ordre */
    private function ids(string $token, string $query = ''): array
    {
        return array_column($this->page($token, $query)['items'], 'id');
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
