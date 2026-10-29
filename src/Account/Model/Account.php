<?php

declare(strict_types=1);

namespace Apimail\Account\Model;

use DateTimeImmutable;

/** Une ligne de accounts ; createdAt en UTC. */
final readonly class Account
{
    /** @param 'admin'|'user'|'bot' $role */
    public function __construct(
        public string $id,
        public string $name,
        public string $role,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
