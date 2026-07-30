<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();

// On shared hosting without a symlinked public/ folder, the web root is
// deployed to a sibling directory instead of this app's own public/ folder.
$sharedHostingPublicPath = $app->basePath().'/../selbuildi.com';
if (is_dir($sharedHostingPublicPath)) {
    $app->usePublicPath($sharedHostingPublicPath);
}

return $app;
