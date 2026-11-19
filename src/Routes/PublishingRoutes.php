<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\PublishingController;
use App\Middleware\AuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    $app->group('/publishing', function (RouteCollectorProxy $group): void {
        $group->post('', [PublishingController::class, 'upload_file'])
            ->add(new AuthMiddleware());
        $group->get('', [PublishingController::class, 'list_entries']);
        $group->get('/{publishing_id}', [PublishingController::class, 'get_entry']);
        $group->post('/{publishing_id}', [PublishingController::class, 'update_entry'])
            ->add(new AuthMiddleware());
        $group->delete('/{publishing_id}', [PublishingController::class, 'delete_entry'])
            ->add(new AuthMiddleware());
    });
};
