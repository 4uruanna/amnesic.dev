<?php

declare(strict_types=1);

namespace Apimail\Account;

use Apimail\Account\Model\Account;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;

/** 403 si le Compte connecté n'a pas l'un de ces rôles, avant que la route ne charge quoi que ce soit. */
final class RequireRole implements MiddlewareInterface
{
    /** @var list<string> */
    private readonly array $roles;

    public function __construct(string ...$roles)
    {
        $this->roles = $roles;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var Account $account posé par Authentication */
        $account = $request->getAttribute('account');
        if (!in_array($account->role, $this->roles, true)) {
            throw new HttpForbiddenException($request, 'Rôle non autorisé.');
        }

        return $handler->handle($request);
    }
}
