<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;

$app = AppFactory::create();
$app->addRoutingMiddleware();

// Toute erreur sort en problem+json (RFC 9457) ; une erreur inattendue ne livre aucun détail.
$app->addErrorMiddleware(false, false, false)->setDefaultErrorHandler(
    function (ServerRequestInterface $request, Throwable $e): ResponseInterface {
        if ($e instanceof HttpException) {
            $status = $e->getCode();
            $detail = $e->getMessage();
        } else {
            error_log((string) $e);
            $status = 500;
            $detail = 'Erreur interne.';
        }

        $response = new Response($status);
        if ($e instanceof HttpMethodNotAllowedException) {
            $response = $response->withHeader('Allow', implode(', ', $e->getAllowedMethods()));
        }
        $response->getBody()->write(json_encode([
            'type' => 'about:blank',
            'title' => $response->getReasonPhrase(),
            'status' => $status,
            'detail' => $detail,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/problem+json');
    }
);

// Ajouté en dernier, donc le plus à l'extérieur : couvre aussi les réponses d'erreur.
$app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    return $handler->handle($request)
        ->withHeader('Strict-Transport-Security', 'max-age=31536000')
        ->withHeader('X-Content-Type-Options', 'nosniff')
        ->withHeader('Cache-Control', 'no-store');
});

return $app;
