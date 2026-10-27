<?php

declare(strict_types=1);

namespace Apimail\Message;

use Apimail\Message\Dto\MessageDeposit;
use Apimail\Shared\JsonBody;
use JsonSerializable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** /v1/messages/** ; les rôles sont vérifiés par RequireRole dans app.php. */
final class MessageController
{
    public function __construct(private readonly MessageService $messages)
    {
    }

    public function deposit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $message = $this->messages->deposit(MessageDeposit::fromArray(JsonBody::read($request)));

        return self::json($response->withStatus(201)->withHeader('Location', "/v1/messages/$message->id"), ['id' => $message->id]);
    }

    /** @param array<string, mixed>|JsonSerializable $body */
    private static function json(ResponseInterface $response, array|JsonSerializable $body): ResponseInterface
    {
        $response->getBody()->write(json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
