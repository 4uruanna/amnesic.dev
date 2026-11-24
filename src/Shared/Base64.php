<?php

declare(strict_types=1);

namespace Apimail\Shared;

/** Base64 standard strict, des champs content et raw du dépôt (api.md § DTO). */
final class Base64
{
    /** La valeur décodée, ou null si ce n'est pas une chaîne en base64 standard strict. */
    public static function decode(mixed $value): ?string
    {
        // base64_decode(…, true) tolère les blancs et un padding absent : l'alphabet et la longueur sont vérifiés avant.
        if (!is_string($value) || strlen($value) % 4 !== 0 || preg_match('/[^A-Za-z0-9+\/=]/', $value) !== 0) {
            return null;
        }
        $decoded = base64_decode($value, true);

        return $decoded === false ? null : $decoded;
    }
}
