<?php
declare(strict_types=1);

// Expects $app and $config in scope (provided by api.php require).

use App\Actions\DatabaseAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/custom-fields', function (Request $request, Response $response) use ($config): Response {
    $fields = DatabaseAction::getAllCustomFields($config['db_path']);
    $response->getBody()->write((string) json_encode($fields));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/custom-fields', function (Request $request, Response $response) use ($config): Response {
    $body = json_decode((string) $request->getBody(), true);
    $name = isset($body['name']) ? trim((string) $body['name']) : '';
    if ($name === '') {
        $response->getBody()->write((string) json_encode(['error' => 'Field name is required']));
        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    // Validate name format: lowercase letters and underscores only
    if (!preg_match('/^[a-z][a-z_]*$/', $name)) {
        $response->getBody()->write((string) json_encode(['error' => 'Field name must be lowercase letters and underscores only, starting with a letter']));
        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    $description = isset($body['description']) ? trim((string) $body['description']) : '';
    $sortOrder = isset($body['sort_order']) ? (int) $body['sort_order'] : 0;

    try {
        DatabaseAction::createCustomField($config['db_path'], $name, $description, $sortOrder);
    } catch (\PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            $response->getBody()->write((string) json_encode(['error' => 'Custom field already exists']));
            return $response
                ->withStatus(409)
                ->withHeader('Content-Type', 'application/json');
        }
        throw $e;
    }

    $field = DatabaseAction::getCustomFieldByName($config['db_path'], $name);
    $response->getBody()->write((string) json_encode($field));
    return $response
        ->withStatus(201)
        ->withHeader('Content-Type', 'application/json');
});

$app->get('/custom-fields/export', function (Request $request, Response $response) use ($config): Response {
    $export = DatabaseAction::exportCustomFields($config['db_path']);
    $response->getBody()->write((string) json_encode($export));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/custom-fields/import', function (Request $request, Response $response) use ($config): Response {
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body) || !isset($body['fields']) || !is_array($body['fields'])) {
        $response->getBody()->write((string) json_encode(['error' => 'Request body must contain a "fields" array']));
        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    $result = DatabaseAction::importCustomFields($config['db_path'], $body['fields']);
    $response->getBody()->write((string) json_encode($result));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->put('/custom-fields/{name:[a-z][a-z_]*}', function (Request $request, Response $response, array $args) use ($config): Response {
    $body = json_decode((string) $request->getBody(), true);
    $description = isset($body['description']) ? (string) $body['description'] : null;
    $sortOrder = isset($body['sort_order']) ? (int) $body['sort_order'] : null;

    if ($description === null && $sortOrder === null) {
        $response->getBody()->write((string) json_encode(['error' => 'No fields to update']));
        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    $updated = DatabaseAction::updateCustomField($config['db_path'], $args['name'], $description, $sortOrder);
    if ($updated === null) {
        $response->getBody()->write((string) json_encode(['error' => 'Custom field not found']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write((string) json_encode($updated));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->delete('/custom-fields/{name:[a-z][a-z_]*}', function (Request $request, Response $response, array $args) use ($config): Response {
    $deleted = DatabaseAction::deleteCustomField($config['db_path'], $args['name']);
    if (!$deleted) {
        $response->getBody()->write((string) json_encode(['error' => 'Custom field not found']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write((string) json_encode(['message' => 'Custom field deleted']));
    return $response->withHeader('Content-Type', 'application/json');
});
