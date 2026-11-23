<?php

// Se importan las clases necesarias para preparar Laravel y gestionar las peticiones y los errores
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// Empiezo la configuración desde la carpeta raíz de Laravel
$carpetaAplicacion = dirname(__DIR__);
$configuracionAplicacion = Application::configure(basePath: $carpetaAplicacion);

// Indico dónde están las rutas web y los comandos, y registro la dirección de comprobación
$archivoRutasWeb = $carpetaAplicacion . '/routes/web.php';
$archivoComandos = $carpetaAplicacion . '/routes/console.php';
$direccionComprobacion = '/up';

$configuracionAplicacion->withRouting(
    web: $archivoRutasWeb,
    commands: $archivoComandos,
    health: $direccionComprobacion,
);

// Stripe envía el aviso sin el token de los formularios de Onuba
// El controlador de pago comprueba la firma al recibir el aviso
$configurarProteccionFormularios = function (Middleware $middleware): void {
    $rutasSinTokenDeFormulario = [
        'stripe/webhook',
    ];

    $middleware->preventRequestForgery(except: $rutasSinTokenDeFormulario);
};

$configuracionAplicacion->withMiddleware($configurarProteccionFormularios);

// Decido en qué peticiones Laravel debe devolver los errores en JSON
$configurarFormatoErrores = function (Exceptions $configuracionErrores): void {
    $devolverErrorEnJson = function (Request $peticion): bool {
        if ($peticion->is('api/*')) {
            return true;
        }

        return $peticion->expectsJson();
    };

    $configuracionErrores->shouldRenderJsonWhen($devolverErrorEnJson);
};

$configuracionAplicacion->withExceptions($configurarFormatoErrores);

// Creo la aplicación cuando ya he preparado sus rutas y el tratamiento de las peticiones
$aplicacion = $configuracionAplicacion->create();

return $aplicacion;
