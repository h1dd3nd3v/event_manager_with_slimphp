<?php

use Slim\App;

return function (App $app) {
    (require __DIR__ . "/Routes/UserRoutes.php")($app);
    (require __DIR__ . "/Routes/EventsRoutes.php")($app);
    (require __DIR__ . "/Routes/StripeRoutes.php")($app);
    (require __DIR__ . "/Routes/OrderRoutes.php")($app);
};