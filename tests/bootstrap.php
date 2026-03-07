<?php

declare(strict_types=1);

// Standalone (CI) or embedded (BridgeIt)
foreach ([
    __DIR__.'/../vendor/autoload.php',
    dirname(__DIR__, 4).'/vendor/autoload.php',
] as $autoloader) {
    if (file_exists($autoloader)) {
        require_once $autoloader;
        break;
    }
}
