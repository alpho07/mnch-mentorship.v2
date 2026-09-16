<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Trust proxies — your existing config, unchanged
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB
        );

        // Middleware alias used in routes/api.php
        $middleware->alias([
            'api.active' => \App\Http\Middleware\EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Only log genuine server errors here — 4xx responses
        // (403s from authorization checks, 404s from route
        // model binding, 409s from validation, etc.) are
        // expected application flow, not failures, and
        // logging every one of them as an ERROR under the
        // misleading "Upload Error" label buried real
        // problems in noise (this fired on every request
        // that hit an expected 4xx, app-wide, not just
        // uploads).
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if ($e->getStatusCode() >= 500) {
                \Log::error('HTTP Exception: '.$e->getStatusCode().' - '.$e->getMessage());
            }
        });
    })
    ->create();
