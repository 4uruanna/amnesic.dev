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
        if ($exception instanceof HttpException) {
            $status = $exception->getCode();
            $detail = $exception->getMessage();
        } else {
            error_log((string) $exception);
            $status = 500;
            $detail = 'Erreur interne.';
        }

        $response = new Response($status);
        if ($exception instanceof HttpMethodNotAllowedException) {
            $response = $response->withHeader('Allow', implode(', ', $exception->getAllowedMethods()));
        }
        if ($status === 401) {
            $response = $response->withHeader('WWW-Authenticate', 'Bearer');
        }
        $response->getBody()->write(json_encode([
            'type' => 'about:blank',
            'title' => $response->getReasonPhrase(),
            'status' => $status,
            'detail' => $detail,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/problem+json');
    }
}
