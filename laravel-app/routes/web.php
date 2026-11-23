<?php

use Illuminate\Support\Facades\Route;

// Las peticiones entran por public/index.php y Laravel busca aquí la ruta que corresponde a la URL
$nombreClaseControladorInicio = 'App\Http\Controllers\InicioController';
$nombreClaseControladorCarrito = 'App\Http\Controllers\CarritoController';
$nombreClaseControladorAutenticacion = 'App\Http\Controllers\AutenticacionController';
$nombreClaseControladorPago = 'App\Http\Controllers\PagoController';

// Indico la clase y el método que muestran la página de inicio
$accionMostrarInicio = [
    $nombreClaseControladorInicio,
    'index',
];
Route::get('/', $accionMostrarInicio);

// Laravel pasa {slug} al método que muestra los productos de una categoría
$accionMostrarCategoria = [
    $nombreClaseControladorInicio,
    'categoria',
];
Route::get('/categoria/{slug}', $accionMostrarCategoria);

// Uso GET para mostrar el carrito
$accionMostrarCarrito = [
    $nombreClaseControladorCarrito,
    'index',
];
$rutaMostrarCarrito = Route::get('/carrito', $accionMostrarCarrito);
$rutaMostrarCarrito->name('carrito.index');

// Uso POST para añadir un producto al carrito
$accionAgregarProducto = [
    $nombreClaseControladorCarrito,
    'agregar',
];
$rutaAgregarProducto = Route::post('/carrito/{producto}', $accionAgregarProducto);
$rutaAgregarProducto->whereNumber('producto');
$rutaAgregarProducto->name('carrito.agregar');

// Uso PATCH para cambiar la cantidad de un producto
$accionActualizarProducto = [
    $nombreClaseControladorCarrito,
    'actualizar',
];
$rutaActualizarProducto = Route::patch('/carrito/{producto}', $accionActualizarProducto);
$rutaActualizarProducto->name('carrito.actualizar');

// Uso DELETE para retirar un producto del carrito
$accionEliminarProducto = [
    $nombreClaseControladorCarrito,
    'eliminar',
];
$rutaEliminarProducto = Route::delete('/carrito/{identificador}', $accionEliminarProducto);
$rutaEliminarProducto->whereNumber('identificador');
$rutaEliminarProducto->name('carrito.eliminar');

// Exijo que el cliente tenga la sesión iniciada para poder cerrarla
$accionCerrarSesion = [
    $nombreClaseControladorAutenticacion,
    'salir',
];
$rutaCerrarSesion = Route::post('/salir', $accionCerrarSesion);
$rutaCerrarSesion->middleware('auth');
$rutaCerrarSesion->name('logout');

// Las rutas del grupo requieren que el cliente haya iniciado sesión
$rutasDePago = Route::middleware('auth');

// Con use pongo el nombre del controlador a disposición de la función del grupo
$rutasDePago->group(function () use ($nombreClaseControladorPago): void {
    // Recibo los datos del formulario e inicio el pago
    $accionIniciarPago = [
        $nombreClaseControladorPago,
        'iniciar',
    ];
    $rutaIniciarPago = Route::post('/carrito/pago', $accionIniciarPago);
    $rutaIniciarPago->middleware('throttle:10,1');
    $rutaIniciarPago->name('carrito.pago.iniciar');

    // Atiendo la petición del cliente para volver a modificar su pedido
    $accionModificarPedido = [
        $nombreClaseControladorPago,
        'modificar',
    ];
    $rutaModificarPedido = Route::post('/carrito/pago/modificar', $accionModificarPedido);
    $rutaModificarPedido->name('carrito.pago.modificar');

    // Registro la ruta a la que vuelve el cliente después del pago
    $nombreMetodoResultadoPago = 'resultado';

    $accionResultadoPago = [
        $nombreClaseControladorPago,
        $nombreMetodoResultadoPago,
    ];

    $rutaResultadoPago = Route::get('/carrito/pago/resultado', $accionResultadoPago);
    $rutaResultadoPago->name('carrito.pago.resultado');

    // Escribo el nombre completo de la clase que se encarga del PDF
    $nombreClaseControlador = 'App\Http\Controllers\PedidoPdfController';

    // Escribo el nombre del método que Laravel debe llamar dentro de esa clase
    $nombreMetodoDescarga = 'descargarJustificante';

    // Laravel espera estos dos datos en orden, primero la clase y después el método
    $accionDescargarJustificante = [
        $nombreClaseControlador,
        $nombreMetodoDescarga,
    ];

    $direccionDescargarJustificante = '/pedidos/{pedido}/pdf';
    $rutaDescargarJustificante = Route::get($direccionDescargarJustificante, $accionDescargarJustificante);

    // El número de pedido de la URL solo puede contener dígitos
    $rutaDescargarJustificante->whereNumber('pedido');
    $rutaDescargarJustificante->name('pedidos.pdf');

    // Uso POST porque pulsar el botón provoca el envío de un correo
    $nombreMetodoEnvio = 'enviarEmailJustificante';
    $accionEnviarJustificante = [
        $nombreClaseControlador,
        $nombreMetodoEnvio,
    ];

    $direccionEnviarJustificante = '/pedidos/{pedido}/enviar-justificante';
    $rutaEnviarJustificante = Route::post($direccionEnviarJustificante, $accionEnviarJustificante);
    $rutaEnviarJustificante->whereNumber('pedido');

    // Limito cuántas solicitudes de envío puede hacer el cliente por minuto
    $rutaEnviarJustificante->middleware('throttle:3,1');
    $rutaEnviarJustificante->name('pedidos.enviar-justificante');
});

// Stripe llama a esta ruta directamente para avisar del resultado del pago
$accionRecibirAvisoStripe = [
    $nombreClaseControladorPago,
    'webhook',
];
Route::post('/stripe/webhook', $accionRecibirAvisoStripe);

// Las rutas de registro y entrada quedan disponibles para quienes no han iniciado sesión
$rutasDeInvitados = Route::middleware('guest');

$rutasDeInvitados->group(function () use ($nombreClaseControladorAutenticacion): void {
    // Muestro el formulario de registro
    $rutaMostrarRegistro = Route::view('/registro', 'auth.registro');
    $rutaMostrarRegistro->name('registro');
    // Recibo el formulario de registro
    $accionRegistrarUsuario = [
        $nombreClaseControladorAutenticacion,
        'registrar',
    ];
    $rutaRegistrarUsuario = Route::post('/registro', $accionRegistrarUsuario);
    $rutaRegistrarUsuario->middleware('throttle:10,1');

    // Muestro el formulario de inicio de sesión
    $rutaMostrarInicioSesion = Route::view('/entrar', 'auth.entrar');
    $rutaMostrarInicioSesion->name('login');
    // Recibo el formulario de inicio de sesión
    $accionIniciarSesion = [
        $nombreClaseControladorAutenticacion,
        'entrar',
    ];
    $rutaIniciarSesion = Route::post('/entrar', $accionIniciarSesion);
    $rutaIniciarSesion->middleware('throttle:10,1');
});
