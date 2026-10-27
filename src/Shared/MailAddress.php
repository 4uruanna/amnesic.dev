<?php

declare(strict_types=1);

namespace Apimail\Shared;

/** Une adresse mail selon api.md § Conventions : FILTER_VALIDATE_EMAIL, 254 caractères au plus, rendue en minuscules. */
final class MailAddress
{
    /** L'adresse en minuscules, ou null si ce n'en est pas une valide. */
    public static function normalize(mixed $value): ?string
    {
        return is_string($value) && mb_strlen($value) <= 254 && filter_var($value, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) !== false
            ? mb_strtolower($value)
            : null;
    }
}
