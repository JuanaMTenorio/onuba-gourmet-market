<?php

// Este archivo define las rutas de entrada a mi aplicación (pero la entrada de las peticiones a Laravel es public/index.php).
/* Cuando el usuario entra a www.onubagourmetmarket.es (/), este archivo manda 
una petición a InicioController para que ejecute su método 'index'. */


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InicioController;

Route::get('/', [InicioController::class, 'index']);

// Ruta para mostrar los productos de una categoría
Route::get('/categoria/{slug}', [InicioController::class, 'categoria']);
