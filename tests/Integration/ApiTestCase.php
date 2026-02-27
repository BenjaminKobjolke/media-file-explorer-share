<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Actions\DatabaseAction;
use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\DbGuardMiddleware;
use App\RequestContext;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tests\TestCase;

class ApiTestCase extends TestCase
{
    protected \Slim\App $app;
    protected array $config;
    protected string $dbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbPath = $this->createTempDbPath();
        $storageDir = $this->createTempDir();

        $this->config = [
            'email_enabled'  => false,
            'storage_enabled' => false,
            'storage_path'    => $storageDir,
            'auth_enabled'  => false,
            'auth_username' => 'testuser',
            'auth_password' => 'testpass',
            'max_file_size' => 10 * 1024 * 1024,
            'max_text_size' =>  1 * 1024 * 1024,
            'db_enabled' => true,
            'db_path'    => $this->dbPath,
            'api_enabled' => true,
            'response_message' => 'OK',
            'cors_origins' => [],
            'debug' => true,
        ];

        $this->app = $this->createApp($this->config);
    }

    protected function createApp(array $config): \Slim\App
    {
        $app = AppFactory::create();
        $basePath = '';
        $app->addRoutingMiddleware();

        $errorMiddleware = $app->addErrorMiddleware(true, true, true);
        $errorMiddleware->setDefaultErrorHandler(function (
            ServerRequestInterface $request,
            \Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails
        ) use ($app): ResponseInterface {
            $response = $app->getResponseFactory()->createResponse();
            $status = $exception instanceof \Slim\Exception\HttpException
                ? $exception->getCode()
                : 500;
            $message = $displayErrorDetails ? $exception->getMessage() : 'Internal Server Error';
            $response->getBody()->write((string) json_encode(['error' => $message]));
            return $response
                ->withStatus($status)
                ->withHeader('Content-Type', 'application/json');
        });

        $app->add(new CorsMiddleware($config['cors_origins'] ?? []));

        // Public routes
        require __DIR__ . '/../../inc/Routes/meta.php';

        // Auth-protected routes
        $app->group('', function (\Slim\Routing\RouteCollectorProxy $group) use ($config, $basePath) {
            $app = $group;
            require __DIR__ . '/../../inc/Routes/upload.php';

            $group->group('', function (\Slim\Routing\RouteCollectorProxy $inner) use ($config, $basePath) {
                $app = $inner;
                require __DIR__ . '/../../inc/Routes/entries.php';
                require __DIR__ . '/../../inc/Routes/custom_fields.php';
                require __DIR__ . '/../../inc/Routes/field_options.php';
            })->add(new DbGuardMiddleware($config));
        })->add(new AuthMiddleware($config));

        return $app;
    }

    protected function createJsonRequest(string $method, string $uri, ?array $body = null, array $headers = []): ServerRequestInterface
    {
        $request = (new RequestFactory())->createRequest($method, $uri);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== null) {
            $json = json_encode($body);
            $stream = (new StreamFactory())->createStream($json);
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($stream);
        }

        return $request;
    }

    protected function runRequest(ServerRequestInterface $request): ResponseInterface
    {
        return $this->app->handle($request);
    }

    protected function decodeResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertIsArray($data, "Response body is not valid JSON: {$body}");
        return $data;
    }

    protected function seedTextEntry(string $subject = 'Test Entry', string $body = 'Test body'): int
    {
        $ctx = $this->makeContext();
        return DatabaseAction::saveText($this->dbPath, $subject, $body, $ctx);
    }
}
