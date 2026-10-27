<?php

declare(strict_types=1);

namespace Apimail\Shared;

use RuntimeException;

/** 422, avec chaque erreur dans errors. */
final class ValidationFailed extends RuntimeException
{
    /** @param list<array{field: string, message: string}> $errors */
    public function __construct(public readonly array $errors, string $detail = 'Le corps de la requête est invalide.')
    {
        parent::__construct($detail);
    }
}
