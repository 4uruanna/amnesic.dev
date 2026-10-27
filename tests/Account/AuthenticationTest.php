<?php

declare(strict_types=1);

namespace Apimail\Tests\Account;

use Apimail\Tests\TestApp;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

final class AuthenticationTest extends TestCase
{
    private TestApp $app;

    protected function setUp(): void
    {
        $this->app = new TestApp();
    }

    public function testMissingHeaderIsJetonAbsent(): void
    {
        self::assertUnauthorized($this->get('/v1/messages'), 'Jeton absent.');
    }

    /** @return iterable<string, array{string}> */
    public static function invalidHeaders(): iterable
    {
        yield 'Basic' => ['Basic ' . base64_encode('admin:secret')];
        yield 'trop court' => ['Bearer ' . str_repeat('a', 63)];
        yield 'majuscules' => ['Bearer ' . str_repeat('A', 64)];
        yield 'bien formé mais inconnu' => ['Bearer ' . str_repeat('0', 64)];
    }

    #[DataProvider('invalidHeaders')]
    public function testInvalidHeaderIsJetonInvalide(string $authorization): void
    {
        self::assertUnauthorized($this->get('/v1/messages', $authorization), 'Jeton invalide.');
    }

    public function testKnownTokenReachesTheRoute(): void
    {
        self::assertSame(200, $this->get('/v1/messages', 'Bearer ' . $this->app->token('admin'))->getStatusCode());
    }

    public function testAdminCreatedByTheInfraSqlIsAccepted(): void
    {
        // La requête de docs/infra.md § Créer un admin, telle quelle.
        preg_match('/^## Créer un admin$.*?^```sql\n(.*?)^```$/ms', file_get_contents(__DIR__ . '/../../docs/infra.md'), $match);
        $statements = preg_split('/;\s*$/m', trim($match[1]), -1, PREG_SPLIT_NO_EMPTY);
        $select = array_pop($statements);
        foreach ($statements as $statement) {
            $this->app->pdo->exec($statement);
        }
        $token = $this->app->pdo->query($select)->fetch(PDO::FETCH_ASSOC)['token'];

        self::assertSame(200, $this->get('/v1/messages', "Bearer $token")->getStatusCode());
    }

    public function testUnknownRouteIs404WithoutAuthentication(): void
    {
        $response = $this->get('/v1/nope');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertFalse($response->hasHeader('WWW-Authenticate'));
    }

    private function get(string $path, ?string $authorization = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $path);
        if ($authorization !== null) {
            $request = $request->withHeader('Authorization', $authorization);
        }

        return $this->app->handle($request);
    }

    private static function assertUnauthorized(ResponseInterface $response, string $detail): void
    {
        self::assertSame(401, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertSame('Bearer', $response->getHeaderLine('WWW-Authenticate'));
        self::assertSame($detail, TestApp::json($response)['detail']);
    }
}
