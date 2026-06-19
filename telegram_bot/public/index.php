<?php

declare(strict_types=1);

use MeowVpn\TelegramBot\Bootstrap;

require __DIR__.'/../vendor/autoload.php';

$app = Bootstrap::fromEnv();
$app->handle();
