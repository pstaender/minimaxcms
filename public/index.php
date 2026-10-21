<?php

declare(strict_types=1);

use Zeitpulse\MiniMaxCMS\App;
use Zeitpulse\MiniMaxCMS\Config\Config;

// Let PHP's built-in dev server serve existing static files (assets) directly.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';

App::create(Config::load(dirname(__DIR__)))->run();
