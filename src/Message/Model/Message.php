<?php

declare(strict_types=1);

namespace Apimail\Message\Model;

use DateTimeImmutable;

/** Une ligne de messages ; sentAt et receivedAt en UTC. */
final readonly class Message
{
    public function __construct(
        public string $id,
        public string $fromAddress,
        public ?string $fromName,
        public string $subject,
        public ?DateTimeImmutable $sentAt,
        public DateTimeImmutable $receivedAt,
    ) {
    }
}
