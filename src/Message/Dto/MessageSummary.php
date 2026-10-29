<?php

declare(strict_types=1);

namespace Apimail\Message\Dto;

use Apimail\Message\Model\Message;
use Apimail\Message\Model\Recipient;
use JsonSerializable;

/** Un élément de GET /v1/messages ; unread est celui du Compte appelant. */
final readonly class MessageSummary implements JsonSerializable
{
    /** @param list<Address> $to */
    public function __construct(
        public string $id,
        public Address $from,
        public array $to,
        public string $subject,
        public ?string $sentAt,
        public string $receivedAt,
        public bool $hasAttachments,
        public bool $unread,
    ) {
    }

    /** @param list<Recipient> $recipients */
    public static function fromModel(Message $message, array $recipients, bool $hasAttachments, bool $unread): self
    {
        return new self(
            $message->id,
            new Address($message->fromAddress, $message->fromName),
            array_map(fn (Recipient $recipient) => new Address($recipient->address, $recipient->name), $recipients),
            $message->subject,
            $message->sentAt?->format('Y-m-d\TH:i:s.v\Z'),
            $message->receivedAt->format('Y-m-d\TH:i:s.v\Z'),
            $hasAttachments,
            $unread,
        );
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
