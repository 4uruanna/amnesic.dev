<?php

declare(strict_types=1);

namespace Apimail\Tests\Account\Dto;

use Apimail\Account\Dto\BindingCreate;
use Apimail\Shared\ValidationFailed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BindingCreateTest extends TestCase
{
    public function testAddressIsLowercased(): void
    {
        self::assertSame('ci@x.tld', BindingCreate::fromArray(['address' => 'Ci@X.tld'])->address);
        self::assertSame('josé@x.tld', BindingCreate::fromArray(['address' => 'José@x.tld'])->address);
    }

    /** @return iterable<string, array{array<mixed>, list<array{field: string, message: string}>}> */
    public static function invalidInputs(): iterable
    {
        yield 'vide' => [[], [['field' => 'address', 'message' => 'Champ obligatoire.']]];
        yield 'pas une adresse' => [['address' => 'ci'], [['field' => 'address', 'message' => 'Adresse mail invalide.']]];
        yield 'pas une chaîne' => [['address' => ['ci@x.tld']], [['field' => 'address', 'message' => 'Adresse mail invalide.']]];
        yield 'plus de 254 caractères' => [['address' => str_repeat('a', 64) . '@' . str_repeat(str_repeat('b', 62) . '.', 3) . 'tld'], [['field' => 'address', 'message' => 'Adresse mail invalide.']]];
        yield 'champ en trop' => [['address' => 'ci@x.tld', 'name' => 'CI'], [['field' => 'name', 'message' => 'Champ inconnu.']]];
    }

    /** @param list<array{field: string, message: string}> $errors */
    #[DataProvider('invalidInputs')]
    public function testInvalidInput(array $data, array $errors): void
    {
        try {
            BindingCreate::fromArray($data);
            self::fail('ValidationFailed attendue.');
        } catch (ValidationFailed $e) {
            self::assertSame($errors, $e->errors);
        }
    }
}
