<?php

declare(strict_types=1);

namespace Apimail\Shared;

use JsonSerializable;

/** Une page de liste : { items, total, offset, limit } (api.md § Conventions). */
final readonly class Page implements JsonSerializable
{
    /** @param list<JsonSerializable> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $offset,
        public int $limit,
    ) {
    }

    /**
     * offset (défaut 0) et limit (défaut 20, de 1 à 100) des paramètres de requête, sinon 422.
     *
     * @param array<string, mixed> $query
     * @return array{int, int}
     */
    public static function bounds(array $query): array
    {
        $errors = [];
        $offset = filter_var($query['offset'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($offset === false) {
            $errors[] = ['field' => 'offset', 'message' => 'Entier positif ou nul attendu.'];
        }
        $limit = filter_var($query['limit'] ?? 20, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($limit === false) {
            $errors[] = ['field' => 'limit', 'message' => 'Entier de 1 à 100 attendu.'];
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Les paramètres de la requête sont invalides.');
        }

        return [$offset, $limit];
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
