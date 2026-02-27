<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * CORS middleware — handles preflight OPTIONS requests and sets
 * Access-Control-* headers for configured origins.
 */
class CorsMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    private array $allowedOrigins;

    /**
     * @param string[] $allowedOrigins List of allowed origin URLs.
     */
    public function __construct(array $allowedOrigins)
    {
        $this->allowedOrigins = $allowedOrigins;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $origin = $request->getHeaderLine('Origin');

        // No Origin header or origin not in whitelist — pass through
        $isAllowed = in_array('*', $this->allowedOrigins, true)
                  || in_array($origin, $this->allowedOrigins, true);
        if ($origin === '' || !$isAllowed) {
            return $handler->handle($request);
        }

        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            $response = new SlimResponse();
            return $this->addCorsHeaders($response, $origin)->withStatus(204);
        }

        // Normal request — add CORS headers to response
        $response = $handler->handle($request);
        return $this->addCorsHeaders($response, $origin);
    }

    private function addCorsHeaders(Response $response, string $origin): Response
    {
        $allowOrigin = in_array('*', $this->allowedOrigins, true) ? '*' : $origin;
        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Max-Age', '86400');
    }
}
