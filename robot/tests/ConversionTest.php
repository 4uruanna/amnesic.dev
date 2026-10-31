<?php

declare(strict_types=1);

namespace Robot\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Robot\Conversion;

/** Chaque règle de relais.md § Conversion, sur des messages capturés sur un vrai Mailpit (tests/fixtures/). */
final class ConversionTest extends TestCase
{
    public function testATextOnlyMail(): void
    {
        self::assertSame([
            'from' => ['address' => 'noreply@service.example', 'name' => 'Service CI'],
            'to' => [['address' => 'ci@example.com', 'name' => 'Équipe CI']],
            'subject' => 'Votre code',
            'sentAt' => '2026-10-30T11:59:58+01:00',
            'text' => "Code : 123456\r\n",
            'html' => null,
            'attachments' => [],
        ], self::deposit('texte-seul'));
    }

    public function testAnHtmlOnlyMailKeepsTheTextThatMailpitDerivesFromIt(): void
    {
        $deposit = self::deposit('html-seul');

        self::assertSame('Résumé HTML', $deposit['subject']);
        self::assertSame("<p>Code : <b>123456</b></p>\r\n", $deposit['html']);
        self::assertSame('Code : *123456*', $deposit['text']);
    }

    public function testAnEmptyNameBecomesNull(): void
    {
        self::assertSame(['address' => 'noreply@service.example', 'name' => null], self::deposit('expediteur-sans-nom')['from']);
        self::assertSame([
            ['address' => 'ci@example.com', 'name' => null],
            ['address' => 'autre@example.com', 'name' => 'Autre'],
        ], self::deposit('piece-jointe')['to']);
    }

    public function testCcAndBccAreIgnored(): void
    {
        $deposit = self::deposit('cc-bcc');

        self::assertSame(['from', 'to', 'subject', 'sentAt', 'text', 'html', 'attachments'], array_keys($deposit));
        self::assertSame([['address' => 'ci@example.com', 'name' => null]], $deposit['to']);
    }

    public function testAMailSentInBccOnlyHasNoTo(): void
    {
        self::assertSame([], self::deposit('sans-to')['to']);
    }

    public function testAttachmentsKeepTheirNameTypeAndContent(): void
    {
        self::assertSame([
            ['filename' => 'facture.pdf', 'contentType' => 'application/pdf', 'content' => 'JVBERi0xLjQKJcOkw7zDtsOfCjEgMCBvYmoKPDwvVHlwZS9DYXRhbG9nPj4KZW5kb2JqCg=='],
            ['filename' => 'été.txt', 'contentType' => 'text/plain', 'content' => 'w6l0w6kK'],
        ], self::deposit('piece-jointe')['attachments']);
    }

    /** Mailpit nomme une image inline sans nom d'après son Content-ID. */
    public function testAnInlineImageIsAnAttachmentToo(): void
    {
        self::assertSame([
            ['filename' => 'logo@service.example', 'contentType' => 'image/png', 'content' => self::fixture('inline-sans-nom')['parts']['2']],
        ], self::deposit('inline-sans-nom')['attachments']);
    }

    /** Aucune fixture n'a les deux : celle-ci joint l'image inline de inline-sans-nom à piece-jointe, sous la part 4. */
    public function testAttachmentsComeBeforeInlineParts(): void
    {
        ['message' => $message, 'parts' => $parts] = self::fixture('piece-jointe');
        $message['Inline'] = [['PartID' => '4'] + self::fixture('inline-sans-nom')['message']['Inline'][0]];
        $parts['4'] = self::fixture('inline-sans-nom')['parts']['2'];

        $deposit = Conversion::deposit($message, array_map(base64_decode(...), $parts));

        self::assertSame(['facture.pdf', 'été.txt', 'logo@service.example'], array_column($deposit['attachments'], 'filename'));
    }

    /** Mailpit ne liste pas une pièce sans nom ni Content-ID : un FileName vide ne vient que d'un cas qu'il n'a pas produit ici. */
    public function testAnEmptyFileNameBecomesPartieAndThePartId(): void
    {
        ['message' => $message, 'parts' => $parts] = self::fixture('inline-sans-nom');
        $message['Inline'][0]['FileName'] = '';

        $deposit = Conversion::deposit($message, array_map(base64_decode(...), $parts));

        self::assertSame('partie-2', $deposit['attachments'][0]['filename']);
    }

    /** Le contenu déposé est celui de la part, octet pour octet : son SHA-256 est celui que Mailpit calcule. */
    #[DataProvider('fixtures')]
    public function testTheContentIsThePartInBase64(string $name): void
    {
        $pieces = Conversion::pieces(self::fixture($name)['message']);

        self::assertSame(
            array_column(array_column($pieces, 'Checksums'), 'SHA256'),
            array_map(fn (array $attachment) => hash('sha256', base64_decode($attachment['content'], true)), self::deposit($name)['attachments']),
        );
    }

    /** @return array<string, array{string}> */
    public static function fixtures(): array
    {
        $names = array_map(fn (string $path) => basename($path, '.json'), glob(__DIR__ . '/fixtures/*.json'));

        return array_combine($names, array_map(fn (string $name) => [$name], $names));
    }

    /** @return array<string, mixed> le MessageDeposit tiré de cette fixture */
    public static function deposit(string $name): array
    {
        ['message' => $message, 'parts' => $parts] = self::fixture($name);

        return Conversion::deposit($message, array_map(base64_decode(...), $parts));
    }

    /** @return array{message: array<string, mixed>, parts: array<string, string>} le message Mailpit et ses parts en base64 */
    public static function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__ . "/fixtures/$name.json"), true, flags: JSON_THROW_ON_ERROR);
    }
}
