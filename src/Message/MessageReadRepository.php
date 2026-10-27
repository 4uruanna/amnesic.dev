<?php

declare(strict_types=1);

namespace Apimail\Message;

use PDO;

final class MessageReadRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** Sans effet si le Compte a déjà lu le Message (data-model.md § Requêtes clés). */
    public function insert(string $accountId, string $messageId): void
    {
        $this->pdo->prepare('INSERT IGNORE INTO message_reads (account_id, message_id) VALUES (?, ?)')->execute([$accountId, $messageId]);
    }
}
