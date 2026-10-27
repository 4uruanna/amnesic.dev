<?php

declare(strict_types=1);

namespace Apimail\Shared;

/** UUIDv7 (RFC 9562) : 48 bits de millisecondes, un compteur de 12 bits, 62 bits aléatoires. */
final class Uuid
{
    private static int $milliseconds = 0;
    private static int $counter = 0;

    public static function v7(): string
    {
        // Dans une même milliseconde (ou si l'horloge recule), le compteur garde les valeurs croissantes.
        $now = (int) (microtime(true) * 1000);
        if ($now > self::$milliseconds) {
            self::$milliseconds = $now;
            self::$counter = random_int(0, 0x7ff); // bit de poids fort à 0 : de la marge pour incrémenter
        } elseif (++self::$counter > 0xfff) {
            self::$milliseconds++;
            self::$counter = 0;
        }

        $random = random_bytes(8);
        $random[0] = chr(ord($random[0]) & 0x3f | 0x80); // variante 10xx
        $hex = sprintf('%012x7%03x', self::$milliseconds, self::$counter) . bin2hex($random);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-'
            . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
