<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\RoyalHonoreesController;
use App\Middleware\AuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    $app->group('/royal-honorees', function (RouteCollectorProxy $group): void {
        $group->get('', [RoyalHonoreesController::class, 'list_honorees']);
        $group->get('/{honoree_id}', [RoyalHonoreesController::class, 'get_honoree']);
        $group->post('', [RoyalHonoreesController::class, 'create_honoree'])
            ->add(new AuthMiddleware());
        $group->post('/{honoree_id}', [RoyalHonoreesController::class, 'update_honoree'])
            ->add(new AuthMiddleware());
        $group->delete('/{honoree_id}', [RoyalHonoreesController::class, 'delete_honoree'])
            ->add(new AuthMiddleware());
    });
};
