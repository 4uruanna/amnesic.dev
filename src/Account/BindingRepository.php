<?php

declare(strict_types=1);

namespace Apimail\Account;

use PDO;

final class BindingRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function insert(string $accountId, string $address): void
    {
        $this->pdo->prepare('INSERT INTO bindings (account_id, address) VALUES (?, ?)')->execute([$accountId, $address]);
    }

    /** Faux si ce Binding n'existait pas. */
    public function delete(string $accountId, string $address): bool
    {
        $delete = $this->pdo->prepare('DELETE FROM bindings WHERE account_id = ? AND address = ?');
        $delete->execute([$accountId, $address]);

        return $delete->rowCount() > 0;
    }

    /** @return list<string> les adresses liées au Compte, triées */
    public function addressesOf(string $accountId): array
    {
        $select = $this->pdo->prepare('SELECT address FROM bindings WHERE account_id = ? ORDER BY address');
        $select->execute([$accountId]);

        return $select->fetchAll(PDO::FETCH_COLUMN);
    }
}
