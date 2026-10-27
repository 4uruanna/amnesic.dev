<?php

declare(strict_types=1);

namespace Apimail\Message\Model;

/** Une ligne de attachments ; size est la taille décodée, en octets. */
final readonly class Attachment
{
    public function __construct(
        public string $messageId,
        public int $partId,
        public string $filename,
        public string $contentType,
        public int $size,
    ) {
    }
}
