<?php

declare(strict_types=1);

namespace Apimail\Account\Dto;

use Apimail\Shared\ValidationFailed;

/** Entrée de POST /v1/accounts. */
final readonly class AccountCreate
{
    /** @param 'admin'|'user'|'bot' $role */
    private function __construct(
        public string $name,
        public string $role,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $errors = [];
        foreach (array_diff(array_keys($data), ['name', 'role']) as $field) {
            $errors[] = ['field' => (string) $field, 'message' => 'Champ inconnu.'];
        }
        $name = $data['name'] ?? null;
        if ($name === null) {
            $errors[] = ['field' => 'name', 'message' => 'Champ obligatoire.'];
        } elseif (!is_string($name) || $name === '' || mb_strlen($name) > 100) {
            $errors[] = ['field' => 'name', 'message' => 'Chaîne de 1 à 100 caractères attendue.'];
        }
        $role = $data['role'] ?? null;
        if ($role === null) {
            $errors[] = ['field' => 'role', 'message' => 'Champ obligatoire.'];
        } elseif (!in_array($role, ['admin', 'user', 'bot'], true)) {
            $errors[] = ['field' => 'role', 'message' => 'admin, user ou bot attendu.'];
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return new self($name, $role);
    }
}
