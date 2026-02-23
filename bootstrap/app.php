<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Registrar middleware para forzar HTTPS - TEMPORALMENTE DESHABILITADO
        // $middleware->append(\App\Http\Middleware\ForceHttps::class);

        // Trust all proxies to work behind any proxy or load balancer
        $middleware->trustProxies(at: '*');

        // Redirecciones consistentes para guest/auth
        $middleware->redirectUsersTo('/produccionProceso');
        $middleware->redirectGuestsTo('/login');

        // Middleware para establecer contexto de SQL Server antes de queries
        // Esto permite que los triggers capturen informacion del usuario
        $middleware->web(append: [
            \App\Http\Middleware\SetSqlContextInfo::class,
            \App\Http\Middleware\ProgramaTejidoContext::class,
            \App\Http\Middleware\NoCacheHtmlResponses::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
