<?php

declare(strict_types=1);

namespace Apimail\Account;

use Apimail\Account\Model\Account;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpUnauthorizedException;

/** Garde du groupe /v1 : Authorization: Bearer d'un Jeton connu ; le Compte devient l'attribut account. */
final class Authentication implements MiddlewareInterface
{
    public function __construct(private readonly TokenRepository $tokens)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authorization = $request->getHeaderLine('Authorization');
        if ($authorization === '') {
            throw new HttpUnauthorizedException($request, 'Jeton absent.');
        }
        $token = str_starts_with($authorization, 'Bearer ') ? substr($authorization, 7) : '';

        return $handler->handle($request->withAttribute('account', $this->authenticate($request, $token)));
    }

    /** Le Compte du Jeton (64 caractères hexadécimaux minuscules), sinon 401. Sert aussi au ?token= de /v1/setup. */
    public function authenticate(ServerRequestInterface $request, string $token): Account
    {
        $account = preg_match('/\A[0-9a-f]{64}\z/', $token) === 1
            ? $this->tokens->findAccountByHash(hash('sha256', $token))
            : null;

        return $account ?? throw new HttpUnauthorizedException($request, 'Jeton invalide.');
    }
}
