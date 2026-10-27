<?php

declare(strict_types=1);

namespace Apimail\Tests\Account\Dto;

use Apimail\Account\Dto\AccountCreate;
use Apimail\Shared\ValidationFailed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AccountCreateTest extends TestCase
{
    public function testValidInput(): void
    {
        $input = AccountCreate::fromArray(['name' => str_repeat('é', 100), 'role' => 'bot']);

        self::assertSame(str_repeat('é', 100), $input->name);
        self::assertSame('bot', $input->role);
    }

    /** @return iterable<string, array{array<mixed>, list<array{field: string, message: string}>}> */
    public static function invalidInputs(): iterable
    {
        yield 'vide' => [[], [
            ['field' => 'name', 'message' => 'Champ obligatoire.'],
            ['field' => 'role', 'message' => 'Champ obligatoire.'],
        ]];
        yield 'nom vide' => [['name' => '', 'role' => 'user'], [['field' => 'name', 'message' => 'Chaîne de 1 à 100 caractères attendue.']]];
        yield 'nom trop long' => [['name' => str_repeat('a', 101), 'role' => 'user'], [['field' => 'name', 'message' => 'Chaîne de 1 à 100 caractères attendue.']]];
        yield 'nom pas une chaîne' => [['name' => 42, 'role' => 'user'], [['field' => 'name', 'message' => 'Chaîne de 1 à 100 caractères attendue.']]];
        yield 'rôle inconnu' => [['name' => 'ci', 'role' => 'relay'], [['field' => 'role', 'message' => 'admin, user ou bot attendu.']]];
        yield 'champ en trop' => [['name' => 'ci', 'role' => 'user', 'password' => 'x'], [['field' => 'password', 'message' => 'Champ inconnu.']]];
        yield 'toutes les erreurs d\'un coup' => [['role' => null, 'x' => 1], [
            ['field' => 'x', 'message' => 'Champ inconnu.'],
            ['field' => 'name', 'message' => 'Champ obligatoire.'],
            ['field' => 'role', 'message' => 'Champ obligatoire.'],
        ]];
    }

    /** @param list<array{field: string, message: string}> $errors */
    #[DataProvider('invalidInputs')]
    public function testInvalidInput(array $data, array $errors): void
    {
        try {
            AccountCreate::fromArray($data);
            self::fail('ValidationFailed attendue.');
        } catch (ValidationFailed $e) {
            self::assertSame($errors, $e->errors);
        }
    }
}
