<?php

declare(strict_types=1);

namespace Apimail\Tests\Shared;

use Apimail\Shared\Uuid;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    public function testFormatVersionAndVariant(): void
    {
        // 8-4-4-4-12 en minuscules, version 7, variante RFC 9562 (10xx).
        self::assertMatchesRegularExpression(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/',
            Uuid::v7()
        );
    }

    public function testTimestampIsCurrentMillisecond(): void
    {
        $before = (int) (microtime(true) * 1000);
        $timestamp = hexdec(str_replace('-', '', substr(Uuid::v7(), 0, 13)));
        $after = (int) (microtime(true) * 1000);

        self::assertGreaterThanOrEqual($before, $timestamp);
        self::assertLessThanOrEqual($after + 1, $timestamp);
    }

    public function testSuccessiveValuesIncrease(): void
    {
        // Beaucoup d'appels tombent dans la même milliseconde : le compteur doit garder l'ordre.
        $previous = Uuid::v7();
        for ($i = 0; $i < 10000; $i++) {
            $next = Uuid::v7();
            self::assertGreaterThan($previous, $next);
            $previous = $next;
        }
    }
}
