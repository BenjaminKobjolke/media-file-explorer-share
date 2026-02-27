<?php
declare(strict_types=1);

use App\Actions\DatabaseAction;
use App\Actions\StorageAction;
use App\Middleware\CorsMiddleware;
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

// -- Route helpers -----------------------------------------------------------

$checkAuth = function (Request $request, Response $response) use ($config): ?Response {
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
    return null;
};

$checkDb = function (Response $response) use ($config): ?Response {
    if (empty($config['db_enabled'])) {
        $response->getBody()->write((string) json_encode(['error' => 'Database is disabled']));
        return $response
            ->withStatus(503)
            ->withHeader('Content-Type', 'application/json');
    }
    return null;
};

$lookupEntry = function (int $id, Response $response) use ($config, $basePath): Response {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $scheme . '://' . $host . $basePath;

    $entry = DatabaseAction::getByIdWithAttachments($config['db_path'], $id, $baseUrl);
    if ($entry === null) {
        $response->getBody()->write((string) json_encode(['error' => 'Entry not found']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write((string) json_encode($entry));
    return $response->withHeader('Content-Type', 'application/json');
};

// -- Routes ------------------------------------------------------------------

$app->get('/entries/{id:[0-9]+}', function (Request $request, Response $response, array $args) use ($checkAuth, $checkDb, $lookupEntry): Response {
    $dbResponse = $checkDb($response);
    if ($dbResponse !== null) {
        return $dbResponse;
    }
    $authResponse = $checkAuth($request, $response);
    if ($authResponse !== null) {
        return $authResponse;
    }
    return $lookupEntry((int) $args['id'], $response);
});

$app->post('/entries', function (Request $request, Response $response) use ($checkAuth, $checkDb, $lookupEntry): Response {
    $dbResponse = $checkDb($response);
    if ($dbResponse !== null) {
        return $dbResponse;
    }
    $authResponse = $checkAuth($request, $response);
    if ($authResponse !== null) {
        return $authResponse;
    }

    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body) || !isset($body['id'])) {
        $response->getBody()->write((string) json_encode(['error' => 'Missing id in request body']));
        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    return $lookupEntry((int) $body['id'], $response);
});

$app->get('/entries/{id:[0-9]+}/file', function (Request $request, Response $response, array $args) use ($checkAuth, $checkDb, $config): Response {
    $dbResponse = $checkDb($response);
    if ($dbResponse !== null) {
        return $dbResponse;
    }
    $authResponse = $checkAuth($request, $response);
    if ($authResponse !== null) {
        return $authResponse;
    }

    // Lookup entry
    $entry = DatabaseAction::getById($config['db_path'], (int) $args['id']);
    if ($entry === null) {
        $response->getBody()->write((string) json_encode(['error' => 'Entry not found']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    if ($entry['type'] !== 'file' || $entry['file_path'] === null) {
        $response->getBody()->write((string) json_encode(['error' => 'No file stored for this entry']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    // Path traversal protection
    $realPath = realpath($entry['file_path']);
    $storagePath = realpath($config['storage_path']);
    if ($realPath === false || $storagePath === false || strpos($realPath, $storagePath) !== 0) {
        $response->getBody()->write((string) json_encode(['error' => 'File not accessible']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    // Serve the file
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($realPath) ?: 'application/octet-stream';
    $fileSize = filesize($realPath);
    $filename = $entry['filename'] ?? basename($realPath);

    $stream = fopen($realPath, 'rb');
    $body = new \Slim\Psr7\Stream($stream);

    return $response
        ->withHeader('Content-Type', $mimeType)
        ->withHeader('Content-Length', (string) $fileSize)
        ->withHeader('Content-Disposition', 'inline; filename="' . addslashes($filename) . '"')
        ->withBody($body);
});

$app->get('/files/{id:[0-9]+}', function (Request $request, Response $response, array $args) use ($checkAuth, $checkDb, $config): Response {
    $dbResponse = $checkDb($response);
    if ($dbResponse !== null) {
        return $dbResponse;
    }
    $authResponse = $checkAuth($request, $response);
    if ($authResponse !== null) {
        return $authResponse;
    }

    // Lookup attachment
    $attachment = DatabaseAction::getAttachmentById($config['db_path'], (int) $args['id']);
    if ($attachment === null) {
        $response->getBody()->write((string) json_encode(['error' => 'Attachment not found']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    if ($attachment['type'] !== 'file' || $attachment['file_path'] === null) {
        $response->getBody()->write((string) json_encode(['error' => 'No file stored for this attachment']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    // Path traversal protection
    $realPath = realpath($attachment['file_path']);
    $storagePath = realpath($config['storage_path']);
    if ($realPath === false || $storagePath === false || strpos($realPath, $storagePath) !== 0) {
        $response->getBody()->write((string) json_encode(['error' => 'File not accessible']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    // Serve the file
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($realPath) ?: 'application/octet-stream';
    $fileSize = filesize($realPath);
    $filename = $attachment['filename'] ?? basename($realPath);

    $stream = fopen($realPath, 'rb');
    $body = new \Slim\Psr7\Stream($stream);

    return $response
        ->withHeader('Content-Type', $mimeType)
        ->withHeader('Content-Length', (string) $fileSize)
        ->withHeader('Content-Disposition', 'inline; filename="' . addslashes($filename) . '"')
        ->withBody($body);
});

$app->get('/entries', function (Request $request, Response $response) use ($checkAuth, $checkDb, $config): Response {
    $dbResponse = $checkDb($response);
    if ($dbResponse !== null) {
        return $dbResponse;
    }
    $authResponse = $checkAuth($request, $response);
    if ($authResponse !== null) {
        return $authResponse;
    }

    $params = $request->getQueryParams();
    $page = max(1, (int) ($params['page'] ?? 1));
    $perPage = max(1, min(100, (int) ($params['per_page'] ?? 20)));

    $result = DatabaseAction::getAllPaginated($config['db_path'], $page, $perPage);
    $response->getBody()->write((string) json_encode($result));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->delete('/entries/{id:[0-9]+}', function (Request $request, Response $response, array $args) use ($checkAuth, $checkDb, $config): Response {
    $dbResponse = $checkDb($response);
    if ($dbResponse !== null) {
        return $dbResponse;
    }
    $authResponse = $checkAuth($request, $response);
    if ($authResponse !== null) {
        return $authResponse;
    }

    $deleted = DatabaseAction::deleteEntry($config['db_path'], (int) $args['id']);
    if ($deleted === null) {
        $response->getBody()->write((string) json_encode(['error' => 'Entry not found']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    // Cleanup files from disk
    if (!empty($config['storage_path'])) {
        if ($deleted['file_path'] !== null) {
            StorageAction::deleteFile($config['storage_path'], $deleted['file_path']);
        }
        foreach ($deleted['attachments'] as $att) {
            if ($att['file_path'] !== null) {
                StorageAction::deleteFile($config['storage_path'], $att['file_path']);
            }
        }
    }

    $response->getBody()->write((string) json_encode(['message' => 'Entry deleted']));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->put('/entries/{id:[0-9]+}', function (Request $request, Response $response, array $args) use ($checkAuth, $checkDb, $lookupEntry, $config): Response {
    $dbResponse = $checkDb($response);
    if ($dbResponse !== null) {
        return $dbResponse;
    }
    $authResponse = $checkAuth($request, $response);
    if ($authResponse !== null) {
        return $authResponse;
    }

    $body = json_decode((string) $request->getBody(), true);
    $subject = isset($body['subject']) ? (string) $body['subject'] : null;
    $bodyText = isset($body['body']) ? (string) $body['body'] : null;

    if ($subject === null && $bodyText === null) {
        $response->getBody()->write((string) json_encode(['error' => 'No fields to update']));
        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    $updated = DatabaseAction::updateEntry($config['db_path'], (int) $args['id'], $subject, $bodyText);
    if ($updated === null) {
        $response->getBody()->write((string) json_encode(['error' => 'Entry not found']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    return $lookupEntry((int) $args['id'], $response);
});

$app->delete('/attachments/{id:[0-9]+}', function (Request $request, Response $response, array $args) use ($checkAuth, $checkDb, $config): Response {
    $dbResponse = $checkDb($response);
    if ($dbResponse !== null) {
        return $dbResponse;
    }
    $authResponse = $checkAuth($request, $response);
    if ($authResponse !== null) {
        return $authResponse;
    }

    $deleted = DatabaseAction::deleteAttachment($config['db_path'], (int) $args['id']);
    if ($deleted === null) {
        $response->getBody()->write((string) json_encode(['error' => 'Attachment not found']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    // Cleanup file from disk
    if (!empty($config['storage_path']) && $deleted['file_path'] !== null) {
        StorageAction::deleteFile($config['storage_path'], $deleted['file_path']);
    }

    $response->getBody()->write((string) json_encode(['message' => 'Attachment deleted']));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/auth', function (Request $request, Response $response) use ($config): Response {
    $method = !empty($config['api_auth_enabled']) ? 'basic' : 'none';
    $response->getBody()->write((string) json_encode(['method' => $method]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/fields', function (Request $request, Response $response) : Response {
    $fields = [
        [
            'name' => '_id',
            'type' => 'int',
            'description' => 'Append mode — attach to an existing entry instead of creating a new one',
            'accepted_values' => [
                ['value' => 1, 'description' => 'ID of the existing entry to append to'],
            ],
        ],
        [
            'name' => '_email',
            'type' => 'bool',
            'description' => 'Set to false to suppress email notification for this request',
            'accepted_values' => [
                ['value' => false, 'description' => 'Suppress email (also accepts string "false", "0", or empty string)'],
                ['value' => true, 'description' => 'Send email (default when omitted, if email_enabled is on)'],
            ],
        ],
        [
            'name' => '_project',
            'type' => 'string',
            'description' => 'Tag the entry with a project name',
            'accepted_values' => [
                ['value' => 'My Project', 'description' => 'Free-text project name to tag the entry with'],
            ],
        ],
    ];
    $response->getBody()->write((string) json_encode($fields));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
