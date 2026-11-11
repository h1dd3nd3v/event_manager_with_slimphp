<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\StripeController;
use App\Controllers\StripeWebhookController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use App\Middleware\AuthMiddleware;

return function (App $app): void {
    $app->group('/checkout', function (RouteCollectorProxy $group): void {
        $group->post('/create-checkout-session', [StripeController::class, 'create_checkout_session']);
        $group->post('/webhook', [StripeWebhookController::class, 'receive']);
        $group->get('/checkout-session', [StripeController::class, 'checkout_session']);
        $group->get('/checkout-events', [StripeController::class, 'checkout_events'])
            ->add(new AuthMiddleware());
    });
};
