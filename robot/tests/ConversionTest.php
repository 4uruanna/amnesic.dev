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
            'raw' => null,
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

        self::assertSame(['from', 'to', 'subject', 'sentAt', 'text', 'html', 'attachments', 'raw'], array_keys($deposit));
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

        $deposit = Conversion::deposit($message, array_map(base64_decode(...), $parts), null);

        self::assertSame(['facture.pdf', 'été.txt', 'logo@service.example'], array_column($deposit['attachments'], 'filename'));
    }

    /** Mailpit ne liste pas une pièce sans nom ni Content-ID : un FileName vide ne vient que d'un cas qu'il n'a pas produit ici. */
    public function testAnEmptyFileNameBecomesPartieAndThePartId(): void
    {
        ['message' => $message, 'parts' => $parts] = self::fixture('inline-sans-nom');
        $message['Inline'][0]['FileName'] = '';

        $deposit = Conversion::deposit($message, array_map(base64_decode(...), $parts), null);

        self::assertSame('partie-2', $deposit['attachments'][0]['filename']);
    }

    /** Mailpit met en tête Bcc, Return-Path et son Received ; seul Return-Path reste devant le mail remis en SMTP. */
    public function testTheOriginalIsTheMailOfMailpitWithoutWhatNamesAHiddenRecipient(): void
    {
        $raw = base64_decode(self::fixture('original')['raw'], true);

        $original = base64_decode(self::deposit('original')['raw'], true);

        self::assertStringStartsWith("Bcc: cache@example.com\r\nReturn-Path: <noreply@service.example>\r\nReceived: ", $raw);
        self::assertSame("Return-Path: <noreply@service.example>\r\n" . substr($raw, strpos($raw, "From: Service CI")), $original);
        self::assertStringNotContainsString('cache@example.com', $original);
        self::assertStringContainsString("\r\nCc: Copie <copie@example.com>\r\n", $original);
    }

    /** Seuls Bcc et le Received de Mailpit partent, repliés ou non, en CRLF comme en LF ; le corps n'est jamais touché. */
    public function testOnlyTheBccAndTheReceivedOfMailpitLeaveTheHeader(): void
    {
        $mailpit = "Received: from c83ae5a1de86 (localhost [127.0.0.1])\r\n        by c83ae5a1de86 (Mailpit) with SMTP\r\n        for <un@example.com>; Tue, 24 Nov 2026 08:39:47 +0000 (UTC)\r\n";
        $raw = "bcc: un@example.com,\r\n\tdeux@example.com\r\n$mailpit"
            . "Received: from mx.service.example by relais.service.example; Thu, 30 Oct 2026 11:00:00 +0000\r\n"
            . "From: noreply@service.example\r\nTo: ci@example.com\r\nX-Bcc: gardé\r\nBcc: trois@example.com\r\n\r\nBcc: dans le corps, gardé\r\n\r\nCaf\xe9\n";
        $kept = "Received: from mx.service.example by relais.service.example; Thu, 30 Oct 2026 11:00:00 +0000\r\n"
            . "From: noreply@service.example\r\nTo: ci@example.com\r\nX-Bcc: gardé\r\n\r\nBcc: dans le corps, gardé\r\n\r\nCaf\xe9\n";

        self::assertSame($kept, Conversion::original($raw));
        self::assertSame(str_replace("\r\n", "\n", $kept), Conversion::original(str_replace("\r\n", "\n", $raw)));
        // Un mail sans corps n'a pas de ligne vide : tout est en-tête.
        self::assertSame('Subject: s', Conversion::original("Bcc: un@example.com\r\nSubject: s"));
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

    /** @return array<string, mixed> le MessageDeposit tiré de cette fixture, avec son Original si elle l'a */
    public static function deposit(string $name): array
    {
        $fixture = self::fixture($name);

        return Conversion::deposit($fixture['message'], array_map(base64_decode(...), $fixture['parts']), isset($fixture['raw']) ? base64_decode($fixture['raw'], true) : null);
    }

    /** @return array{message: array<string, mixed>, parts: array<string, string>, raw?: string} le message Mailpit, ses parts et, pour certaines, son mail /raw, en base64 */
    public static function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__ . "/fixtures/$name.json"), true, flags: JSON_THROW_ON_ERROR);
    }
}
