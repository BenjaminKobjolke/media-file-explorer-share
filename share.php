<?php
declare(strict_types=1);

// -- Pre-flight checks (before config or autoloader are available) -----------

$autoloadFile = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadFile)) {
    http_response_code(500);
    echo 'Autoloader not found. Run "composer install" first.';
    exit(1);
}

$configFile = __DIR__ . '/config/app.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo 'Config file not found. Copy config/app.php.example to config/app.php';
    exit(1);
}

require $autoloadFile;
$config = require $configFile;

// -- Debug mode --------------------------------------------------------------

$debug = !empty($config['debug']);

if ($debug) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// -- Run ---------------------------------------------------------------------

try {
    (new App\WebhookHandler($config))->run();
} catch (\Throwable $e) {
    if ($debug) {
        http_response_code(500);
        echo "Exception: " . $e->getMessage() . "\n"
           . "File: " . $e->getFile() . ':' . $e->getLine() . "\n"
           . $e->getTraceAsString();
        exit(1);
    }
    throw $e;
}
