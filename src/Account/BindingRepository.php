<?php

declare(strict_types=1);

namespace Apimail\Account;

use PDO;

final class BindingRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<string> les adresses liées au Compte, triées */
    public function addressesOf(string $accountId): array
    {
        $select = $this->pdo->prepare('SELECT address FROM bindings WHERE account_id = ? ORDER BY address');
        $select->execute([$accountId]);

        return $select->fetchAll(PDO::FETCH_COLUMN);
    }
}
