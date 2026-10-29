<?php

declare(strict_types=1);

namespace Apimail\Account\Dto;

use Apimail\Shared\MailAddress;
use Apimail\Shared\ValidationFailed;

/** Entrée de POST /v1/accounts/{id}/bindings ; l'adresse ressort en minuscules. */
final readonly class BindingCreate
{
    private function __construct(public string $address)
    {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $errors = [];
        foreach (array_diff(array_keys($data), ['address']) as $field) {
            $errors[] = ['field' => (string) $field, 'message' => 'Champ inconnu.'];
        }
        $address = MailAddress::normalize($data['address'] ?? null);
        if (!isset($data['address'])) {
            $errors[] = ['field' => 'address', 'message' => 'Champ obligatoire.'];
        } elseif ($address === null) {
            $errors[] = ['field' => 'address', 'message' => 'Adresse mail invalide.'];
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return new self($address);
    }
}
