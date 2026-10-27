<?php

declare(strict_types=1);

namespace Apimail\Message\Dto;

use Apimail\Shared\MailAddress;
use Apimail\Shared\ValidationFailed;
use JsonSerializable;

/** Une adresse de from ou de to, en entrée (fromArray la valide et la met en minuscules) comme en sortie (MessageSummary). */
final readonly class Address implements JsonSerializable
{
    public function __construct(
        public string $address,
        public ?string $name,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $errors = [];
        foreach (array_diff(array_keys($data), ['address', 'name']) as $field) {
            $errors[] = ['field' => (string) $field, 'message' => 'Champ inconnu.'];
        }
        $address = MailAddress::normalize($data['address'] ?? null);
        if (!isset($data['address'])) {
            $errors[] = ['field' => 'address', 'message' => 'Champ obligatoire.'];
        } elseif ($address === null) {
            $errors[] = ['field' => 'address', 'message' => 'Adresse mail invalide.'];
        }
        $name = $data['name'] ?? null;
        if ($name !== null && (!is_string($name) || mb_strlen($name) > 255)) {
            $errors[] = ['field' => 'name', 'message' => 'Chaîne de 255 caractères au plus attendue.'];
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return new self($address, $name);
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
