<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/config/app.php';

(new App\WebhookHandler($config))->run();
