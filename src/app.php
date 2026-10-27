<?php

declare(strict_types=1);

use Apimail\Account\Authentication;
use Apimail\Shared\ProblemJsonErrorHandler;
use Apimail\Shared\SecurityHeaders;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotImplementedException;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

AppFactory::setContainer(require __DIR__ . '/container.php');
$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(false, false, false)->setDefaultErrorHandler(ProblemJsonErrorHandler::class);

// Ajouté en dernier, donc le plus à l'extérieur : couvre aussi les réponses d'erreur.
$app->add(SecurityHeaders::class);

$app->group('/v1', function (RouteCollectorProxy $v1): void {
    // Remplacée par MessageController en J04-1.
    $v1->get('/messages', function (ServerRequestInterface $request): never {
        throw new HttpNotImplementedException($request);
    });
})->add(Authentication::class);

return $app;
