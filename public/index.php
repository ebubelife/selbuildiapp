<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// On shared hosting, this public/ folder's contents are deployed to a
// sibling web-accessible directory while the rest of the app lives in
// "selbuildi-app" next to it, instead of this folder's own parent.
$appBase = is_dir(__DIR__.'/../vendor') ? __DIR__.'/..' : __DIR__.'/../selbuildi-app';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $appBase.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $appBase.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $appBase.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
