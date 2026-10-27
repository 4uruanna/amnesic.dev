<?php

declare(strict_types=1);

namespace Apimail\Tests\Account\Dto;

use Apimail\Account\Dto\TokenCreate;
use Apimail\Shared\ValidationFailed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TokenCreateTest extends TestCase
{
    public function testValidInput(): void
    {
        self::assertSame(str_repeat('é', 100), TokenCreate::fromArray(['name' => str_repeat('é', 100)])->name);
    }

    /** @return iterable<string, array{array<mixed>, list<array{field: string, message: string}>}> */
    public static function invalidInputs(): iterable
    {
        yield 'vide' => [[], [['field' => 'name', 'message' => 'Champ obligatoire.']]];
        yield 'nom vide' => [['name' => ''], [['field' => 'name', 'message' => 'Chaîne de 1 à 100 caractères attendue.']]];
        yield 'nom trop long' => [['name' => str_repeat('a', 101)], [['field' => 'name', 'message' => 'Chaîne de 1 à 100 caractères attendue.']]];
        yield 'champ en trop' => [['name' => 'setup', 'token' => 'x'], [['field' => 'token', 'message' => 'Champ inconnu.']]];
    }

    /** @param list<array{field: string, message: string}> $errors */
    #[DataProvider('invalidInputs')]
    public function testInvalidInput(array $data, array $errors): void
    {
        try {
            TokenCreate::fromArray($data);
            self::fail('ValidationFailed attendue.');
        } catch (ValidationFailed $e) {
            self::assertSame($errors, $e->errors);
        }
    }
}
