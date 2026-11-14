<?php

// Este archivo define las rutas de entrada a mi aplicación (pero la entrada de las peticiones a Laravel es public/index.php).
/* Cuando el usuario entra a www.onubagourmetmarket.es (/), este archivo manda 
una petición a InicioController para que ejecute su método 'index'. */


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\CarritoController;
use App\Http\Controllers\AutenticacionController;

Route::get('/', [InicioController::class, 'index']);

// Ruta para mostrar los productos de una categoría
Route::get('/categoria/{slug}', [InicioController::class, 'categoria']);

// Las consultas usan GET y los cambios del carrito se envían mediante formularios
Route::get('/carrito', [CarritoController::class, 'index'])->name('carrito.index');
Route::post('/carrito/{producto}', [CarritoController::class, 'agregar'])->name('carrito.agregar');
Route::patch('/carrito/{producto}', [CarritoController::class, 'actualizar'])->name('carrito.actualizar');
Route::delete('/carrito/{identificador}', [CarritoController::class, 'eliminar'])->name('carrito.eliminar');

Route::middleware('guest')->group(function (): void {
    Route::view('/registro', 'auth.registro')->name('registro');
    Route::post('/registro', [AutenticacionController::class, 'registrar'])->middleware('throttle:10,1');
    Route::view('/entrar', 'auth.entrar')->name('login');
    Route::post('/entrar', [AutenticacionController::class, 'entrar'])->middleware('throttle:10,1');
});
