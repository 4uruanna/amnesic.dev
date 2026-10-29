<?php

declare(strict_types=1);

namespace Apimail\Account;

use Apimail\Account\Dto\AccountCreate;
use Apimail\Account\Dto\AccountDetail;
use Apimail\Account\Dto\AccountSummary;
use Apimail\Account\Dto\BindingCreate;
use Apimail\Account\Dto\TokenCreate;
use Apimail\Account\Dto\TokenCreated;
use Apimail\Shared\JsonBody;
use Apimail\Shared\Page;
use JsonSerializable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** /v1/accounts/**, réservé aux admin (RequireRole dans app.php). */
final class AccountController
{
    public function __construct(private readonly AccountService $accounts)
    {
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $account = $this->accounts->create(AccountCreate::fromArray(JsonBody::read($request)));

        return self::json($response->withStatus(201)->withHeader('Location', "/v1/accounts/$account->id"), AccountSummary::fromModel($account));
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$offset, $limit] = Page::bounds($request->getQueryParams());
        [$accounts, $total] = $this->accounts->page($offset, $limit);

        return self::json($response, new Page(array_map(AccountSummary::fromModel(...), $accounts), $total, $offset, $limit));
    }

    /** @param array{id: string} $args */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return self::json($response, AccountDetail::fromModel(...$this->accounts->detail($args['id'])));
    }

    /** @param array{id: string} $args */
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->accounts->delete($args['id']);

        return $response->withStatus(204);
    }

    /** @param array{id: string} $args */
    public function bind(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $address = $this->accounts->bind($args['id'], BindingCreate::fromArray(JsonBody::read($request)));

        return self::json($response->withStatus(201), ['address' => $address]);
    }

    /** @param array{id: string, address: string} $args */
    public function unbind(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->accounts->unbind($args['id'], $args['address']);

        return $response->withStatus(204);
    }

    /** @param array{id: string} $args */
    public function issueToken(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        [$token, $secret] = $this->accounts->issueToken($args['id'], TokenCreate::fromArray(JsonBody::read($request)));

        return self::json($response->withStatus(201), TokenCreated::fromModel($token, $secret));
    }

    /** @param array{id: string, tokenId: string} $args */
    public function revokeToken(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->accounts->revokeToken($args['id'], $args['tokenId']);

        return $response->withStatus(204);
    }

    /** @param array<string, mixed>|JsonSerializable $body */
    private static function json(ResponseInterface $response, array|JsonSerializable $body): ResponseInterface
    {
        $response->getBody()->write(json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
