<?php
declare(strict_types=1);

// Expects $app and $config in scope (provided by api.php require).

use App\Actions\DatabaseAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/field-options/{field:[a-z][a-z_]*}', function (Request $request, Response $response, array $args) use ($config): Response {
    $field = DatabaseAction::getCustomFieldByName($config['db_path'], $args['field']);
    if ($field === null) {
        $response->getBody()->write((string) json_encode(['error' => 'Custom field not found']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    $options = DatabaseAction::getAllOptions($config['db_path'], $args['field']);
    $response->getBody()->write((string) json_encode($options));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/field-options/{field:[a-z][a-z_]*}', function (Request $request, Response $response, array $args) use ($config): Response {
    $field = DatabaseAction::getCustomFieldByName($config['db_path'], $args['field']);
    if ($field === null) {
        $response->getBody()->write((string) json_encode(['error' => 'Custom field not found']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    $body = json_decode((string) $request->getBody(), true);
    $name = isset($body['name']) ? trim((string) $body['name']) : '';
    if ($name === '') {
        $response->getBody()->write((string) json_encode(['error' => 'Option name is required']));
        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    try {
        $id = DatabaseAction::createOption($config['db_path'], $args['field'], $name);
    } catch (\PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            $response->getBody()->write((string) json_encode(['error' => 'Option name already exists for this field']));
            return $response
                ->withStatus(409)
                ->withHeader('Content-Type', 'application/json');
        }
        throw $e;
    }

    $option = DatabaseAction::getOptionById($config['db_path'], $args['field'], $id);
    $response->getBody()->write((string) json_encode($option));
    return $response
        ->withStatus(201)
        ->withHeader('Content-Type', 'application/json');
});

$app->put('/field-options/{field:[a-z][a-z_]*}/{id:[0-9]+}', function (Request $request, Response $response, array $args) use ($config): Response {
    $field = DatabaseAction::getCustomFieldByName($config['db_path'], $args['field']);
    if ($field === null) {
        $response->getBody()->write((string) json_encode(['error' => 'Custom field not found']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    $body = json_decode((string) $request->getBody(), true);
    $name = isset($body['name']) ? trim((string) $body['name']) : '';
    if ($name === '') {
        $response->getBody()->write((string) json_encode(['error' => 'Option name is required']));
        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    try {
        $updated = DatabaseAction::updateOption($config['db_path'], $args['field'], (int) $args['id'], $name);
    } catch (\PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            $response->getBody()->write((string) json_encode(['error' => 'Option name already exists for this field']));
            return $response
                ->withStatus(409)
                ->withHeader('Content-Type', 'application/json');
        }
        throw $e;
    }

    if ($updated === null) {
        $response->getBody()->write((string) json_encode(['error' => 'Option not found']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write((string) json_encode($updated));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->delete('/field-options/{field:[a-z][a-z_]*}/{id:[0-9]+}', function (Request $request, Response $response, array $args) use ($config): Response {
    $field = DatabaseAction::getCustomFieldByName($config['db_path'], $args['field']);
    if ($field === null) {
        $response->getBody()->write((string) json_encode(['error' => 'Custom field not found']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    $deleted = DatabaseAction::deleteOption($config['db_path'], $args['field'], (int) $args['id']);
    if (!$deleted) {
        $response->getBody()->write((string) json_encode(['error' => 'Option not found']));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write((string) json_encode(['message' => 'Option deleted']));
    return $response->withHeader('Content-Type', 'application/json');
});
