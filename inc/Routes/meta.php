<?php
declare(strict_types=1);

// Expects $app and $config in scope (provided by api.php require).

use App\Actions\DatabaseAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/auth', function (Request $request, Response $response) use ($config): Response {
    $method = !empty($config['auth_enabled']) ? 'basic' : 'none';
    $response->getBody()->write((string) json_encode(['method' => $method]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/fields', function (Request $request, Response $response) use ($config): Response {
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
            'description' => 'Send email notification',
            'accepted_values' => [
                ['value' => false, 'description' => 'Suppress email (also accepts string "false", "0", or empty string)'],
                ['value' => true, 'description' => 'Send email (default when omitted, if email_enabled is on)'],
            ],
        ],
    ];

    // Auto-discover custom fields from database
    if (!empty($config['db_enabled'])) {
        $customFields = DatabaseAction::getAllCustomFields($config['db_path']);
        foreach ($customFields as $cf) {
            $fields[] = [
                'name' => '_' . $cf['name'],
                'type' => 'int',
                'description' => $cf['description'] ?? ('Tag the entry with a ' . $cf['name']),
                'accepted_values' => [
                    ['value' => 1, 'description' => 'ID of the ' . $cf['name'] . ' option (must exist)'],
                ],
                'resource' => [
                    'name' => $cf['name'],
                    'path' => '/field-options/' . $cf['name'],
                ],
            ];
        }
    }

    $response->getBody()->write((string) json_encode($fields));
    return $response->withHeader('Content-Type', 'application/json');
});
