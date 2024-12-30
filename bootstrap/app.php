<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\LocalizationMiddleware::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'member/uploadKyc',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response) {
            switch ($response->getStatusCode()) {
                case 400:
                    return Inertia::render('Errors/400');
                case 401:
                case 419:
                    return Inertia::render('Errors/401');
                case 403:
                    return Inertia::render('Errors/403');
                case 404:
                    return Inertia::render('Errors/404');
                case 408:
                    return Inertia::render('Errors/408');
                case 500:
                    if (app()->environment('production')) {
                        return Inertia::render('Errors/500');
                    }
                    return $response;
                case 502:
                    return Inertia::render('Errors/502');
                case 503:
                    return Inertia::render('Errors/503');
                case 504:
                    return Inertia::render('Errors/504');
                default:
                    return $response;
            }
        });
    })->create();
