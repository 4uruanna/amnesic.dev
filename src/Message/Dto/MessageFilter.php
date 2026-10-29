<?php

declare(strict_types=1);

namespace Apimail\Message\Dto;

use Apimail\Shared\MailAddress;
use Apimail\Shared\Rfc3339;
use Apimail\Shared\ValidationFailed;
use DateTimeImmutable;

/** Les filtres to, since et unread de GET /v1/messages ; les autres paramètres de requête sont ignorés. */
final readonly class MessageFilter
{
    private function __construct(
        public ?string $to,
        public ?DateTimeImmutable $since,
        public ?bool $unread,
    ) {
    }

    /** @param array<string, mixed> $query */
    public static function fromArray(array $query): self
    {
        $errors = [];
        $to = isset($query['to']) ? MailAddress::normalize($query['to']) : null;
        if (isset($query['to']) && $to === null) {
            $errors[] = ['field' => 'to', 'message' => 'Adresse mail invalide.'];
        }
        $since = isset($query['since']) ? Rfc3339::parse($query['since']) : null;
        if (isset($query['since']) && $since === null) {
            $errors[] = ['field' => 'since', 'message' => 'Date RFC 3339 attendue.'];
        }
        $unread = $query['unread'] ?? null;
        if ($unread !== null && !in_array($unread, ['0', '1'], true)) {
            $errors[] = ['field' => 'unread', 'message' => '0 ou 1 attendu.'];
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Les paramètres de la requête sont invalides.');
        }

        return new self($to, $since, $unread === null ? null : $unread === '1');
    }
}
