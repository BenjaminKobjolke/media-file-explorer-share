<?php
declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Middleware\DbGuardMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Response;

class DbGuardMiddlewareTest extends TestCase
{
    private function createHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = new Response();
                $response->getBody()->write('OK');
                return $response;
            }
        };
    }

    public function testDbEnabledPassesThrough(): void
    {
        $middleware = new DbGuardMiddleware(['db_enabled' => true]);
        $request = (new RequestFactory())->createRequest('GET', '/test');
        $response = $middleware->process($request, $this->createHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDbDisabledReturns503(): void
    {
        $middleware = new DbGuardMiddleware(['db_enabled' => false]);
        $request = (new RequestFactory())->createRequest('GET', '/test');
        $response = $middleware->process($request, $this->createHandler());

        $this->assertSame(503, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('Database is disabled', $body['error']);
    }
}
