<?php
declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Middleware\CorsMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Response;

class CorsMiddlewareTest extends TestCase
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

    private function createRequest(string $method = 'GET', array $headers = []): ServerRequestInterface
    {
        $request = (new RequestFactory())->createRequest($method, '/test');
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        return $request;
    }

    public function testNoOriginPassesThrough(): void
    {
        $middleware = new CorsMiddleware(['http://example.com']);
        $response = $middleware->process($this->createRequest(), $this->createHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testAllowedOriginSetsCorsHeaders(): void
    {
        $middleware = new CorsMiddleware(['http://example.com']);
        $request = $this->createRequest('GET', ['Origin' => 'http://example.com']);
        $response = $middleware->process($request, $this->createHandler());

        $this->assertSame('http://example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertNotEmpty($response->getHeaderLine('Access-Control-Allow-Methods'));
    }

    public function testDisallowedOriginPassesThrough(): void
    {
        $middleware = new CorsMiddleware(['http://example.com']);
        $request = $this->createRequest('GET', ['Origin' => 'http://evil.com']);
        $response = $middleware->process($request, $this->createHandler());

        $this->assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testOptionsPreflightReturns204(): void
    {
        $middleware = new CorsMiddleware(['http://example.com']);
        $request = $this->createRequest('OPTIONS', ['Origin' => 'http://example.com']);
        $response = $middleware->process($request, $this->createHandler());

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('http://example.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testWildcardAllowsAnyOrigin(): void
    {
        $middleware = new CorsMiddleware(['*']);
        $request = $this->createRequest('GET', ['Origin' => 'http://anything.com']);
        $response = $middleware->process($request, $this->createHandler());

        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testEmptyOriginsConfigBlocksAll(): void
    {
        $middleware = new CorsMiddleware([]);
        $request = $this->createRequest('GET', ['Origin' => 'http://example.com']);
        $response = $middleware->process($request, $this->createHandler());

        $this->assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }
}
