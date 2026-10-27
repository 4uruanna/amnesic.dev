<?php

declare(strict_types=1);

namespace Apimail\Tests\Setup;

use Apimail\Tests\TestApp;
use Apimail\Tests\TestDatabase;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class SetupControllerTest extends TestCase
{
    public function testEmptyDatabaseMigratesWithoutToken(): void
    {
        $app = new TestApp(TestDatabase::empty());

        $response = $app->request('GET', '/v1/setup');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame(['applied' => ['001_comptes.sql', '002_messages.sql']], TestApp::json($response));
        self::assertSame(0, (int) $app->pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn());
    }

    public function testInstalledDatabaseRequiresAnAdminTokenInTheUrl(): void
    {
        $app = new TestApp(TestDatabase::empty());
        $app->request('GET', '/v1/setup');
        $admin = $app->token('admin');

        self::assertProblem($app->request('GET', '/v1/setup'), 401, 'Jeton absent.');
        self::assertProblem($app->request('GET', '/v1/setup?token=' . str_repeat('0', 64)), 401, 'Jeton invalide.');
        self::assertProblem($app->request('GET', '/v1/setup?token=' . $app->token('user')), 403, 'Rôle non autorisé.');
        // Seule route à lire le jeton dans l'URL, et elle n'accepte pas l'en-tête.
        self::assertProblem($app->request('GET', '/v1/setup', $admin), 401, 'Jeton absent.');

        $response = $app->request('GET', "/v1/setup?token=$admin");
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['applied' => []], TestApp::json($response));
    }

    private static function assertProblem(ResponseInterface $response, int $status, string $detail): void
    {
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertSame($detail, TestApp::json($response)['detail']);
    }
}
