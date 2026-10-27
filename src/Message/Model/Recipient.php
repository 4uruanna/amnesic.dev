<?php

declare(strict_types=1);

namespace Apimail\Message\Model;

/** Une ligne de recipients : un Destinataire (To) d'un Message, adresse en minuscules. */
final readonly class Recipient
{
    public function __construct(
        public string $messageId,
        public string $address,
        public ?string $name,
    ) {
    }
}
