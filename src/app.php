<?php

declare(strict_types=1);

use Apimail\Account\AccountController;
use Apimail\Account\Authentication;
use Apimail\Account\RequireRole;
use Apimail\Message\MessageController;
use Apimail\Setup\SetupController;
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

// Hors de la garde par en-tête : jeton admin en ?token=, ou aucun sur une base vide.
$app->get('/v1/setup', [SetupController::class, 'migrate']);

$app->group('/v1', function (RouteCollectorProxy $v1): void {
    $v1->post('/messages', [MessageController::class, 'deposit'])->add(new RequireRole('bot'));
    // Remplacée par MessageController en J04-1.
    $v1->get('/messages', function (ServerRequestInterface $request): never {
        throw new HttpNotImplementedException($request);
    })->add(new RequireRole('admin', 'user'));

    $v1->group('/accounts', function (RouteCollectorProxy $accounts): void {
        $accounts->post('', [AccountController::class, 'create']);
        $accounts->get('', [AccountController::class, 'list']);
        $accounts->get('/{id}', [AccountController::class, 'show']);
        $accounts->delete('/{id}', [AccountController::class, 'delete']);
        $accounts->post('/{id}/bindings', [AccountController::class, 'bind']);
        $accounts->delete('/{id}/bindings/{address}', [AccountController::class, 'unbind']);
        $accounts->post('/{id}/tokens', [AccountController::class, 'issueToken']);
        $accounts->delete('/{id}/tokens/{tokenId}', [AccountController::class, 'revokeToken']);
    })->add(new RequireRole('admin'));
})->add(Authentication::class);

return $app;
