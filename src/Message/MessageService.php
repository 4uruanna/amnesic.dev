<?php

declare(strict_types=1);

namespace Apimail\Message;

use Apimail\Account\Model\Account;
use Apimail\Message\Dto\Address;
use Apimail\Message\Dto\AttachmentDeposit;
use Apimail\Message\Dto\MessageDeposit;
use Apimail\Message\Dto\MessageFilter;
use Apimail\Message\Model\Attachment;
use Apimail\Message\Model\Message;
use Apimail\Message\Model\Recipient;
use Apimail\Shared\NotFound;
use Apimail\Shared\Uuid;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

/** Dépôt des Messages, et leur lecture selon les Bindings du Compte. */
final class MessageService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly MessageRepository $messages,
        private readonly MessageReadRepository $reads,
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

    /**
     * La page des Messages que le Compte voit, et leur nombre total.
     *
     * @return array{list<array{Message, list<Recipient>, bool, bool}>, int} chaque Message avec ses Destinataires, hasAttachments et unread
     */
    public function list(Account $account, MessageFilter $filter, int $offset, int $limit): array
    {
        [$rows, $total] = $this->messages->page($account->id, self::boundTo($account), $filter->to, $filter->since, $filter->unread, $offset, $limit);
        $recipients = $this->messages->recipientsOf(array_map(fn (array $row) => $row[0]->id, $rows));

        return [array_map(fn (array $row) => [$row[0], $recipients[$row[0]->id], $row[1], $row[2]], $rows), $total];
    }

    /**
     * Le détail d'un Message que le Compte voit, qui le marque lu pour ce Compte seul.
     *
     * @return array{Message, list<Recipient>, list<Attachment>, ?string, ?string} le Message, ses Destinataires, ses pièces jointes, son text et son html
     */
    public function show(Account $account, string $id): array
    {
        $message = $this->visible($account, $id);
        $detail = [$message, $this->messages->recipientsOf([$id])[$id], $this->messages->attachmentsOf($id), $this->files->read($id, 'text'), $this->files->read($id, 'html')];
        $this->reads->insert($account->id, $id);

        return $detail;
    }

    /** Le Message, si le Compte le voit ; NotFound sinon, sans distinguer un Message inconnu d'un Message invisible. */
    private function visible(Account $account, string $id): Message
    {
        return $this->messages->find($id, self::boundTo($account)) ?? throw new NotFound('Message introuvable.');
    }

    /** Le Compte dont les Bindings bornent ce que voit $account : aucun pour un admin, qui voit tout (api.md § Authentification et permissions). */
    private static function boundTo(Account $account): ?string
    {
        return $account->role === 'admin' ? null : $account->id;
    }
}
