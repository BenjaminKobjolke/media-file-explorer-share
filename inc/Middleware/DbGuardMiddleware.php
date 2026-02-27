<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class DbGuardMiddleware implements MiddlewareInterface
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
        if (empty($this->config['db_enabled'])) {
            $response = new SlimResponse();
            $response->getBody()->write((string) json_encode(['error' => 'Database is disabled']));
            return $response
                ->withStatus(503)
                ->withHeader('Content-Type', 'application/json');
        }
        return $handler->handle($request);
    }
}
