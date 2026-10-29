<?php

declare(strict_types=1);

namespace Apimail\Account;

use Apimail\Account\Model\Account;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class AccountRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function insert(Account $account): void
    {
        $this->pdo->prepare('INSERT INTO accounts (id, name, role, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$account->id, $account->name, $account->role, $account->createdAt->format('Y-m-d H:i:s.v')]);
    }

    public function find(string $id): ?Account
    {
        $select = $this->pdo->prepare('SELECT id, name, role, created_at FROM accounts WHERE id = ?');
        $select->execute([$id]);
        $row = $select->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : self::model($row);
    }

    /** @return list<Account> triés par nom */
    public function page(int $offset, int $limit): array
    {
        $select = $this->pdo->prepare('SELECT id, name, role, created_at FROM accounts ORDER BY name LIMIT ? OFFSET ?');
        $select->bindValue(1, $limit, PDO::PARAM_INT);
        $select->bindValue(2, $offset, PDO::PARAM_INT);
        $select->execute();

        return array_map(self::model(...), $select->fetchAll(PDO::FETCH_ASSOC));
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn();
    }

    /** Les cascades emportent ses Jetons, ses Bindings et ses lectures. Faux si le Compte n'existait pas. */
    public function delete(string $id): bool
    {
        $delete = $this->pdo->prepare('DELETE FROM accounts WHERE id = ?');
        $delete->execute([$id]);

        return $delete->rowCount() > 0;
    }

    /** @param array<string, string> $row */
    private static function model(array $row): Account
    {
        return new Account($row['id'], $row['name'], $row['role'], new DateTimeImmutable($row['created_at'], new DateTimeZone('UTC')));
    }
}
