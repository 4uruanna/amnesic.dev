<?php

declare(strict_types=1);

namespace Apimail\Account;

use Apimail\Account\Dto\AccountCreate;
use Apimail\Account\Dto\AccountDetail;
use Apimail\Account\Dto\AccountSummary;
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

    private static function json(ResponseInterface $response, JsonSerializable $body): ResponseInterface
    {
        $response->getBody()->write(json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
