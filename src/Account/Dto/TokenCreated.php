<?php

declare(strict_types=1);

namespace Apimail\Account\Dto;

use Apimail\Account\Model\Token;
use JsonSerializable;

/** Les champs de TokenInfo, plus le secret : la seule réponse qui le contient. */
final readonly class TokenCreated implements JsonSerializable
{
    public function __construct(
        public TokenInfo $info,
        public string $token,
    ) {
    }

    public static function fromModel(Token $token, string $secret): self
    {
        return new self(TokenInfo::fromModel($token), $secret);
    }

    public function jsonSerialize(): array
    {
        return [...$this->info->jsonSerialize(), 'token' => $this->token];
    }
}
