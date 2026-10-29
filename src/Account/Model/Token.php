<?php

declare(strict_types=1);

namespace Apimail\Account\Model;

use DateTimeImmutable;

/** Une ligne de tokens : l'empreinte SHA-256 du Jeton, jamais le secret ; createdAt en UTC. */
final readonly class Token
{
    public function __construct(
        public string $id,
        public string $accountId,
        public string $name,
        public string $hash,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
