<?php

declare(strict_types=1);

namespace Apimail\Account\Dto;

use Apimail\Shared\ValidationFailed;

/** Entrée de POST /v1/accounts/{id}/tokens. */
final readonly class TokenCreate
{
    private function __construct(public string $name)
    {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $errors = [];
        foreach (array_diff(array_keys($data), ['name']) as $field) {
            $errors[] = ['field' => (string) $field, 'message' => 'Champ inconnu.'];
        }
        $name = $data['name'] ?? null;
        if ($name === null) {
            $errors[] = ['field' => 'name', 'message' => 'Champ obligatoire.'];
        } elseif (!is_string($name) || $name === '' || mb_strlen($name) > 100) {
            $errors[] = ['field' => 'name', 'message' => 'Chaîne de 1 à 100 caractères attendue.'];
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return new self($name);
    }
}
