<?php

declare(strict_types=1);

namespace Apimail\Account\Dto;

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
        $address = $data['address'] ?? null;
        if ($address === null) {
            $errors[] = ['field' => 'address', 'message' => 'Champ obligatoire.'];
        } elseif (!is_string($address) || mb_strlen($address) > 254 || filter_var($address, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) === false) {
            $errors[] = ['field' => 'address', 'message' => 'Adresse mail invalide.'];
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return new self(mb_strtolower($address));
    }
}
