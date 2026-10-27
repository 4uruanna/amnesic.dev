<?php

declare(strict_types=1);

namespace Apimail\Account;

use Apimail\Account\Dto\AccountCreate;
use Apimail\Account\Model\Account;
use Apimail\Account\Model\Token;
use Apimail\Shared\Conflict;
use Apimail\Shared\NotFound;
use Apimail\Shared\Uuid;
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
}
