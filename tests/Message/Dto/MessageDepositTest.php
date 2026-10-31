<?php

declare(strict_types=1);

namespace Apimail\Tests\Message\Dto;

use Apimail\Message\Dto\Address;
use Apimail\Message\Dto\AttachmentDeposit;
use Apimail\Message\Dto\MessageDeposit;
use Apimail\Shared\ValidationFailed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessageDepositTest extends TestCase
{
    private const array MINIMAL = ['from' => ['address' => 'noreply@x.tld'], 'to' => [['address' => 'ci@x.tld']]];
    private const array ATTACHMENT = ['filename' => 'a.pdf', 'contentType' => 'application/pdf', 'content' => 'SGVsbG8='];

    public function testCompleteDeposit(): void
    {
        $deposit = MessageDeposit::fromArray([
            'from' => ['address' => 'NoReply@Service.tld', 'name' => 'Service'],
            'to' => [
                ['address' => 'CI@amnesic.dev', 'name' => 'CI'],
                ['address' => 'José@amnesic.dev', 'name' => null],
                ['address' => 'ci@amnesic.dev', 'name' => 'Doublon'],
            ],
            'subject' => 'Votre code',
            'sentAt' => '2026-10-26T11:59:58.5+02:00',
            'text' => 'Code : 123456',
            'html' => '<p>Code : <b>123456</b></p>',
            'attachments' => [
                ['filename' => 'facture é.pdf', 'contentType' => 'application/pdf', 'content' => base64_encode("%PDF\x00\xff")],
                ['filename' => 'vide.txt', 'contentType' => 'text/plain; charset=utf-8', 'content' => ''],
            ],
        ]);

        self::assertSame(['noreply@service.tld', 'Service'], [$deposit->from->address, $deposit->from->name]);
        // Une adresse répétée n'est gardée qu'une fois, avec son premier name.
        self::assertSame([['ci@amnesic.dev', 'CI'], ['josé@amnesic.dev', null]], array_map(fn (Address $a) => [$a->address, $a->name], $deposit->to));
        self::assertSame('Votre code', $deposit->subject);
        self::assertSame('2026-10-26T09:59:58.500000+00:00', $deposit->sentAt?->format('Y-m-d\TH:i:s.uP'));
        self::assertSame(['Code : 123456', '<p>Code : <b>123456</b></p>'], [$deposit->text, $deposit->html]);
        self::assertSame(
            [['facture é.pdf', 'application/pdf', "%PDF\x00\xff"], ['vide.txt', 'text/plain; charset=utf-8', '']],
            array_map(fn (AttachmentDeposit $a) => [$a->filename, $a->contentType, $a->content], $deposit->attachments)
        );
    }

    public function testMinimalDeposit(): void
    {
        foreach ([self::MINIMAL, [...self::MINIMAL, 'subject' => null, 'sentAt' => null, 'text' => null, 'html' => null, 'attachments' => null]] as $data) {
            $deposit = MessageDeposit::fromArray($data);

            self::assertSame(['noreply@x.tld', null], [$deposit->from->address, $deposit->from->name]);
            self::assertSame([['ci@x.tld', null]], array_map(fn (Address $a) => [$a->address, $a->name], $deposit->to));
            self::assertSame(['', null, null, null, []], [$deposit->subject, $deposit->sentAt, $deposit->text, $deposit->html, $deposit->attachments]);
        }
    }

    public function testSentAtAcceptsAnyRfc3339Offset(): void
    {
        foreach ([
            '2026-10-26T09:59:58Z' => '2026-10-26T09:59:58.000000',
            '2026-10-26t09:59:58.123456789z' => '2026-10-26T09:59:58.123456',
            '2026-10-26T00:30:00-05:30' => '2026-10-26T06:00:00.000000',
            // Les bornes de DATETIME dans MySQL, en UTC.
            '0000-01-01T00:00:00Z' => '0000-01-01T00:00:00.000000',
            '9999-12-31T23:59:59.999Z' => '9999-12-31T23:59:59.999000',
            '9999-12-31T23:00:00+05:00' => '9999-12-31T18:00:00.000000',
        ] as $sentAt => $utc) {
            self::assertSame("$utc+00:00", MessageDeposit::fromArray([...self::MINIMAL, 'sentAt' => $sentAt])->sentAt?->format('Y-m-d\TH:i:s.uP'));
        }
    }

    /** @return iterable<string, array{array<mixed>, list<array{field: string, message: string}>}> */
    public static function invalidDeposits(): iterable
    {
        $attachment = fn (array $override) => [...self::MINIMAL, 'attachments' => [[...self::ATTACHMENT, ...$override]]];
        $tooLongAddress = str_repeat('a', 64) . '@' . str_repeat(str_repeat('b', 62) . '.', 3) . 'tld';

        yield 'vide' => [[], [['field' => 'from', 'message' => 'Champ obligatoire.'], ['field' => 'to', 'message' => 'Champ obligatoire.']]];
        yield 'cc' => [[...self::MINIMAL, 'cc' => [['address' => 'cc@x.tld']]], [['field' => 'cc', 'message' => 'Champ inconnu.']]];
        yield 'bcc' => [[...self::MINIMAL, 'bcc' => []], [['field' => 'bcc', 'message' => 'Champ inconnu.']]];
        yield 'receivedAt posé par l\'API' => [[...self::MINIMAL, 'receivedAt' => '2026-10-26T09:59:58Z'], [['field' => 'receivedAt', 'message' => 'Champ inconnu.']]];

        yield 'from pas un objet' => [[...self::MINIMAL, 'from' => 'noreply@x.tld'], [['field' => 'from', 'message' => 'Objet attendu.']]];
        yield 'from liste' => [[...self::MINIMAL, 'from' => ['noreply@x.tld']], [['field' => 'from', 'message' => 'Objet attendu.']]];
        yield 'from sans adresse' => [[...self::MINIMAL, 'from' => ['name' => 'X']], [['field' => 'from.address', 'message' => 'Champ obligatoire.']]];
        yield 'from adresse invalide' => [[...self::MINIMAL, 'from' => ['address' => 'noreply']], [['field' => 'from.address', 'message' => 'Adresse mail invalide.']]];
        yield 'from adresse de plus de 254 caractères' => [[...self::MINIMAL, 'from' => ['address' => $tooLongAddress]], [['field' => 'from.address', 'message' => 'Adresse mail invalide.']]];
        yield 'from adresse pas une chaîne' => [[...self::MINIMAL, 'from' => ['address' => 42]], [['field' => 'from.address', 'message' => 'Adresse mail invalide.']]];
        yield 'from name de plus de 255 caractères' => [[...self::MINIMAL, 'from' => ['address' => 'noreply@x.tld', 'name' => str_repeat('é', 256)]], [['field' => 'from.name', 'message' => 'Chaîne de 255 caractères au plus attendue.']]];
        yield 'from name pas une chaîne' => [[...self::MINIMAL, 'from' => ['address' => 'noreply@x.tld', 'name' => false]], [['field' => 'from.name', 'message' => 'Chaîne de 255 caractères au plus attendue.']]];
        yield 'from champ en trop' => [[...self::MINIMAL, 'from' => ['address' => 'noreply@x.tld', 'type' => 'to']], [['field' => 'from.type', 'message' => 'Champ inconnu.']]];

        yield 'to vide' => [[...self::MINIMAL, 'to' => []], [['field' => 'to', 'message' => 'Tableau de 1 à 100 adresses attendu.']]];
        yield 'to objet' => [[...self::MINIMAL, 'to' => ['address' => 'ci@x.tld']], [['field' => 'to', 'message' => 'Tableau de 1 à 100 adresses attendu.']]];
        yield 'to chaîne' => [[...self::MINIMAL, 'to' => 'ci@x.tld'], [['field' => 'to', 'message' => 'Tableau de 1 à 100 adresses attendu.']]];
        yield 'to de 101 éléments' => [[...self::MINIMAL, 'to' => array_map(fn (int $i) => ['address' => "ci$i@x.tld"], range(0, 100))], [['field' => 'to', 'message' => 'Tableau de 1 à 100 adresses attendu.']]];
        yield 'to[1] pas un objet' => [[...self::MINIMAL, 'to' => [['address' => 'ci@x.tld'], null]], [['field' => 'to[1]', 'message' => 'Objet attendu.']]];
        yield 'to[1].address invalide' => [[...self::MINIMAL, 'to' => [['address' => 'ci@x.tld'], ['address' => 'ci@']]], [['field' => 'to[1].address', 'message' => 'Adresse mail invalide.']]];
        yield 'to[0].name trop long' => [[...self::MINIMAL, 'to' => [['address' => 'ci@x.tld', 'name' => str_repeat('a', 256)]]], [['field' => 'to[0].name', 'message' => 'Chaîne de 255 caractères au plus attendue.']]];

        yield 'subject de plus de 998 caractères' => [[...self::MINIMAL, 'subject' => str_repeat('é', 999)], [['field' => 'subject', 'message' => 'Chaîne de 998 caractères au plus attendue.']]];
        yield 'subject pas une chaîne' => [[...self::MINIMAL, 'subject' => 1], [['field' => 'subject', 'message' => 'Chaîne de 998 caractères au plus attendue.']]];

        foreach (['hier', '2026-10-26', '2026-10-26 09:59:58Z', '2026-10-26T09:59:58', '2026-10-26T09:59:58+0200', '2026-02-30T00:00:00Z', '2026-10-26T25:00:00Z', '2026-10-26T09:59:58+24:00', '9999-12-31T23:00:00-05:00', '0000-01-01T00:30:00+01:00', 1761472798] as $sentAt) {
            yield "sentAt $sentAt" => [[...self::MINIMAL, 'sentAt' => $sentAt], [['field' => 'sentAt', 'message' => 'Date RFC 3339 attendue.']]];
        }

        yield 'text pas une chaîne' => [[...self::MINIMAL, 'text' => ['Code']], [['field' => 'text', 'message' => 'Chaîne attendue.']]];
        yield 'html pas une chaîne' => [[...self::MINIMAL, 'html' => 1], [['field' => 'html', 'message' => 'Chaîne attendue.']]];

        yield 'attachments objet' => [[...self::MINIMAL, 'attachments' => ['0' => self::ATTACHMENT, 'x' => self::ATTACHMENT]], [['field' => 'attachments', 'message' => 'Tableau de 100 pièces jointes au plus attendu.']]];
        yield 'attachments de 101 éléments' => [[...self::MINIMAL, 'attachments' => array_fill(0, 101, self::ATTACHMENT)], [['field' => 'attachments', 'message' => 'Tableau de 100 pièces jointes au plus attendu.']]];
        yield 'attachments[0] pas un objet' => [[...self::MINIMAL, 'attachments' => ['SGVsbG8=']], [['field' => 'attachments[0]', 'message' => 'Objet attendu.']]];
        yield 'attachments[0] vide' => [[...self::MINIMAL, 'attachments' => [[]]], [
            ['field' => 'attachments[0].filename', 'message' => 'Champ obligatoire.'],
            ['field' => 'attachments[0].contentType', 'message' => 'Champ obligatoire.'],
            ['field' => 'attachments[0].content', 'message' => 'Champ obligatoire.'],
        ]];
        yield 'attachments[0] champ en trop' => [$attachment(['size' => 5]), [['field' => 'attachments[0].size', 'message' => 'Champ inconnu.']]];

        foreach (['vide' => '', '256 caractères' => str_repeat('é', 256), 'retour à la ligne' => "a\nb.pdf", 'DEL' => "a\x7f.pdf", 'contrôle C1' => "a\u{85}.pdf", 'pas une chaîne' => 1] as $case => $filename) {
            yield "filename $case" => [$attachment(['filename' => $filename]), [['field' => 'attachments[0].filename', 'message' => 'Chaîne de 1 à 255 caractères sans caractère de contrôle attendue.']]];
        }

        foreach (['sans sous-type' => 'pdf', 'sous-type vide' => 'application/', 'espace' => 'application /pdf', 'trois parties' => 'a/b/c', '256 caractères' => 'text/' . str_repeat('a', 251), 'retour à la ligne' => "text/plain;\r\n charset=utf-8", 'pas une chaîne' => ['application/pdf']] as $case => $contentType) {
            yield "contentType $case" => [$attachment(['contentType' => $contentType]), [['field' => 'attachments[0].contentType', 'message' => 'Type type/sous-type de 255 caractères au plus, sans caractère de contrôle, attendu.']]];
        }

        foreach (['hors alphabet' => 'SGVsbG8=!', 'base64url' => '-_8=', 'espace' => 'SGVs bG8=', 'retour à la ligne' => "SGVs\r\nbG8=", 'sans padding' => 'SGVsbG8', 'padding en trop' => 'SGVsbG8==', 'pas une chaîne' => 1] as $case => $content) {
            yield "content $case" => [$attachment(['content' => $content]), [['field' => 'attachments[0].content', 'message' => 'Base64 standard attendu.']]];
        }

        yield 'toutes les erreurs d\'un coup' => [
            ['from' => ['address' => 'x'], 'to' => [['address' => 'ci@x.tld'], ['address' => 'y']], 'cc' => [], 'sentAt' => 'hier', 'attachments' => [[...self::ATTACHMENT, 'content' => '!']]],
            [
                ['field' => 'cc', 'message' => 'Champ inconnu.'],
                ['field' => 'from.address', 'message' => 'Adresse mail invalide.'],
                ['field' => 'to[1].address', 'message' => 'Adresse mail invalide.'],
                ['field' => 'sentAt', 'message' => 'Date RFC 3339 attendue.'],
                ['field' => 'attachments[0].content', 'message' => 'Base64 standard attendu.'],
            ],
        ];
    }

    /**
     * @param array<mixed> $data
     * @param list<array{field: string, message: string}> $errors
     */
    #[DataProvider('invalidDeposits')]
    public function testInvalidDeposit(array $data, array $errors): void
    {
        try {
            MessageDeposit::fromArray($data);
            self::fail('ValidationFailed attendue.');
        } catch (ValidationFailed $e) {
            self::assertSame($errors, $e->errors);
        }
    }
}
