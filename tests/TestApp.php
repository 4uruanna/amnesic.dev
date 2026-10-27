<?php

declare(strict_types=1);

namespace Apimail\Tests;

use Apimail\Shared\Uuid;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

/** L'application branchée sur apimail_test (remise à neuf par défaut), et de quoi l'appeler. */
final class TestApp
{
    public readonly PDO $pdo;
    private readonly App $app;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? TestDatabase::reset();
        $this->app = require __DIR__ . '/../src/app.php';
        $this->app->getContainer()->set(PDO::class, $this->pdo);
    }

    /** Crée en base un Compte de ce rôle avec un Jeton, et renvoie le secret du Jeton. */
    public function token(string $role, ?string $name = null): string
    {
        $token = bin2hex(random_bytes(32));
        $id = Uuid::v7();
        $this->pdo->prepare('INSERT INTO accounts (id, name, role, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP(3))')
            ->execute([$id, $name ?? $role, $role]);
        $this->pdo->prepare('INSERT INTO tokens (id, account_id, name, hash, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP(3))')
            ->execute([Uuid::v7(), $id, 'test', hash('sha256', $token)]);

        return $token;
    }

    /** $body : un tableau est encodé en JSON, une chaîne part telle quelle. */
    public function request(
        string $method,
        string $path,
        ?string $token = null,
        array|string|null $body = null,
        string $contentType = 'application/json',
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path);
        if ($token !== null) {
            $request = $request->withHeader('Authorization', "Bearer $token");
        }
        if ($body !== null) {
            $request = $request->withHeader('Content-Type', $contentType);
            $request->getBody()->write(is_array($body) ? json_encode($body) : $body);
        }

        return $this->handle($request);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->app->handle($request);
    }

    /** @return array<mixed> */
    public static function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
