<?php

declare(strict_types=1);

namespace Apimail\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class AppTest extends TestCase
{
    public function testUnknownRouteIs404ProblemJson(): void
    {
        $response = self::request(require __DIR__ . '/../src/app.php', 'GET', '/v1/nope');

        self::assertProblem($response, 404, 'Not Found');
    }

    public function testWrongMethodIs405WithAllow(): void
    {
        $app = require __DIR__ . '/../src/app.php';
        $app->get('/test', fn ($request, $response) => $response);

        $response = self::request($app, 'POST', '/test');

        self::assertProblem($response, 405, 'Method Not Allowed');
        self::assertSame('GET', $response->getHeaderLine('Allow'));
    }

    public function testUnexpectedErrorIs500WithoutDetails(): void
    {
        $app = require __DIR__ . '/../src/app.php';
        $app->get('/boom', fn () => throw new RuntimeException('secret'));
        $this->expectErrorLog();

        $response = self::request($app, 'GET', '/boom');

        self::assertProblem($response, 500, 'Internal Server Error');
        self::assertStringNotContainsString('secret', (string) $response->getBody());
    }

    private static function request(App $app, string $method, string $path): ResponseInterface
    {
        return $app->handle((new ServerRequestFactory())->createServerRequest($method, $path));
    }

    private static function assertProblem(ResponseInterface $response, int $status, string $title): void
    {
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        $body = json_decode((string) $response->getBody(), true);
        self::assertSame('about:blank', $body['type']);
        self::assertSame($title, $body['title']);
        self::assertSame($status, $body['status']);
        self::assertSame('max-age=31536000', $response->getHeaderLine('Strict-Transport-Security'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }
}
