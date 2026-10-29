<?php

declare(strict_types=1);

namespace Apimail\Message;

use Apimail\Message\Model\Attachment;
use Apimail\Message\Model\Message;
use Apimail\Message\Model\Recipient;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class MessageRepository
{
    /** Un Destinataire du Message m est lié au Compte du paramètre (data-model.md § Requêtes clés). */
    private const string BOUND = 'EXISTS (SELECT 1 FROM recipients r JOIN bindings b ON b.address = r.address WHERE r.message_id = m.id AND b.account_id = ?)';
    /** Le Compte du paramètre a lu le Message m. */
    private const string READ = 'EXISTS (SELECT 1 FROM message_reads mr WHERE mr.message_id = m.id AND mr.account_id = ?)';

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

    /**
     * Une page de Messages du plus récent au plus ancien, et le nombre total de ceux qui satisfont les filtres.
     *
     * @param string $reader le Compte dont unread et le filtre $unread portent les lectures
     * @param ?string $boundTo seulement les Messages dont un Destinataire est lié à ce Compte ; null : tous
     * @return array{list<array{Message, bool, bool}>, int} chaque Message avec hasAttachments et unread
     */
    public function page(string $reader, ?string $boundTo, ?string $to, ?DateTimeImmutable $since, ?bool $unread, int $offset, int $limit): array
    {
        $conditions = ['TRUE'];
        $params = [];
        if ($boundTo !== null) {
            $conditions[] = self::BOUND;
            $params[] = $boundTo;
        }
        if ($to !== null) {
            $conditions[] = 'EXISTS (SELECT 1 FROM recipients r2 WHERE r2.message_id = m.id AND r2.address = ?)';
            $params[] = $to;
        }
        if ($since !== null) {
            $conditions[] = 'm.received_at >= ?';
            $params[] = $since->format('Y-m-d H:i:s.u');
        }
        if ($unread !== null) {
            $conditions[] = ($unread ? 'NOT ' : '') . self::READ;
            $params[] = $reader;
        }
        $where = implode(' AND ', $conditions);

        $select = $this->pdo->prepare(
            'SELECT m.id, m.from_address, m.from_name, m.subject, m.sent_at, m.received_at,
                    EXISTS (SELECT 1 FROM attachments a WHERE a.message_id = m.id) AS has_attachments,
                    NOT ' . self::READ . " AS unread
             FROM messages m WHERE $where ORDER BY m.received_at DESC, m.id DESC LIMIT ? OFFSET ?"
        );
        foreach ([$reader, ...$params, $limit, $offset] as $i => $value) {
            $select->bindValue($i + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $select->execute();
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM messages m WHERE $where");
        $count->execute($params);

        return [
            array_map(fn (array $row) => [self::message($row), (bool) $row['has_attachments'], (bool) $row['unread']], $select->fetchAll(PDO::FETCH_ASSOC)),
            (int) $count->fetchColumn(),
        ];
    }

    /** Le Message, s'il existe et, avec $boundTo, si l'un de ses Destinataires est lié à ce Compte. */
    public function find(string $id, ?string $boundTo): ?Message
    {
        $select = $this->pdo->prepare('SELECT m.id, m.from_address, m.from_name, m.subject, m.sent_at, m.received_at FROM messages m WHERE m.id = ?' . ($boundTo === null ? '' : ' AND ' . self::BOUND));
        $select->execute($boundTo === null ? [$id] : [$id, $boundTo]);
        $row = $select->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : self::message($row);
    }

    /**
     * @param list<string> $ids
     * @return array<string, list<Recipient>> les Destinataires de chaque Message, triés par adresse
     */
    public function recipientsOf(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $select = $this->pdo->prepare('SELECT message_id, address, name FROM recipients WHERE message_id IN (' . implode(', ', array_fill(0, count($ids), '?')) . ') ORDER BY address');
        $select->execute($ids);
        $recipients = array_fill_keys($ids, []);
        foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $recipients[$row['message_id']][] = new Recipient($row['message_id'], $row['address'], $row['name']);
        }

        return $recipients;
    }

    /** @return list<string> au plus 500 Messages reçus il y a plus de 30 jours (data-model.md § Requêtes clés) */
    public function expired(): array
    {
        return $this->pdo->query('SELECT id FROM messages WHERE received_at < UTC_TIMESTAMP(3) - INTERVAL 30 DAY LIMIT 500')->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Les cascades emportent ses Destinataires, ses pièces jointes et ses lectures. */
    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM messages WHERE id = ?')->execute([$id]);
    }

    /** @return list<Attachment> les pièces jointes du Message, par partId */
    public function attachmentsOf(string $id): array
    {
        $select = $this->pdo->prepare('SELECT message_id, part_id, filename, content_type, size FROM attachments WHERE message_id = ? ORDER BY part_id');
        $select->execute([$id]);

        return array_map(
            fn (array $row) => new Attachment($row['message_id'], $row['part_id'], $row['filename'], $row['content_type'], $row['size']),
            $select->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /** @param array<string, ?string> $row */
    private static function message(array $row): Message
    {
        $utc = new DateTimeZone('UTC');

        return new Message(
            $row['id'],
            $row['from_address'],
            $row['from_name'],
            $row['subject'],
            $row['sent_at'] === null ? null : new DateTimeImmutable($row['sent_at'], $utc),
            new DateTimeImmutable($row['received_at'], $utc),
        );
    }
}
