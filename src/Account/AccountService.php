<?php

declare(strict_types=1);

namespace Apimail\Account;

use Apimail\Account\Dto\AccountCreate;
use Apimail\Account\Dto\BindingCreate;
use Apimail\Account\Dto\TokenCreate;
use Apimail\Account\Model\Account;
use Apimail\Account\Model\Token;
use Apimail\Shared\Conflict;
use Apimail\Shared\NotFound;
use Apimail\Shared\Uuid;
use Apimail\Shared\ValidationFailed;
use DateTimeImmutable;
use DateTimeZone;
use PDOException;

/** Comptes, Bindings et Jetons, gérés par un admin. */
final class AccountService
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly BindingRepository $bindings,
        private readonly TokenRepository $tokens,
    ) {
    }

    public function create(AccountCreate $input): Account
    {
        $account = new Account(Uuid::v7(), $input->name, $input->role, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        try {
            $this->accounts->insert($account);
        } catch (PDOException $e) {
            // Clé unique accounts_name : vérifier avant d'insérer laisserait passer deux créations simultanées.
            throw ($e->errorInfo[1] ?? null) === 1062 ? new Conflict('Nom de Compte déjà pris.') : $e;
        }

        return $account;
    }

    /** @return array{list<Account>, int} la page de Comptes triés par nom, et leur nombre total */
    public function page(int $offset, int $limit): array
    {
        return [$this->accounts->page($offset, $limit), $this->accounts->count()];
    }

    /** @return array{Account, list<string>, list<Token>} le Compte, ses adresses triées et ses Jetons par date */
    public function detail(string $id): array
    {
        $account = $this->accounts->find($id) ?? throw new NotFound('Compte introuvable.');

        return [$account, $this->bindings->addressesOf($id), $this->tokens->tokensOf($id)];
    }

    public function delete(string $id): void
    {
        if (!$this->accounts->delete($id)) {
            throw new NotFound('Compte introuvable.');
        }
    }

    /** @return string l'adresse liée, en minuscules */
    public function bind(string $id, BindingCreate $input): string
    {
        $account = $this->accounts->find($id) ?? throw new NotFound('Compte introuvable.');
        if ($account->role === 'bot') {
            // Un bot ne lit rien : un Binding ne lui donnerait aucun accès.
            throw new ValidationFailed([['field' => '', 'message' => 'Un Compte bot ne reçoit pas de Binding.']], 'Un Compte bot ne reçoit pas de Binding.');
        }
        try {
            $this->bindings->insert($id, $input->address);
        } catch (PDOException $e) {
            throw ($e->errorInfo[1] ?? null) === 1062 ? new Conflict('Adresse déjà liée à ce Compte.') : $e;
        }

        return $input->address;
    }

    public function unbind(string $id, string $address): void
    {
        if (!$this->bindings->delete($id, mb_strtolower($address))) {
            throw new NotFound('Binding introuvable.');
        }
    }

    /** @return array{Token, string} le Jeton émis et son secret, que l'API ne pourra plus restituer */
    public function issueToken(string $id, TokenCreate $input): array
    {
        $this->accounts->find($id) ?? throw new NotFound('Compte introuvable.');
        $secret = bin2hex(random_bytes(32));
        $token = new Token(Uuid::v7(), $id, $input->name, hash('sha256', $secret), new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $this->tokens->insert($token);

        return [$token, $secret];
    }

    public function revokeToken(string $id, string $tokenId): void
    {
        if (!$this->tokens->delete($id, $tokenId)) {
            throw new NotFound('Jeton introuvable.');
        }
    }
}
