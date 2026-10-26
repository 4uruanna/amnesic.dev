<?php

declare(strict_types=1);

namespace Apimail\Account;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpUnauthorizedException;

/** Garde du groupe /v1 : Authorization: Bearer suivi de 64 caractères hexadécimaux minuscules. */
final class Authentication implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authorization = $request->getHeaderLine('Authorization');
        if ($authorization === '') {
            throw new HttpUnauthorizedException($request, 'Jeton absent.');
        }
        if (preg_match('/\ABearer [0-9a-f]{64}\z/', $authorization) !== 1) {
            throw new HttpUnauthorizedException($request, 'Jeton invalide.');
        }

        // Aucun jeton n'est encore connu : la recherche de son empreinte en base arrive en J02-2.
        throw new HttpUnauthorizedException($request, 'Jeton invalide.');
    }
}
