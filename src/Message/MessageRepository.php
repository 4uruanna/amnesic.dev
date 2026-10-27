<?php

declare(strict_types=1);

namespace Apimail\Message;

use Apimail\Message\Model\Attachment;
use Apimail\Message\Model\Message;
use Apimail\Message\Model\Recipient;
use PDO;

final class MessageRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param list<Recipient> $recipients
     * @param list<Attachment> $attachments
     */
    public function insert(Message $message, array $recipients, array $attachments): void
    {
        $this->pdo->prepare('INSERT INTO messages (id, from_address, from_name, subject, sent_at, received_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([
                $message->id,
                $message->fromAddress,
                $message->fromName,
                $message->subject,
                $message->sentAt?->format('Y-m-d H:i:s.v'),
                $message->receivedAt->format('Y-m-d H:i:s.v'),
            ]);
        $insert = $this->pdo->prepare('INSERT INTO recipients (message_id, address, name) VALUES (?, ?, ?)');
        foreach ($recipients as $recipient) {
            $insert->execute([$recipient->messageId, $recipient->address, $recipient->name]);
        }
        $insert = $this->pdo->prepare('INSERT INTO attachments (message_id, part_id, filename, content_type, size) VALUES (?, ?, ?, ?, ?)');
        foreach ($attachments as $attachment) {
            $insert->execute([$attachment->messageId, $attachment->partId, $attachment->filename, $attachment->contentType, $attachment->size]);
        }
    }
}
