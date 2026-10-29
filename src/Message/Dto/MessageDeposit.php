<?php

declare(strict_types=1);

namespace Apimail\Message\Dto;

use Apimail\Shared\Rfc3339;
use Apimail\Shared\ValidationFailed;
use DateTimeImmutable;

/** Entrée de POST /v1/messages : le mail déjà découpé par le Relais (ADR 0006). */
final readonly class MessageDeposit
{
    /**
     * @param list<Address> $to sans adresse répétée
     * @param list<AttachmentDeposit> $attachments dans l'ordre des partId
     */
    private function __construct(
        public Address $from,
        public array $to,
        public string $subject,
        public ?DateTimeImmutable $sentAt,
        public ?string $text,
        public ?string $html,
        public array $attachments,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $errors = [];
        foreach (array_diff(array_keys($data), ['from', 'to', 'subject', 'sentAt', 'text', 'html', 'attachments']) as $field) {
            $errors[] = ['field' => (string) $field, 'message' => 'Champ inconnu.'];
        }

        $from = null;
        if (!isset($data['from'])) {
            $errors[] = ['field' => 'from', 'message' => 'Champ obligatoire.'];
        } else {
            $from = self::nested(Address::fromArray(...), $data['from'], 'from', $errors);
        }

        $to = [];
        if (!isset($data['to'])) {
            $errors[] = ['field' => 'to', 'message' => 'Champ obligatoire.'];
        } elseif (!is_array($data['to']) || $data['to'] === [] || !array_is_list($data['to']) || count($data['to']) > 100) {
            $errors[] = ['field' => 'to', 'message' => 'Tableau de 1 à 100 adresses attendu.'];
        } else {
            foreach ($data['to'] as $i => $item) {
                $address = self::nested(Address::fromArray(...), $item, "to[$i]", $errors);
                if ($address !== null) {
                    $to[$address->address] ??= $address; // une adresse répétée garde son premier name
                }
            }
        }

        $subject = $data['subject'] ?? '';
        if (!is_string($subject) || mb_strlen($subject) > 998) {
            $errors[] = ['field' => 'subject', 'message' => 'Chaîne de 998 caractères au plus attendue.'];
        }
        $sentAt = isset($data['sentAt']) ? Rfc3339::parse($data['sentAt']) : null;
        if (isset($data['sentAt']) && $sentAt === null) {
            $errors[] = ['field' => 'sentAt', 'message' => 'Date RFC 3339 attendue.'];
        }
        foreach (['text', 'html'] as $field) {
            if (isset($data[$field]) && !is_string($data[$field])) {
                $errors[] = ['field' => $field, 'message' => 'Chaîne attendue.'];
            }
        }

        $attachments = [];
        $items = $data['attachments'] ?? [];
        if (!is_array($items) || !array_is_list($items) || count($items) > 100) {
            $errors[] = ['field' => 'attachments', 'message' => 'Tableau de 100 pièces jointes au plus attendu.'];
        } else {
            foreach ($items as $i => $item) {
                $attachments[] = self::nested(AttachmentDeposit::fromArray(...), $item, "attachments[$i]", $errors);
            }
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return new self($from, array_values($to), $subject, $sentAt, $data['text'] ?? null, $data['html'] ?? null, $attachments);
    }

    /**
     * Le DTO imbriqué que construit $fromArray, ou null après avoir ajouté ses erreurs, préfixées de $path.
     *
     * @param list<array{field: string, message: string}> $errors
     */
    private static function nested(callable $fromArray, mixed $data, string $path, array &$errors): mixed
    {
        // json_decode rend {} en [] : seul un tableau non vide trahit une liste.
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            $errors[] = ['field' => $path, 'message' => 'Objet attendu.'];

            return null;
        }
        try {
            return $fromArray($data);
        } catch (ValidationFailed $e) {
            foreach ($e->errors as $error) {
                $errors[] = ['field' => "$path.{$error['field']}", 'message' => $error['message']];
            }

            return null;
        }
    }
}
