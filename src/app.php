<?php

declare(strict_types=1);

use Apimail\Shared\ProblemJsonErrorHandler;
use Apimail\Shared\SecurityHeaders;
use Slim\Factory\AppFactory;

AppFactory::setContainer(require __DIR__ . '/container.php');
$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(false, false, false)->setDefaultErrorHandler(ProblemJsonErrorHandler::class);

// Ajouté en dernier, donc le plus à l'extérieur : couvre aussi les réponses d'erreur.
$app->add(SecurityHeaders::class);

return $app;
