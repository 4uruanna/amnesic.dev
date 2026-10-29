<?php

declare(strict_types=1);

namespace Apimail\Setup;

use Apimail\Account\Authentication;
use Apimail\Shared\Migrator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpUnauthorizedException;

/** GET /v1/setup : applique les migrations ; sans jeton tant que la base est vide, sinon jeton admin en ?token=. */
final class SetupController
{
    public function __construct(
        private readonly Migrator $migrator,
        private readonly Authentication $authentication,
    ) {
    }

    public function migrate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->migrator->isInstalled()) {
            // Seule route à lire le jeton dans l'URL ; l'en-tête Authorization n'y compte pas.
            $token = $request->getQueryParams()['token'] ?? '';
            if ($token === '') {
                throw new HttpUnauthorizedException($request, 'Jeton absent.');
            }
            if ($this->authentication->authenticate($request, is_string($token) ? $token : '')->role !== 'admin') {
                throw new HttpForbiddenException($request, 'Rôle non autorisé.');
            }
        }

        $response->getBody()->write(json_encode(['applied' => $this->migrator->migrate()]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
