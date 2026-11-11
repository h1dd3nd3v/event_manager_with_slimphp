<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\OrdersController;
use App\Middleware\AuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    $app->group('/orders', function (RouteCollectorProxy $group): void {
        $group->post('', [OrdersController::class, 'create_order']);
        $group->get('', [OrdersController::class, 'list_orders'])
            ->add(new AuthMiddleware());
        $group->get('/{order_id}', [OrdersController::class, 'get_order'])
            ->add(new AuthMiddleware());
        $group->patch('/{order_id}/fulfillment', [OrdersController::class, 'update_fulfillment'])
            ->add(new AuthMiddleware());
    });
};
