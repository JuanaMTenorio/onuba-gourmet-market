<?php

// Se importan las clases necesarias para preparar Laravel y gestionar las peticiones y los errores
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// Se indica la carpeta raíz de la aplicación, en este caso laravel-app
return Application::configure(basePath: dirname(__DIR__))
    // Se establecen los archivos de rutas y la dirección para comprobar que la aplicación responde
    ->withRouting(
        // Las rutas de las páginas web se definen en routes/web.php
        web: __DIR__.'/../routes/web.php',
        // Los comandos de consola se pueden definir en routes/console.php
        commands: __DIR__.'/../routes/console.php',
        // La dirección /up permite comprobar que la aplicación arranca correctamente
        health: '/up',
    )
    // Aquí se pueden configurar los middleware, que realizan tareas antes o después de atender una petición
    ->withMiddleware(function (Middleware $middleware): void {
        // Actualmente no se añaden ajustes propios en este bloque
    })
    // Aquí se configura cómo se presentan los errores de la aplicación
    ->withExceptions(function (Exceptions $exceptions): void {
        // Los errores se devuelven en formato JSON si la ruta empieza por api/ o la petición espera JSON
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    // Se crea la aplicación con la configuración anterior
    ->create();
