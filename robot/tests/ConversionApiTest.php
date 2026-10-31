<?php

declare(strict_types=1);

namespace Robot\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Le dépôt tiré de chaque fixture, soumis à la vraie API : seule juge du contrat (relais.md § Conversion). */
final class ConversionApiTest extends TestCase
{
    /** @var array{bot: string, ci: string} */
    private array $tokens;

    protected function setUp(): void
    {
        $this->tokens = Api::reset();
    }

    #[DataProvider('fixtures')]
    public function testTheApiAcceptsTheDepositOfEveryFixture(string $name): void
    {
        $deposit = ConversionTest::deposit($name);

        [$code, $body] = Api::request('POST', '/v1/messages', $this->tokens['bot'], $deposit);

        if ($name === 'sans-to') {
            self::assertSame(422, $code, $body);
            self::assertSame(['to'], array_column(json_decode($body, true)['errors'], 'field'));

            return;
        }
        self::assertSame(201, $code, $body);
        // Chaque pièce revient à ci telle que Mailpit l'a donnée.
        $id = json_decode($body, true)['id'];
        foreach ($deposit['attachments'] as $index => $attachment) {
            [$code, $content] = Api::request('GET', "/v1/messages/$id/attachment/" . ($index + 1), $this->tokens['ci']);
            self::assertSame(200, $code);
            self::assertSame(base64_decode($attachment['content'], true), $content);
        }
    }

    /** @return array<string, array{string}> */
    public static function fixtures(): array
    {
        return ConversionTest::fixtures();
    }
}
