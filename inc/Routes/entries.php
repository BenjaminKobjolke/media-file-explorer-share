<?php
declare(strict_types=1);

// Expects $app, $config and $basePath in scope (provided by api.php require).

use App\Actions\DatabaseAction;
use App\Actions\StorageAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// -- Entry lookup helper (used only by entry routes) -------------------------

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

// -- Entry routes ------------------------------------------------------------

$app->get('/entries/{id:[0-9]+}', function (Request $request, Response $response, array $args) use ($lookupEntry): Response {
    return $lookupEntry((int) $args['id'], $response);
});

$app->post('/entries', function (Request $request, Response $response) use ($lookupEntry): Response {
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body) || !isset($body['id'])) {
        $response->getBody()->write((string) json_encode(['error' => 'Missing id in request body']));
        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    return $lookupEntry((int) $body['id'], $response);
});

$app->get('/entries/{id:[0-9]+}/file', function (Request $request, Response $response, array $args) use ($config): Response {
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

$app->get('/files/{id:[0-9]+}', function (Request $request, Response $response, array $args) use ($config): Response {
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

$app->get('/entries', function (Request $request, Response $response) use ($config): Response {
    $params = $request->getQueryParams();
    $page = max(1, (int) ($params['page'] ?? 1));
    $perPage = max(1, min(100, (int) ($params['per_page'] ?? 20)));

    // Extract dynamic custom field filters (e.g. status_id=1, project_id=1)
    $fieldFilters = [];
    $customFields = DatabaseAction::getAllCustomFields($config['db_path']);
    foreach ($customFields as $cf) {
        $filterKey = $cf['name'] . '_id';
        if (isset($params[$filterKey])) {
            $fieldFilters[$cf['name']] = (int) $params[$filterKey];
        }
    }

    $result = DatabaseAction::getAllPaginated($config['db_path'], $page, $perPage, $fieldFilters);
    $response->getBody()->write((string) json_encode($result));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->delete('/entries/{id:[0-9]+}', function (Request $request, Response $response, array $args) use ($config): Response {
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

$app->put('/entries/{id:[0-9]+}', function (Request $request, Response $response, array $args) use ($lookupEntry, $config): Response {
    $body = json_decode((string) $request->getBody(), true);
    $subject = isset($body['subject']) ? (string) $body['subject'] : null;
    $bodyText = isset($body['body']) ? (string) $body['body'] : null;

    // Extract dynamic custom field values (e.g. status_id, resolution_id)
    $fieldValues = [];
    $customFields = DatabaseAction::getAllCustomFields($config['db_path']);
    foreach ($customFields as $cf) {
        $fieldKey = $cf['name'] . '_id';
        if (isset($body[$fieldKey])) {
            $optId = (int) $body[$fieldKey];
            $option = DatabaseAction::getOptionById($config['db_path'], $cf['name'], $optId);
            if ($option === null) {
                $response->getBody()->write((string) json_encode(['error' => ucfirst($cf['name']) . ' option not found']));
                return $response
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json');
            }
            $fieldValues[$cf['name']] = $optId;
        }
    }

    if ($subject === null && $bodyText === null && empty($fieldValues)) {
        $response->getBody()->write((string) json_encode(['error' => 'No fields to update']));
        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    $updated = DatabaseAction::updateEntry($config['db_path'], (int) $args['id'], $subject, $bodyText, $fieldValues);
    if ($updated === null) {
        $response->getBody()->write((string) json_encode(['error' => 'Entry not found']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    return $lookupEntry((int) $args['id'], $response);
});

$app->delete('/attachments/{id:[0-9]+}', function (Request $request, Response $response, array $args) use ($config): Response {
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
