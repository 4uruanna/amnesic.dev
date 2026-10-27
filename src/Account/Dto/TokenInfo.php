<?php

declare(strict_types=1);

namespace Apimail\Account\Dto;

use Apimail\Account\Model\Token;
use JsonSerializable;

/** Un Jeton sans son secret. */
final readonly class TokenInfo implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public string $createdAt,
    ) {
    }

    public static function fromModel(Token $token): self
    {
        return new self($token->id, $token->name, $token->createdAt->format('Y-m-d\TH:i:s.v\Z'));
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
