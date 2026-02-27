<?php
declare(strict_types=1);

use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\DbGuardMiddleware;
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

// -- Version info ------------------------------------------------------------

$versionInfo = [];
$versionFile = __DIR__ . '/VERSION';
if (file_exists($versionFile)) {
    $versionInfo['_version'] = trim((string) file_get_contents($versionFile));
}
$deployFile = __DIR__ . '/deploy.ver';
if (file_exists($deployFile)) {
    $deployId = trim((string) file_get_contents($deployFile));
    if ($deployId !== '') {
        $versionInfo['_deploy_id'] = $deployId;
    }
}

// -- Feature gate ------------------------------------------------------------

if (empty($config['api_enabled'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(array_merge($versionInfo, ['error' => 'API is disabled']));
    exit;
}

// -- Slim app ----------------------------------------------------------------

$debug = !empty($config['debug']);

$app = AppFactory::create();
$scriptName = $_SERVER['SCRIPT_NAME'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (strpos($requestUri, $scriptName) === 0) {
    $basePath = $scriptName;
} else {
    $basePath = dirname($scriptName) . '/api';
}
$app->setBasePath($basePath);
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

// Version middleware (outermost — enriches all JSON responses with version info)
if (!empty($versionInfo)) {
    $app->add(function (Request $request, $handler) use ($versionInfo): Response {
        $response = $handler->handle($request);
        $contentType = $response->getHeaderLine('Content-Type');
        if (strpos($contentType, 'application/json') === false) {
            return $response;
        }
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return $response;
        }
        if (array_values($data) === $data) {
            $enriched = array_merge($versionInfo, ['data' => $data]);
        } else {
            $enriched = array_merge($versionInfo, $data);
        }
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, (string) json_encode($enriched));
        rewind($stream);
        return $response->withBody(new \Slim\Psr7\Stream($stream));
    });
}

// CORS middleware
$app->add(new CorsMiddleware($config['cors_origins'] ?? []));

// -- Routes ------------------------------------------------------------------

// Public routes (no auth, no db guard)
require __DIR__ . '/inc/Routes/meta.php';

// Auth-protected routes
$app->group('', function (\Slim\Routing\RouteCollectorProxy $group) use ($config, $basePath) {
    $app = $group;
    require __DIR__ . '/inc/Routes/upload.php';

    // DB-guarded routes (auth + db guard)
    $group->group('', function (\Slim\Routing\RouteCollectorProxy $inner) use ($config, $basePath) {
        $app = $inner;
        require __DIR__ . '/inc/Routes/entries.php';
        require __DIR__ . '/inc/Routes/custom_fields.php';
        require __DIR__ . '/inc/Routes/field_options.php';
    })->add(new DbGuardMiddleware($config));
})->add(new AuthMiddleware($config));

$app->run();
