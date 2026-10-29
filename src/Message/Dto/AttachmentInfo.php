<?php

declare(strict_types=1);

namespace Apimail\Message\Dto;

use Apimail\Message\Model\Attachment;
use JsonSerializable;

/** Une pièce jointe d'un MessageDetail, sans son contenu ; size est la taille décodée. */
final readonly class AttachmentInfo implements JsonSerializable
{
    public function __construct(
        public int $partId,
        public string $filename,
        public string $contentType,
        public int $size,
    ) {
    }

    public static function fromModel(Attachment $attachment): self
    {
        return new self($attachment->partId, $attachment->filename, $attachment->contentType, $attachment->size);
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
