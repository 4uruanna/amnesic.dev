<?php

declare(strict_types=1);

namespace Robot\Tests;

use PHPUnit\Framework\TestCase;
use Robot\Apimail;
use Robot\Mailpit;
use Robot\Relais;

/** Un cycle contre un vrai Mailpit et la vraie API, sans mocks : relais.md § Cycle, § Erreurs, § Journal. */
final class RelaisTest extends TestCase
{
    /** @var array{bot: string, ci: string} */
    private array $tokens;
    /** @var list<string> */
    private array $journal = [];

    protected function setUp(): void
    {
        $this->tokens = Api::reset();
        TestMailpit::clear();
    }

    protected function tearDown(): void
    {
        // Le journal ne porte jamais un secret.
        foreach ([$this->tokens['bot'], (string) getenv('MAILPIT_PASS')] as $secret) {
            foreach ($this->journal as $line) {
                self::assertStringNotContainsString($secret, $line);
            }
        }
    }

    public function testADepositedMailLeavesMailpitAndTheApiShowsIt(): void
    {
        $id = TestMailpit::send(self::mail('Votre code', [
            'Attachments' => [['Filename' => 'facture.pdf', 'ContentType' => 'application/pdf', 'Content' => base64_encode("%PDF-1.4\n\x00\xff")]],
        ]));

        $this->cycle();

        self::assertSame([], TestMailpit::messages());
        $items = Api::get('/v1/messages', $this->tokens['ci'])['items'];
        self::assertSame(['Votre code'], array_column($items, 'subject'));
        self::assertSame(["deposited $id {$items[0]['id']}"], $this->journal);
        [$code, $content] = Api::request('GET', "/v1/messages/{$items[0]['id']}/attachment/1", $this->tokens['ci']);
        self::assertSame([200, "%PDF-1.4\n\x00\xff"], [$code, $content]);
    }

    public function testARejectedMailStaysInMailpitTaggedAndIsNeverDepositedAgain(): void
    {
        $id = TestMailpit::send(self::mail('Copie cachée seule', ['To' => [], 'Bcc' => ['ci@example.com']]));

        $this->cycle();
        $this->cycle();

        self::assertSame([$id => ['rejet']], TestMailpit::messages());
        self::assertCount(1, $this->journal);
        self::assertMatchesRegularExpression("/\\Arejected $id 422 to: .+\\z/", $this->journal[0]);
    }

    public function testEveryErrorOfTheResponseHasItsLine(): void
    {
        $id = TestMailpit::send(self::mail(str_repeat('a', 999), ['To' => [], 'Bcc' => ['ci@example.com']]));

        $this->cycle();

        self::assertSame([$id => ['rejet']], TestMailpit::messages());
        self::assertCount(2, $this->journal);
        self::assertStringStartsWith("rejected $id 422 to: ", $this->journal[0]);
        self::assertStringStartsWith("rejected $id 422 subject: ", $this->journal[1]);
    }

    /** Mailpit lit l'en-tête Date « +2400 », mais ne sait pas le rendre en JSON : GET /api/v1/message/{ID} lui répond 400, à jamais. */
    public function testAMailThatMailpitCannotGiveIsRejectedWithoutHoldingBackTheNextOnes(): void
    {
        $id = TestMailpit::smtp("From: noreply@service.example\r\nTo: ci@example.com\r\nSubject: Date impossible\r\nDate: Thu, 30 Oct 2026 12:00:00 +2400\r\n\r\nCorps\r\n");
        $next = TestMailpit::send(self::mail('Votre code'));

        $this->cycle();
        $this->cycle();

        self::assertSame([$id => ['rejet']], TestMailpit::messages());
        self::assertCount(2, $this->journal);
        self::assertSame("rejected $id mailpit 400", $this->journal[0]);
        self::assertStringStartsWith("deposited $next ", $this->journal[1]);
    }

    /** Une date que Mailpit rend, mais qui sort en UTC des années 0000 à 9999 de MySQL : l'API la refuse au lieu d'une 500. */
    public function testASentAtBeyondYear9999IsRejected(): void
    {
        $id = TestMailpit::smtp("From: noreply@service.example\r\nTo: ci@example.com\r\nSubject: An 10000\r\nDate: Fri, 31 Dec 9999 23:00:00 -0500\r\n\r\nCorps\r\n");

        $this->cycle();

        self::assertSame([$id => ['rejet']], TestMailpit::messages());
        self::assertCount(1, $this->journal);
        self::assertStringStartsWith("rejected $id 422 sentAt: ", $this->journal[0]);
    }

    public function testAnInvalidTokenLeavesTheMailUntilTheApiAcceptsIt(): void
    {
        $id = TestMailpit::send(self::mail('Votre code'));

        $this->cycle(token: str_repeat('0', 64));

        self::assertSame([$id => []], TestMailpit::messages());
        self::assertSame(['retry apimail 401'], $this->journal);

        $this->cycle();

        self::assertSame([], TestMailpit::messages());
        self::assertSame(1, Api::get('/v1/messages', $this->tokens['ci'])['total']);
    }

    public function testAnApiThatDoesNotAnswerLeavesTheMailUntilItDoes(): void
    {
        $id = TestMailpit::send(self::mail('Votre code'));

        $this->cycle(apimail: 'http://127.0.0.1:1');

        self::assertSame([$id => []], TestMailpit::messages());
        self::assertCount(1, $this->journal);
        self::assertStringStartsWith('retry apimail: ', $this->journal[0]);

        $this->cycle();

        self::assertSame([], TestMailpit::messages());
        self::assertSame(1, Api::get('/v1/messages', $this->tokens['ci'])['total']);
    }

    public function testAMailpitThatDoesNotAnswerAbandonsTheCycle(): void
    {
        $this->cycle(mailpit: 'http://127.0.0.1:1');

        self::assertCount(1, $this->journal);
        self::assertStringStartsWith('retry mailpit: ', $this->journal[0]);
    }

    public function testMailsReachTheApiInTheOrderMailpitReceivedThem(): void
    {
        foreach (['un', 'deux', 'trois'] as $subject) {
            TestMailpit::send(self::mail($subject));
        }

        $this->cycle();

        // L'API liste du plus récent au plus ancien.
        self::assertSame(['trois', 'deux', 'un'], array_column(Api::get('/v1/messages', $this->tokens['ci'])['items'], 'subject'));
    }

    public function testACycleTakesEveryPageOfMailpit(): void
    {
        foreach (range(1, 51) as $n) {
            TestMailpit::send(self::mail("mail $n"));
        }

        $this->cycle();

        self::assertSame([], TestMailpit::messages());
        $page = Api::get('/v1/messages?limit=100', $this->tokens['ci']);
        self::assertSame(51, $page['total']);
        self::assertSame(['mail 51', 'mail 1'], [$page['items'][0]['subject'], $page['items'][50]['subject']]);
    }

    /** bin/relais.php, avec la configuration de relais.md § Configuration : un mail arrive dans l'API au cycle suivant. */
    public function testTheRobotRelaysEveryPollSeconds(): void
    {
        $robot = proc_open([PHP_BINARY, __DIR__ . '/../bin/relais.php'], [1 => ['pipe', 'w']], $pipes, null, [
            'APIMAIL_URL' => Api::url(),
            'APIMAIL_TOKEN' => $this->tokens['bot'],
            'MAILPIT_URL' => (string) getenv('MAILPIT_URL'),
            'MAILPIT_USER' => (string) getenv('MAILPIT_USER'),
            'MAILPIT_PASS' => (string) getenv('MAILPIT_PASS'),
            'POLL_SECONDS' => '1',
        ]);
        $this->journal[] = rtrim(fgets($pipes[1]));
        $id = TestMailpit::send(self::mail('Votre code'));
        $this->journal[] = rtrim(fgets($pipes[1]));
        proc_terminate($robot);
        proc_close($robot);

        self::assertSame('started', $this->journal[0]);
        self::assertStringStartsWith("deposited $id ", $this->journal[1]);
        self::assertSame(1, Api::get('/v1/messages', $this->tokens['ci'])['total']);
    }

    private function cycle(?string $apimail = null, ?string $token = null, ?string $mailpit = null): void
    {
        $relais = new Relais(
            new Mailpit($mailpit ?? (string) getenv('MAILPIT_URL'), (string) getenv('MAILPIT_USER'), (string) getenv('MAILPIT_PASS')),
            new Apimail($apimail ?? Api::url(), $token ?? $this->tokens['bot']),
            function (string $line): void {
                $this->journal[] = $line;
            },
        );
        $relais->cycle();
    }

    /**
     * @param array<string, mixed> $more
     * @return array<string, mixed> un corps de POST /api/v1/send pour ci@example.com
     */
    private static function mail(string $subject, array $more = []): array
    {
        return $more + [
            'From' => ['Email' => 'noreply@service.example', 'Name' => 'Service'],
            'To' => [['Email' => 'ci@example.com']],
            'Subject' => $subject,
            'Text' => 'Code : 123456',
        ];
    }
}
