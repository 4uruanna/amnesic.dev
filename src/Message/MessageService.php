<?php

declare(strict_types=1);

namespace Apimail\Message;

use Apimail\Message\Dto\Address;
use Apimail\Message\Dto\AttachmentDeposit;
use Apimail\Message\Dto\MessageDeposit;
use Apimail\Message\Model\Attachment;
use Apimail\Message\Model\Message;
use Apimail\Message\Model\Recipient;
use Apimail\Shared\Uuid;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

/** Dépôt des Messages. */
final class MessageService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly MessageRepository $messages,
        private readonly MessageFiles $files,
    ) {
    }

    public function deposit(MessageDeposit $input): Message
    {
        $message = new Message(
            Uuid::v7(),
            $input->from->address,
            $input->from->name,
            $input->subject,
            $input->sentAt,
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
        $recipients = array_map(fn (Address $to) => new Recipient($message->id, $to->address, $to->name), $input->to);
        $attachments = array_map(
            fn (int $i, AttachmentDeposit $a) => new Attachment($message->id, $i + 1, $a->filename, $a->contentType, strlen($a->content)),
            array_keys($input->attachments),
            $input->attachments,
        );

        // Fichiers d'abord : une ligne messages implique que ses fichiers existent (data-model.md § Fichiers).
        try {
            $this->files->write($message->id, $input->text, $input->html, array_map(fn (AttachmentDeposit $a) => $a->content, $input->attachments));
            $this->pdo->beginTransaction();
            $this->messages->insert($message, $recipients, $attachments);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->files->delete($message->id);
            throw $e;
        }

        return $message;
    }
}
