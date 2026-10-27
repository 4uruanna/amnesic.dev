<?php

declare(strict_types=1);

namespace Apimail\Account;

use Apimail\Account\Model\Account;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class TokenRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** Le Compte dont un Jeton a cette empreinte SHA-256. */
    public function findAccountByHash(string $hash): ?Account
    {
        $select = $this->pdo->prepare(
            'SELECT a.id, a.name, a.role, a.created_at FROM tokens t JOIN accounts a ON a.id = t.account_id WHERE t.hash = ?'
        );
        $select->execute([$hash]);
        $row = $select->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new Account($row['id'], $row['name'], $row['role'], new DateTimeImmutable($row['created_at'], new DateTimeZone('UTC')));
    }
}
