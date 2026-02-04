<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$loader = require __DIR__.'/../vendor/autoload.php';
$loader->addPsr4('MongoDB\\Laravel\\', __DIR__.'/../vendor/mongodb/laravel-mongodb/src');
$loader->addPsr4('MongoDB\\', __DIR__.'/../vendor/mongodb/mongodb/src');

if (!function_exists('MongoDB\\add_logger')) {
    require_once __DIR__.'/../vendor/mongodb/mongodb/src/functions.php';
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'can' => \App\Http\Middleware\CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
