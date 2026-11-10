<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Models\UsersModel;
use App\Utilities\JwtToken;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;
use UnexpectedValueException;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $authorization = trim($request->getHeaderLine('Authorization'));

        if (!preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
            return $this->unauthorized();
        }

        try {
            $claims = JwtToken::decode($matches[1]);
        } catch (UnexpectedValueException) {
            return $this->unauthorized();
        }

        if (!isset($claims->sub) || !is_string($claims->sub) || $claims->sub === '') {
            return $this->unauthorized();
        }

        $user = UsersModel::where('username', $claims->sub)->first();

        if ($user === null) {
            return $this->unauthorized();
        }

        return $handler->handle($request->withAttribute('user', $user));
    }

    private function unauthorized(): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'errors' => true,
            'message' => 'Unauthorized',
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}
