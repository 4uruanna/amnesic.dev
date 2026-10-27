<?php

declare(strict_types=1);

namespace Apimail\Tests\Account;

use Apimail\Shared\Uuid;
use Apimail\Tests\TestApp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class AccountControllerTest extends TestCase
{
    private const UUID = '/\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/';
    private const DATE = '/\A\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z\z/';

    private TestApp $app;
    private string $admin;

    protected function setUp(): void
    {
        $this->app = new TestApp();
        $this->admin = $this->app->token('admin');
    }

    public function testCreateIs201WithLocationAndSummary(): void
    {
        $response = $this->app->request('POST', '/v1/accounts', $this->admin, ['name' => 'relais-mx', 'role' => 'bot']);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $body = TestApp::json($response);
        self::assertSame(['id', 'name', 'role', 'createdAt'], array_keys($body));
        self::assertMatchesRegularExpression(self::UUID, $body['id']);
        self::assertSame(['relais-mx', 'bot'], [$body['name'], $body['role']]);
        self::assertMatchesRegularExpression(self::DATE, $body['createdAt']);
        self::assertSame("/v1/accounts/{$body['id']}", $response->getHeaderLine('Location'));
        self::assertSame($body, $this->summary($body['id']));
    }

    public function testCreateWithATakenNameIs409(): void
    {
        $response = $this->app->request('POST', '/v1/accounts', $this->admin, ['name' => 'admin', 'role' => 'user']);

        self::assertProblem($response, 409, 'Nom de Compte déjà pris.');
    }

    public function testCreateWithAnUnknownRoleOrAnExtraFieldIs422(): void
    {
        $response = $this->app->request('POST', '/v1/accounts', $this->admin, ['name' => 'ci', 'role' => 'relay', 'cc' => []]);

        self::assertProblem($response, 422, 'Le corps de la requête est invalide.');
        self::assertSame('Unprocessable Content', TestApp::json($response)['title']);
        self::assertSame([
            ['field' => 'cc', 'message' => 'Champ inconnu.'],
            ['field' => 'role', 'message' => 'admin, user ou bot attendu.'],
        ], TestApp::json($response)['errors']);
    }

    public function testCreateReadsOnlyAJsonObject(): void
    {
        self::assertProblem($this->app->request('POST', '/v1/accounts', $this->admin, 'name=ci', 'application/x-www-form-urlencoded'), 415, 'Le corps doit être en application/json.');
        self::assertProblem($this->app->request('POST', '/v1/accounts', $this->admin, '{"name": "ci",'), 400, 'JSON mal formé.');
        self::assertSame(201, $this->app->request('POST', '/v1/accounts', $this->admin, '{"name":"ci","role":"user"}', 'application/json; charset=utf-8')->getStatusCode());

        $response = $this->app->request('POST', '/v1/accounts', $this->admin, '"ci"');
        self::assertProblem($response, 422, 'Le corps de la requête est invalide.');
        self::assertSame([['field' => '', 'message' => 'Objet JSON attendu.']], TestApp::json($response)['errors']);
    }

    public function testListIsPagedAndSortedByName(): void
    {
        foreach (['carol', 'alice', 'bob'] as $name) {
            $this->app->request('POST', '/v1/accounts', $this->admin, ['name' => $name, 'role' => 'user']);
        }

        $page = TestApp::json($this->app->request('GET', '/v1/accounts?offset=1&limit=2', $this->admin));
        self::assertSame(['alice', 'bob'], array_column($page['items'], 'name'));
        self::assertSame([4, 1, 2], [$page['total'], $page['offset'], $page['limit']]);
        self::assertSame($this->summary($page['items'][1]['id']), $page['items'][1]);

        $page = TestApp::json($this->app->request('GET', '/v1/accounts', $this->admin));
        self::assertSame(['admin', 'alice', 'bob', 'carol'], array_column($page['items'], 'name'));
        self::assertSame([4, 0, 20], [$page['total'], $page['offset'], $page['limit']]);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidPagination(): iterable
    {
        yield 'offset négatif' => ['offset=-1', 'offset'];
        yield 'offset pas un entier' => ['offset=a', 'offset'];
        yield 'limit nul' => ['limit=0', 'limit'];
        yield 'limit au-delà de 100' => ['limit=101', 'limit'];
    }

    #[DataProvider('invalidPagination')]
    public function testListOutOfBoundsIs422(string $query, string $field): void
    {
        $response = $this->app->request('GET', "/v1/accounts?$query", $this->admin);

        self::assertProblem($response, 422, 'Les paramètres de la requête sont invalides.');
        self::assertSame([$field], array_column(TestApp::json($response)['errors'], 'field'));
    }

    public function testShowGivesAddressesAndTokensWithoutSecret(): void
    {
        $id = $this->create('ci', 'user');
        $this->bind($id, 'b@x.tld');
        $this->bind($id, 'a@x.tld');
        $later = $this->addToken($id, 'later', '2026-10-27 10:00:00.000');
        $first = $this->addToken($id, 'first', '2026-10-27 09:00:00.000');

        $response = $this->app->request('GET', "/v1/accounts/$id", $this->admin);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([
            ...$this->summary($id),
            'addresses' => ['a@x.tld', 'b@x.tld'],
            'tokens' => [
                ['id' => $first, 'name' => 'first', 'createdAt' => '2026-10-27T09:00:00.000Z'],
                ['id' => $later, 'name' => 'later', 'createdAt' => '2026-10-27T10:00:00.000Z'],
            ],
        ], TestApp::json($response));
    }

    public function testUnknownAccountIs404(): void
    {
        $unknown = Uuid::v7();

        self::assertProblem($this->app->request('GET', "/v1/accounts/$unknown", $this->admin), 404, 'Compte introuvable.');
        self::assertProblem($this->app->request('DELETE', "/v1/accounts/$unknown", $this->admin), 404, 'Compte introuvable.');
    }

    public function testDeleteTakesTokensAndBindingsAlong(): void
    {
        $id = $this->create('ci', 'user');
        $this->bind($id, 'ci@x.tld');
        $this->addToken($id, 'ci', '2026-10-27 09:00:00.000');

        $response = $this->app->request('DELETE', "/v1/accounts/$id", $this->admin);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertSame(404, $this->app->request('GET', "/v1/accounts/$id", $this->admin)->getStatusCode());
        $remaining = $this->app->pdo->prepare('SELECT (SELECT COUNT(*) FROM tokens WHERE account_id = ?) + (SELECT COUNT(*) FROM bindings WHERE account_id = ?)');
        $remaining->execute([$id, $id]);
        self::assertSame(0, (int) $remaining->fetchColumn());
    }

    public function testBindingIsStoredInLowercase(): void
    {
        $id = $this->create('ci', 'user');

        $response = $this->app->request('POST', "/v1/accounts/$id/bindings", $this->admin, ['address' => 'Ci@X.tld']);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(['address' => 'ci@x.tld'], TestApp::json($response));
        self::assertSame(['ci@x.tld'], TestApp::json($this->app->request('GET', "/v1/accounts/$id", $this->admin))['addresses']);
    }

    public function testBindingAlreadyPresentIs409(): void
    {
        $id = $this->create('ci', 'user');
        $this->app->request('POST', "/v1/accounts/$id/bindings", $this->admin, ['address' => 'ci@x.tld']);

        $response = $this->app->request('POST', "/v1/accounts/$id/bindings", $this->admin, ['address' => 'CI@x.tld']);

        self::assertProblem($response, 409, 'Adresse déjà liée à ce Compte.');
    }

    public function testBindingOnABotIs422(): void
    {
        $id = $this->create('relais-mx', 'bot');

        $response = $this->app->request('POST', "/v1/accounts/$id/bindings", $this->admin, ['address' => 'ci@x.tld']);

        self::assertProblem($response, 422, 'Un Compte bot ne reçoit pas de Binding.');
        self::assertSame([['field' => '', 'message' => 'Un Compte bot ne reçoit pas de Binding.']], TestApp::json($response)['errors']);
    }

    public function testUnbind(): void
    {
        $id = $this->create('ci', 'user');
        $this->app->request('POST', "/v1/accounts/$id/bindings", $this->admin, ['address' => 'ci@x.tld']);

        // L'adresse du chemin se compare en minuscules, encodée ou non.
        $response = $this->app->request('DELETE', "/v1/accounts/$id/bindings/CI%40X.tld", $this->admin);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame([], TestApp::json($this->app->request('GET', "/v1/accounts/$id", $this->admin))['addresses']);
        self::assertProblem($this->app->request('DELETE', "/v1/accounts/$id/bindings/ci@x.tld", $this->admin), 404, 'Binding introuvable.');
    }

    public function testBindingOrTokenOfAnUnknownAccountIs404(): void
    {
        $unknown = Uuid::v7();

        self::assertProblem($this->app->request('POST', "/v1/accounts/$unknown/bindings", $this->admin, ['address' => 'ci@x.tld']), 404, 'Compte introuvable.');
        self::assertProblem($this->app->request('DELETE', "/v1/accounts/$unknown/bindings/ci@x.tld", $this->admin), 404, 'Binding introuvable.');
        self::assertProblem($this->app->request('POST', "/v1/accounts/$unknown/tokens", $this->admin, ['name' => 'ci']), 404, 'Compte introuvable.');
    }

    public function testIssuedTokenAuthenticatesUntilRevoked(): void
    {
        $id = $this->create('ci', 'user');

        $response = $this->app->request('POST', "/v1/accounts/$id/tokens", $this->admin, ['name' => 'ci']);

        self::assertSame(201, $response->getStatusCode());
        $created = TestApp::json($response);
        self::assertSame(['id', 'name', 'createdAt', 'token'], array_keys($created));
        self::assertMatchesRegularExpression(self::UUID, $created['id']);
        self::assertSame('ci', $created['name']);
        self::assertMatchesRegularExpression(self::DATE, $created['createdAt']);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $created['token']);
        unset($created['token']);
        self::assertSame([$created], TestApp::json($this->app->request('GET', "/v1/accounts/$id", $this->admin))['tokens']);

        $token = TestApp::json($response)['token'];
        self::assertSame(501, $this->app->request('GET', '/v1/messages', $token)->getStatusCode());

        self::assertSame(204, $this->app->request('DELETE', "/v1/accounts/$id/tokens/{$created['id']}", $this->admin)->getStatusCode());
        self::assertSame(401, $this->app->request('GET', '/v1/messages', $token)->getStatusCode());
        self::assertProblem($this->app->request('DELETE', "/v1/accounts/$id/tokens/{$created['id']}", $this->admin), 404, 'Jeton introuvable.');
    }

    public function testTokenOfAnotherAccountIs404(): void
    {
        $ci = $this->create('ci', 'user');
        $other = $this->create('other', 'user');
        $tokenId = $this->addToken($other, 'other', '2026-10-27 09:00:00.000');

        self::assertProblem($this->app->request('DELETE', "/v1/accounts/$ci/tokens/$tokenId", $this->admin), 404, 'Jeton introuvable.');
    }

    /** @return iterable<string, array{string}> */
    public static function nonAdminRoles(): iterable
    {
        yield 'user' => ['user'];
        yield 'bot' => ['bot'];
    }

    #[DataProvider('nonAdminRoles')]
    public function testOnlyAdminsReachAccounts(string $role): void
    {
        $token = $this->app->token($role);
        $id = $this->create('ci', 'user');

        foreach ([
            ['POST', '/v1/accounts', ['name' => 'x', 'role' => 'user']],
            ['GET', '/v1/accounts', null],
            ['GET', "/v1/accounts/$id", null],
            ['GET', '/v1/accounts/' . Uuid::v7(), null], // 403 avant 404
            ['DELETE', "/v1/accounts/$id", null],
            ['POST', "/v1/accounts/$id/bindings", ['address' => 'ci@x.tld']],
            ['DELETE', "/v1/accounts/$id/bindings/ci@x.tld", null],
            ['POST', "/v1/accounts/$id/tokens", ['name' => 'ci']],
            ['DELETE', "/v1/accounts/$id/tokens/" . Uuid::v7(), null],
        ] as [$method, $path, $body]) {
            self::assertProblem($this->app->request($method, $path, $token, $body), 403, 'Rôle non autorisé.');
        }
        self::assertSame(200, $this->app->request('GET', "/v1/accounts/$id", $this->admin)->getStatusCode());
    }

    private function create(string $name, string $role): string
    {
        return TestApp::json($this->app->request('POST', '/v1/accounts', $this->admin, ['name' => $name, 'role' => $role]))['id'];
    }

    /** @return array<string, mixed> l'AccountSummary attendu, lu en base */
    private function summary(string $id): array
    {
        $select = $this->app->pdo->prepare('SELECT id, name, role, created_at FROM accounts WHERE id = ?');
        $select->execute([$id]);
        $row = $select->fetch();

        return ['id' => $row['id'], 'name' => $row['name'], 'role' => $row['role'], 'createdAt' => str_replace(' ', 'T', $row['created_at']) . 'Z'];
    }

    private function bind(string $id, string $address): void
    {
        $this->app->pdo->prepare('INSERT INTO bindings (account_id, address) VALUES (?, ?)')->execute([$id, $address]);
    }

    private function addToken(string $id, string $name, string $createdAt): string
    {
        $tokenId = Uuid::v7();
        $this->app->pdo->prepare('INSERT INTO tokens (id, account_id, name, hash, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$tokenId, $id, $name, hash('sha256', random_bytes(32)), $createdAt]);

        return $tokenId;
    }

    private static function assertProblem(ResponseInterface $response, int $status, string $detail): void
    {
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertSame($detail, TestApp::json($response)['detail']);
    }
}
