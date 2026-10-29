<?php

declare(strict_types=1);

namespace Apimail\Shared;

use DateTimeImmutable;
use DateTimeZone;

/** Une date en entrée selon api.md § Conventions : RFC 3339, tout décalage horaire accepté. */
final class Rfc3339
{
    /** La date ramenée en UTC, ou null si elle est invalide. */
    public static function parse(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || preg_match('/\A(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})(?:\.(\d+))?(Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)\z/i', $value, $m) !== 1) {
            return null;
        }
        // createFromFormat lit 6 décimales au plus, et signale un 30 février ou un 25 h par un avertissement.
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u P', sprintf('%s %s.%s %s', $m[1], $m[2], substr(str_pad($m[3], 6, '0'), 0, 6), strtoupper($m[4])));

        return $date === false || DateTimeImmutable::getLastErrors() !== false ? null : $date->setTimezone(new DateTimeZone('UTC'));
    }
}
