<?php

// Archivo incluido por defecto en Laravel que recibe las peticiones web de la aplicación

// Se importan las clases de Laravel que se utilizan en este archivo
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// Se guarda el momento en el que empieza a ejecutarse Laravel para poder medir el tiempo que tarda
$inicioEjecucion = microtime(true);
define('LARAVEL_START', $inicioEjecucion);

// Se comprueba si existe el archivo del modo mantenimiento y, si existe, se carga
$archivoMantenimiento = __DIR__ . '/../storage/framework/maintenance.php';
if (file_exists($archivoMantenimiento)) {
    require $archivoMantenimiento;
}

// Se carga el sistema de Composer que permite encontrar las clases de Laravel y sus dependencias
$archivoCargadorComposer = __DIR__ . '/../vendor/autoload.php';
require $archivoCargadorComposer;

// Se prepara la aplicación cargando su archivo de arranque
$archivoArranque = __DIR__ . '/../bootstrap/app.php';
/** @var Application $aplicacion */
$aplicacion = require_once $archivoArranque;

// Recojo la petición del navegador y la entrego a Laravel para que la atienda
$peticion = Request::capture();
$aplicacion->handleRequest($peticion);
