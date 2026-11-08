<?php

// Archivo incluido por defecto en Laravel que recibe las peticiones web de la aplicación

// Se importan las clases de Laravel que se utilizan en este archivo
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// Se guarda el momento en el que empieza a ejecutarse Laravel para poder medir el tiempo que tarda
define('LARAVEL_START', microtime(true));

// Se comprueba si existe el archivo del modo mantenimiento y, si existe, se carga
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Se carga el sistema de Composer que permite encontrar las clases de Laravel y sus dependencias
require __DIR__.'/../vendor/autoload.php';

// Se prepara la aplicación cargando su archivo de arranque
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

// Se recoge la petición del navegador y se entrega a Laravel para que busque la ruta y devuelva la respuesta
$app->handleRequest(Request::capture());
