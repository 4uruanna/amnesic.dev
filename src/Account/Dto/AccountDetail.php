<?php

declare(strict_types=1);

namespace Apimail\Account\Dto;

use Apimail\Account\Model\Account;
use Apimail\Account\Model\Token;
use JsonSerializable;

/** Les champs d'AccountSummary, plus addresses et tokens. */
final readonly class AccountDetail implements JsonSerializable
{
    /**
     * @param list<string> $addresses
     * @param list<TokenInfo> $tokens
     */
    public function __construct(
        public AccountSummary $summary,
        public array $addresses,
        public array $tokens,
    ) {
    }

    /**
     * @param list<string> $addresses triées
     * @param list<Token> $tokens triés par createdAt
     */
    public static function fromModel(Account $account, array $addresses, array $tokens): self
    {
        return new self(AccountSummary::fromModel($account), $addresses, array_map(TokenInfo::fromModel(...), $tokens));
    }

    public function jsonSerialize(): array
    {
        return [...$this->summary->jsonSerialize(), 'addresses' => $this->addresses, 'tokens' => $this->tokens];
    }
}
