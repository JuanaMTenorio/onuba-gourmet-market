<?php

// Este archivo define las rutas de entrada a mi aplicación (pero la entrada de las peticiones a Laravel es public/index.php).
/* Cuando el usuario entra a www.onubagourmetmarket.es (/), este archivo manda 
una petición a InicioController para que ejecute su método 'index'. */


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\CarritoController;
use App\Http\Controllers\AutenticacionController;
use App\Http\Controllers\PagoController;

Route::get('/', [InicioController::class, 'index']);

// Ruta para mostrar los productos de una categoría
Route::get('/categoria/{slug}', [InicioController::class, 'categoria']);

// Las consultas usan GET y los cambios del carrito se envían mediante formularios
Route::get('/carrito', [CarritoController::class, 'index'])->name('carrito.index');
$rutaAgregarProducto = Route::post('/carrito/{producto}', [CarritoController::class, 'agregar']);
$rutaAgregarProducto->whereNumber('producto');
$rutaAgregarProducto->name('carrito.agregar');
Route::patch('/carrito/{producto}', [CarritoController::class, 'actualizar'])->name('carrito.actualizar');
Route::delete('/carrito/{identificador}', [CarritoController::class, 'eliminar'])
    ->whereNumber('identificador')->name('carrito.eliminar');

// Solo un cliente con la sesión iniciada puede cerrarla
Route::post('/salir', [AutenticacionController::class, 'salir'])->middleware('auth')->name('logout');

$rutasDePago = Route::middleware('auth');
// Función: Registrar las rutas de pago que requieren iniciar sesión
$rutasDePago->group(function (): void {
    $rutaIniciarPago = Route::post('/carrito/pago', [PagoController::class, 'iniciar']);
    $rutaIniciarPago->middleware('throttle:10,1');
    $rutaIniciarPago->name('carrito.pago.iniciar');
    Route::post('/carrito/pago/modificar', [PagoController::class, 'modificar'])
        ->name('carrito.pago.modificar');
    Route::get('/carrito/pago/resultado', [PagoController::class, 'resultado'])
        ->name('carrito.pago.resultado');
});

// Stripe llama a esta ruta directamente para avisar del resultado del pago
Route::post('/stripe/webhook', [PagoController::class, 'webhook']);

// Función: Registrar las rutas de acceso para quienes aún no han iniciado sesión
Route::middleware('guest')->group(function (): void {
    Route::view('/registro', 'auth.registro')->name('registro');
    Route::post('/registro', [AutenticacionController::class, 'registrar'])->middleware('throttle:10,1');
    Route::view('/entrar', 'auth.entrar')->name('login');
    Route::post('/entrar', [AutenticacionController::class, 'entrar'])->middleware('throttle:10,1');
});

// Función: Mostrar la demostración de 5,00 € y consultar el resultado al volver de Stripe
Route::get('/stripe-prueba', function () {
    if (! app()->environment('local')) {
        abort(404);
    }

    $clavePublicable = config('services.stripe.publicable');
    $configurada = is_string($clavePublicable) && str_starts_with($clavePublicable, 'pk_test_');
    $mensaje = null;
    $peticion = request();
    $idSesion = $peticion->query('sesion');
    $idSesionGuardada = session('stripe_prueba_sesion');

    if (is_string($idSesion) && $idSesion === $idSesionGuardada) {
        $clave = config('services.stripe.secret');

        if (is_string($clave) && str_starts_with($clave, 'sk_test_')) {
            try {
                $stripe = new \Stripe\StripeClient($clave);
                $checkout = $stripe->checkout;
                $sesionesStripe = $checkout->sessions;
                $sesion = $sesionesStripe->retrieve($idSesion);
                $mensaje = 'Stripe todavía no ha confirmado el pago de prueba.';

                if ($sesion->payment_status === 'paid') {
                    $mensaje = 'Stripe ha confirmado el pago de prueba de 5,00 €.';
                }
            } catch (\Stripe\Exception\ApiErrorException $error) {
                $mensaje = 'No se pudo consultar el pago en Stripe. Recarga la página para volver a comprobarlo.';
            }
        }
    }

    return view('stripe-prueba', [
        'clavePublicable' => $clavePublicable,
        'configurada' => $configurada,
        'mensaje' => $mensaje,
    ]);
});

// Función: Abrir una sesión de Stripe para la demostración de 5,00 €
$rutaAbrirPrueba = Route::post('/stripe-prueba', function () {
    if (! app()->environment('local')) {
        abort(404);
    }

    $clave = config('services.stripe.secret');
    if (! is_string($clave) || ! str_starts_with($clave, 'sk_test_')) {
        abort(500, 'Configura una clave secreta de pruebas de Stripe.');
    }

    $stripe = new \Stripe\StripeClient($clave);
    $precioDePrueba = [
        'currency' => 'eur',
        'product_data' => ['name' => 'Producto de prueba de Onuba'],
        'unit_amount' => 500,
    ];
    $lineaDePrueba = [
        'price_data' => $precioDePrueba,
        'quantity' => 1,
    ];

    try {
        $checkout = $stripe->checkout;
        $sesionesStripe = $checkout->sessions;
        $datosSesion = [
            'mode' => 'payment',
            'ui_mode' => 'embedded_page',
            'line_items' => [$lineaDePrueba],
            'return_url' => url('/stripe-prueba') . '?sesion={CHECKOUT_SESSION_ID}',
        ];
        $sesion = $sesionesStripe->create($datosSesion);
    } catch (\Stripe\Exception\ApiConnectionException $error) {
        return response()->json(['message' => 'Onuba no pudo conectar con Stripe. Vuelve a intentarlo.'], 503);
    } catch (\Stripe\Exception\ApiErrorException $error) {
        return response()->json(['message' => 'Stripe no pudo abrir la sesión de prueba. Revisa el registro de peticiones en el panel de Stripe.'], 502);
    }

    session(['stripe_prueba_sesion' => $sesion->id]);

    return response()->json(['clientSecret' => $sesion->client_secret]);
});
$rutaAbrirPrueba->middleware('throttle:10,1');
