<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware implements MiddlewareInterface
{
    /** @var array<string, mixed> */
    private array $config;

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if (!empty($this->config['auth_enabled'])) {
            $authHeader = $request->getHeaderLine('Authorization');
            $expected = 'Basic ' . base64_encode($this->config['auth_username'] . ':' . $this->config['auth_password']);
            if ($authHeader !== $expected) {
                $response = new SlimResponse();
                $response->getBody()->write((string) json_encode(['error' => 'Unauthorized']));
                return $response
                    ->withStatus(401)
                    ->withHeader('Content-Type', 'application/json')
                    ->withHeader('WWW-Authenticate', 'Basic realm="API"');
            }
        }
        return $handler->handle($request);
    }
}
