<?php

declare(strict_types=1);

namespace Apimail\Tests\Message\Dto;

use Apimail\Message\Dto\MessageFilter;
use Apimail\Shared\ValidationFailed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessageFilterTest extends TestCase
{
    public function testAbsentParametersFilterNothing(): void
    {
        $filter = MessageFilter::fromArray(['offset' => '0', 'inconnu' => 'x']);

        self::assertSame([null, null, null], [$filter->to, $filter->since, $filter->unread]);
    }

    public function testValidParameters(): void
    {
        $filter = MessageFilter::fromArray(['to' => 'José@X.tld', 'since' => '2026-10-26T11:59:58.5+02:00', 'unread' => '1']);

        self::assertSame('josé@x.tld', $filter->to);
        self::assertSame('2026-10-26T09:59:58.500000+00:00', $filter->since?->format('Y-m-d\TH:i:s.uP'));
        self::assertTrue($filter->unread);
        self::assertFalse(MessageFilter::fromArray(['unread' => '0'])->unread);
    }

    /** @return iterable<string, array{array<mixed>, list<array{field: string, message: string}>}> */
    public static function invalidQueries(): iterable
    {
        foreach (['' => '', 'ci' => 'ci', 'tableau' => ['ci@x.tld']] as $case => $to) {
            yield "to $case" => [['to' => $to], [['field' => 'to', 'message' => 'Adresse mail invalide.']]];
        }
        foreach (['hier', '', '2026-10-26', '2026-10-26T09:59:58', '2026-02-30T00:00:00Z'] as $since) {
            yield "since $since" => [['since' => $since], [['field' => 'since', 'message' => 'Date RFC 3339 attendue.']]];
        }
        foreach (['2', '', 'true', '01', ['1']] as $unread) {
            yield 'unread ' . json_encode($unread) => [['unread' => $unread], [['field' => 'unread', 'message' => '0 ou 1 attendu.']]];
        }
        yield 'toutes les erreurs d\'un coup' => [['to' => 'ci', 'since' => 'hier', 'unread' => '2'], [
            ['field' => 'to', 'message' => 'Adresse mail invalide.'],
            ['field' => 'since', 'message' => 'Date RFC 3339 attendue.'],
            ['field' => 'unread', 'message' => '0 ou 1 attendu.'],
        ]];
    }

    /**
     * @param array<mixed> $query
     * @param list<array{field: string, message: string}> $errors
     */
    #[DataProvider('invalidQueries')]
    public function testInvalidQuery(array $query, array $errors): void
    {
        try {
            MessageFilter::fromArray($query);
            self::fail('ValidationFailed attendue.');
        } catch (ValidationFailed $e) {
            self::assertSame($errors, $e->errors);
            self::assertSame('Les paramètres de la requête sont invalides.', $e->getMessage());
        }
    }
}
