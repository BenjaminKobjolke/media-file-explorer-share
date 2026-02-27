<?php
declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Middleware\AuthMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Response;

class AuthMiddlewareTest extends TestCase
{
    private function createHandler(int $statusCode = 200): RequestHandlerInterface
    {
        return new class($statusCode) implements RequestHandlerInterface {
            private int $status;
            public function __construct(int $status) { $this->status = $status; }
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = new Response();
                $response->getBody()->write('OK');
                return $response->withStatus($this->status);
            }
        };
    }

    private function createRequest(array $headers = []): ServerRequestInterface
    {
        $request = (new RequestFactory())->createRequest('GET', '/test');
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        return $request;
    }

    public function testAuthDisabledPassesThrough(): void
    {
        $middleware = new AuthMiddleware(['auth_enabled' => false]);
        $response = $middleware->process($this->createRequest(), $this->createHandler());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testValidCredentialsPassThrough(): void
    {
        $config = ['auth_enabled' => true, 'auth_username' => 'admin', 'auth_password' => 'secret'];
        $middleware = new AuthMiddleware($config);

        $creds = base64_encode('admin:secret');
        $request = $this->createRequest(['Authorization' => "Basic {$creds}"]);

        $response = $middleware->process($request, $this->createHandler());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testInvalidCredentialsReturn401(): void
    {
        $config = ['auth_enabled' => true, 'auth_username' => 'admin', 'auth_password' => 'secret'];
        $middleware = new AuthMiddleware($config);

        $creds = base64_encode('admin:wrong');
        $request = $this->createRequest(['Authorization' => "Basic {$creds}"]);

        $response = $middleware->process($request, $this->createHandler());
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Basic realm="API"', $response->getHeaderLine('WWW-Authenticate'));
    }

    public function testMissingCredentialsReturn401(): void
    {
        $config = ['auth_enabled' => true, 'auth_username' => 'admin', 'auth_password' => 'secret'];
        $middleware = new AuthMiddleware($config);

        $response = $middleware->process($this->createRequest(), $this->createHandler());
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testWrongAuthSchemeReturn401(): void
    {
        $config = ['auth_enabled' => true, 'auth_username' => 'admin', 'auth_password' => 'secret'];
        $middleware = new AuthMiddleware($config);

        $request = $this->createRequest(['Authorization' => 'Bearer sometoken']);
        $response = $middleware->process($request, $this->createHandler());
        $this->assertSame(401, $response->getStatusCode());
    }
}
