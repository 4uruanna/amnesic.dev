<?php

declare(strict_types=1);

namespace Apimail\Shared;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Interfaces\ErrorHandlerInterface;
use Slim\Psr7\Response;
use Throwable;

/** Toute erreur sort en problem+json (RFC 9457) ; une erreur inattendue ne livre aucun détail. */
final class ProblemJsonErrorHandler implements ErrorHandlerInterface
{
    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        $status = match (true) {
            $exception instanceof HttpException => $exception->getCode(),
            $exception instanceof ValidationFailed => 422,
            $exception instanceof NotFound => 404,
            $exception instanceof Conflict => 409,
            default => 500,
        };
        if ($status === 500) {
            error_log((string) $exception);
            $detail = 'Erreur interne.';
        } else {
            $detail = $exception->getMessage();
        }

        $response = new Response($status);
        if ($status === 422) {
            $response = $response->withStatus(422, 'Unprocessable Content'); // nom de la RFC 9110, slim/psr7 garde l'ancien
        }
        if ($exception instanceof HttpMethodNotAllowedException) {
            $response = $response->withHeader('Allow', implode(', ', $exception->getAllowedMethods()));
        }
        if ($status === 401) {
            $response = $response->withHeader('WWW-Authenticate', 'Bearer');
        }
        $problem = [
            'type' => 'about:blank',
            'title' => $response->getReasonPhrase(),
            'status' => $status,
            'detail' => $detail,
        ];
        if ($exception instanceof ValidationFailed) {
            $problem['errors'] = $exception->errors;
        }
        $response->getBody()->write(json_encode($problem, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/problem+json');
    }
}
