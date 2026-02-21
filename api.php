<?php
declare(strict_types=1);

use App\Actions\DatabaseAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

// -- Pre-flight checks -------------------------------------------------------

$autoloadFile = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadFile)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Autoloader not found. Run "composer install" first.']);
    exit(1);
}

$configFile = __DIR__ . '/config/app.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Config file not found. Copy config/app.php.example to config/app.php']);
    exit(1);
}

require $autoloadFile;
$config = require $configFile;

// -- Feature gate ------------------------------------------------------------

if (empty($config['api_enabled'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'API is disabled']);
    exit;
}

if (empty($config['db_enabled'])) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database is disabled']);
    exit;
}

// -- Slim app ----------------------------------------------------------------

$debug = !empty($config['debug']);

$app = AppFactory::create();
$app->setBasePath($_SERVER['SCRIPT_NAME']);
$app->addRoutingMiddleware();

// JSON error handler (override Slim's default HTML errors)
$errorMiddleware = $app->addErrorMiddleware($debug, true, true);
$errorMiddleware->setDefaultErrorHandler(function (
    Request $request,
    \Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($app): Response {
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

// -- Routes ------------------------------------------------------------------

$app->get('/entries/{id:[0-9]+}', function (Request $request, Response $response, array $args) use ($config): Response {
    // Auth check
    if (!empty($config['api_auth_enabled'])) {
        $authHeader = $request->getHeaderLine('Authorization');
        $expected = 'Basic ' . base64_encode($config['auth_username'] . ':' . $config['auth_password']);
        if ($authHeader !== $expected) {
            $response->getBody()->write((string) json_encode(['error' => 'Unauthorized']));
            return $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('WWW-Authenticate', 'Basic realm="API"');
        }
    }

    // Lookup
    $entry = DatabaseAction::getById($config['db_path'], (int) $args['id']);
    if ($entry === null) {
        $response->getBody()->write((string) json_encode(['error' => 'Entry not found']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    // Cast integer fields for clean JSON
    $entry['id'] = (int) $entry['id'];
    $entry['file_size'] = $entry['file_size'] !== null ? (int) $entry['file_size'] : null;

    $response->getBody()->write((string) json_encode($entry));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
