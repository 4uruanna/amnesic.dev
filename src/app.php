<?php

declare(strict_types=1);

use Apimail\Account\AccountController;
use Apimail\Account\Authentication;
use Apimail\Account\RequireRole;
use Apimail\Message\MessageController;
use Apimail\Setup\SetupController;
use Apimail\Shared\ProblemJsonErrorHandler;
use Apimail\Shared\SecurityHeaders;
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
    $v1->get('/messages', [MessageController::class, 'list'])->add(new RequireRole('admin', 'user'));
    // Un id hors de cet alphabet ne désigne aucune route : MySQL refuse de comparer un « é » à une colonne ascii.
    $v1->group('/messages/{id:[0-9a-f-]+}', function (RouteCollectorProxy $message): void {
        $message->get('', [MessageController::class, 'show']);
        $message->get('/attachment/{partId:[1-9][0-9]*}', [MessageController::class, 'attachment']);
        $message->delete('', [MessageController::class, 'delete']);
    })->add(new RequireRole('admin', 'user'));

    $v1->group('/accounts', function (RouteCollectorProxy $accounts): void {
        $accounts->post('', [AccountController::class, 'create']);
        $accounts->get('', [AccountController::class, 'list']);
        // Même alphabet que /messages/{id}, pour la même raison.
        $accounts->group('/{id:[0-9a-f-]+}', function (RouteCollectorProxy $account): void {
            $account->get('', [AccountController::class, 'show']);
            $account->delete('', [AccountController::class, 'delete']);
            $account->post('/bindings', [AccountController::class, 'bind']);
            $account->delete('/bindings/{address}', [AccountController::class, 'unbind']);
            $account->post('/tokens', [AccountController::class, 'issueToken']);
            $account->delete('/tokens/{tokenId:[0-9a-f-]+}', [AccountController::class, 'revokeToken']);
        });
    })->add(new RequireRole('admin'));
})->add(Authentication::class);

return $app;
