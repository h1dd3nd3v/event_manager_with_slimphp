<?php

declare(strict_types=1);

namespace App\Routes;

use Slim\App;
use App\Controllers\EventsController;
use App\Middleware\AuthMiddleware;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/events', function (RouteCollectorProxy $group) {
        $group->post('', [EventsController::class, 'create_event']);
        $group->get('', [EventsController::class, 'show_events']);
        $group->get('/{event_id}', [EventsController::class, 'get_event']);
        $group->delete('/{event_id}', [EventsController::class, 'delete_event']);
        $group->post('/{event_id}', [EventsController::class, 'update_event_details']);
        $group->post('/image/{event_id}', [EventsController::class, 'update_event_image']);
    });
};