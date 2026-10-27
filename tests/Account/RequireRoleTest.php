<?php

declare(strict_types=1);

namespace Apimail\Tests\Account;

use Apimail\Tests\TestApp;
use PHPUnit\Framework\TestCase;

final class RequireRoleTest extends TestCase
{
    public function testRoleOutsideTheRouteIs403(): void
    {
        $app = new TestApp();

        $response = $app->request('GET', '/v1/messages', $app->token('bot'));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertSame('Rôle non autorisé.', TestApp::json($response)['detail']);
    }

    public function testRolesOfTheRouteReachIt(): void
    {
        $app = new TestApp();

        self::assertSame(200, $app->request('GET', '/v1/messages', $app->token('admin'))->getStatusCode());
        self::assertSame(200, $app->request('GET', '/v1/messages', $app->token('user'))->getStatusCode());
    }
}
