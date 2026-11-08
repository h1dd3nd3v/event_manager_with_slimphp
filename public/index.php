<?php
declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use Tuupola\Middleware\CorsMiddleware;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$app = AppFactory::create();

$app->add(function (Request $request, $handler): Response {

    $allowedOrigins = [
        'http://localhost:5173',

        // Local network
        // 'http://100.115.149.56:5173',
        // 'http://10.195.128.9:5173',
        // 'http://10.195.128.9',

        // Replace with your deployed frontend
        'https://adminconsole.mysankofa.com',
    ];

    $origin = $request->getHeaderLine('Origin');

    // Handle preflight requests
    if ($request->getMethod() === 'OPTIONS') {

        $response = new \Slim\Psr7\Response();

        if (in_array($origin, $allowedOrigins, true)) {
            return $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->withHeader(
                    'Access-Control-Allow-Headers',
                    'Origin, X-Requested-With, Content-Type, Accept, Authorization'
                )
                ->withHeader('Access-Control-Max-Age', '86400');
        }

        return $response;
    }

    $response = $handler->handle($request);

    if (in_array($origin, $allowedOrigins, true)) {
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader(
                'Access-Control-Allow-Headers',
                'Origin, X-Requested-With, Content-Type, Accept, Authorization'
            );
    }

    return $response;
});

require __DIR__ . '/../src/database.php';

$app->addBodyParsingMiddleware();

$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write("Hello world!");
    return $response;
});

(require __DIR__ . '/../src/routes.php')($app);

$app->run();