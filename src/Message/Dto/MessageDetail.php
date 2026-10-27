<?php

declare(strict_types=1);

namespace Apimail\Message\Dto;

use Apimail\Message\Model\Attachment;
use Apimail\Message\Model\Message;
use Apimail\Message\Model\Recipient;
use JsonSerializable;

/** Les champs de MessageSummary sauf unread (l'appel qui le renvoie marque le Message lu), plus text, html et attachments. */
final readonly class MessageDetail implements JsonSerializable
{
    /** @param list<AttachmentInfo> $attachments */
    public function __construct(
        public MessageSummary $summary,
        public ?string $text,
        public ?string $html,
        public array $attachments,
    ) {
    }

    /**
     * @param list<Recipient> $recipients
     * @param list<Attachment> $attachments par partId
     */
    public static function fromModel(Message $message, array $recipients, array $attachments, ?string $text, ?string $html): self
    {
        return new self(MessageSummary::fromModel($message, $recipients, $attachments !== [], false), $text, $html, array_map(AttachmentInfo::fromModel(...), $attachments));
    }

    public function jsonSerialize(): array
    {
        return [...array_diff_key($this->summary->jsonSerialize(), ['unread' => null]), 'text' => $this->text, 'html' => $this->html, 'attachments' => $this->attachments];
    }
}
