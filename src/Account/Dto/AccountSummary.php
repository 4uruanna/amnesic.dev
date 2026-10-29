<?php

declare(strict_types=1);

namespace Apimail\Account\Dto;

use Apimail\Account\Model\Account;
use JsonSerializable;

final readonly class AccountSummary implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public string $role,
        public string $createdAt,
    ) {
    }

    public static function fromModel(Account $account): self
    {
        return new self($account->id, $account->name, $account->role, $account->createdAt->format('Y-m-d\TH:i:s.v\Z'));
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
