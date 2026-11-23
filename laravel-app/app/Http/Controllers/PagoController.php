<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Usuario;
use App\Services\Carrito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PagoController extends Controller
{
    // Guardo el pedido y devuelvo el código que permite abrir el formulario de Stripe
    public function iniciar(Request $peticion, Carrito $carrito) {

        $clienteStripe = $this->crearClienteStripe();
        $carrito->incorporarVisitante($peticion);

        // El servicio ejecuta este bloque mientras protege el carrito del cliente
        $operacionIniciarPago = function () use ($peticion, $carrito, $clienteStripe) {
            $usuario = $peticion->user();
            $identificadorUsuario = $usuario->id;

            $pedidoPendiente = $carrito->pedidoPendiente($peticion);
            $hayPagoAbierto = $carrito->hayPagoAbierto($pedidoPendiente);
            $respuesta = response();

            try {
                // Si el intento sigue abierto, devuelvo el mismo código de pago
                if ($hayPagoAbierto) {
                    $servicioCheckout = $clienteStripe->checkout;
                    $sesionesStripe = $servicioCheckout->sessions;
                    $identificadorSesionStripe = $pedidoPendiente->stripe_checkout_session_id;
                    $sesionStripe = $this->reintentarConexionStripe(
                        fn () => $sesionesStripe->retrieve($identificadorSesionStripe),
                    );

                    if ($sesionStripe->status !== 'open') {
                        $this->actualizarEstado($sesionStripe);

                        $datosRespuesta = [
                            'message' => 'El intento anterior ha cambiado de estado. Recarga el carrito para continuar.',
                        ];

                        return $respuesta->json($datosRespuesta, 409);
                    }

                    $datosRespuesta = [
                        'clientSecret' => $sesionStripe->client_secret,
                    ];

                    return $respuesta->json($datosRespuesta);
                }

                // Valido los datos de envío y el total que se mostraba en el carrito
                $reglasEnvio = [
                    'nombre' => ['required', 'string', 'max:100'],
                    'apellidos' => ['required', 'string', 'max:150'],
                    'email' => ['required', 'email', 'max:255'],
                    'telefono' => ['required', 'string', 'max:20'],
                    'direccion' => ['required', 'string', 'max:255'],
                    'codigo_postal' => ['required', 'string', 'max:10'],
                    'localidad' => ['required', 'string', 'max:100'],
                    'provincia' => ['required', 'string', 'max:100'],
                    'total_mostrado' => ['required', 'integer'],
                ];

                $datosEnvio = $peticion->validate($reglasEnvio);

                // Calculo el importe con el carrito guardado y lo comparo con el que vio el cliente
                $cantidadesCarrito = $carrito->leer($peticion);
                $carritoCalculado = $this->prepararLineas($cantidadesCarrito);
                $lineasPedido = $carritoCalculado['lineas'];
                $totalCentimos = $carritoCalculado['totalCentimos'];

                $totalMostrado = (int) $datosEnvio['total_mostrado'];

                if ($totalCentimos !== $totalMostrado) {
                    $errores = [
                        'pago' => 'El total del carrito ha cambiado. Recarga la página y revisa el importe.',
                    ];

                    throw ValidationException::withMessages($errores);
                }

                // Guardo los datos de envío y los importes antes de pedir la sesión a Stripe
                $totalEuros = $this->convertirCentimosAEuros($totalCentimos);
                $fechaActualizacion = now();

                $datosPedido = [
                    'nombre_envio' => $datosEnvio['nombre'],
                    'apellidos_envio' => $datosEnvio['apellidos'],
                    'email_envio' => $datosEnvio['email'],
                    'telefono_envio' => $datosEnvio['telefono'],
                    'direccion_envio' => $datosEnvio['direccion'],
                    'codigo_postal_envio' => $datosEnvio['codigo_postal'],
                    'localidad_envio' => $datosEnvio['localidad'],
                    'provincia_envio' => $datosEnvio['provincia'],
                    'total' => $totalEuros,
                    'updated_at' => $fechaActualizacion,
                ];

                $identificadorPedido = $this->guardarPedido(
                    $pedidoPendiente,
                    $identificadorUsuario,
                    $datosPedido,
                    $lineasPedido,
                );

                $correoEnvio = $datosEnvio['email'];
                $sesionStripe = $this->crearSesionPago($clienteStripe, $identificadorPedido, $correoEnvio);

                // Relaciono la sesión de Stripe con el pedido guardado en MySQL
                $datosSesionStripe = [
                    'stripe_checkout_session_id' => $sesionStripe->id,
                ];
                $consultaPedido = DB::table('pedidos');
                $consultaPedido->where('id', $identificadorPedido);
                $consultaPedido->update($datosSesionStripe);

                $datosRespuesta = [
                    'clientSecret' => $sesionStripe->client_secret,
                ];

                return $respuesta->json($datosRespuesta);
            } catch (ApiErrorException $error) {
                preg_match('/Network error \[errno (\d+)\]/', $error->getMessage(), $errorRed);
                Log::warning('No se pudo preparar la sesión de Stripe.', [
                    'operacion' => $hayPagoAbierto ? 'recuperar' : 'crear',
                    'tipo' => $error::class,
                    'codigo_red' => $errorRed[1] ?? null,
                    'estado_http' => $error->getHttpStatus(),
                    'codigo_stripe' => $error->getStripeCode(),
                    'peticion_stripe' => $error->getRequestId(),
                ]);

                $mensaje = $error instanceof ApiConnectionException
                    ? 'No se ha podido conectar con Stripe. Vuelve a pulsar el botón de pago en unos segundos.'
                    : 'No se pudo preparar el pago en Stripe. Inténtalo de nuevo.';
                $datosRespuesta = [
                    'message' => $mensaje,
                ];

                return $respuesta->json($datosRespuesta, 502);
            }
        };

        return $carrito->modificar($peticion, $operacionIniciarPago);
    }

    // Guardo los datos de envío y las líneas del pedido en MySQL
    private function guardarPedido(?object $pedidoPendiente, int $identificadorUsuario, array $datosPedido, array $lineasPedido): int
    {
        $consultaPedido = DB::table('pedidos');

        if ($pedidoPendiente === null) {
            // Añado los datos que solo hacen falta al crear un pedido nuevo
            $datosPedido['usuario_id'] = $identificadorUsuario;
            $datosPedido['estado'] = 'pendiente';
            $datosPedido['fecha'] = now();
            $datosPedido['created_at'] = now();
            $identificadorPedido = $consultaPedido->insertGetId($datosPedido);
        } else {
            // Actualizo el pedido existente y reemplazo las líneas con el carrito actual
            $identificadorPedido = $pedidoPendiente->id;
            $consultaPedido->where('id', $identificadorPedido);
            $consultaPedido->update($datosPedido);

            $consultaDetalles = DB::table('detalles_pedido');
            $consultaDetalles->where('pedido_id', $identificadorPedido);
            $consultaDetalles->delete();
        }

        // Cada línea queda vinculada al pedido mediante la columna pedido_id
        $tablaDetallesPedido = DB::table('detalles_pedido');
        foreach ($lineasPedido as $lineaPedido) {
            $lineaPedido['pedido_id'] = $identificadorPedido;
            $lineaPedido['created_at'] = now();
            $lineaPedido['updated_at'] = now();

            $tablaDetallesPedido->insert($lineaPedido);
        }

        return $identificadorPedido;
    }

    // Creo en Stripe una sesión de pago por el importe guardado en el pedido
    private function crearSesionPago(StripeClient $clienteStripe, int $identificadorPedido, string $correoEnvio): Session
    {
        $sqlTotalPedido = '
            SELECT total
            FROM pedidos
            WHERE id = ?
        ';

        $parametrosTotalPedido = [
            $identificadorPedido,
        ];

        $totalGuardado = DB::scalar($sqlTotalPedido, $parametrosTotalPedido);
        $totalCentimos = $this->convertirEurosACentimos($totalGuardado);

        // Stripe recibe el total como una sola línea de cobro en céntimos
        $datosProductoStripe = [
            'name' => "Compra en Onuba Gourmet Market. Pedido n.º {$identificadorPedido}",
        ];
        $datosPrecio = [
            'currency' => 'eur',
            'product_data' => $datosProductoStripe,
            'unit_amount' => $totalCentimos,
        ];
        $lineaDeCobro = [
            'price_data' => $datosPrecio,
            'quantity' => 1,
        ];
        $lineasCobro = [$lineaDeCobro];

        // Stripe sustituye CHECKOUT_SESSION_ID por el identificador de la sesión al volver
        $direccionResultado = route('carrito.pago.resultado');
        $direccionRegreso = $direccionResultado . '?sesion={CHECKOUT_SESSION_ID}';
        $referenciaPedido = (string) $identificadorPedido;

        $datosSesionStripe = [
            'mode' => 'payment',
            'ui_mode' => 'embedded_page',
            'client_reference_id' => $referenciaPedido,
            'customer_email' => $correoEnvio,
            'line_items' => $lineasCobro,
            'return_url' => $direccionRegreso,
        ];

        $servicioCheckout = $clienteStripe->checkout;
        $sesionesStripe = $servicioCheckout->sessions;

        // Stripe recibe la misma clave si la primera petición llega pero su respuesta se pierde.
        $opcionesStripe = ['idempotency_key' => (string) Str::uuid()];

        return $this->reintentarConexionStripe(
            fn () => $sesionesStripe->create($datosSesionStripe, $opcionesStripe),
        );
    }

    private function reintentarConexionStripe(\Closure $operacion): Session
    {
        return retry(2, $operacion, 500, fn (\Throwable $error) => $error instanceof ApiConnectionException);
    }

    // Cierro el intento de Stripe antes de permitir cambios en el pedido
    public function modificar(Request $peticion, Carrito $carrito) {

        $clienteStripe = $this->crearClienteStripe();

        $operacionModificarPedido = function () use ($peticion, $carrito, $clienteStripe) {
            $pedidoPendiente = $carrito->pedidoPendiente($peticion);
            $hayPagoAbierto = $carrito->hayPagoAbierto($pedidoPendiente);
            $redireccion = redirect();

            if ($hayPagoAbierto) {
                try {
                    $servicioCheckout = $clienteStripe->checkout;
                    $sesionesStripe = $servicioCheckout->sessions;
                    $identificadorSesionStripe = $pedidoPendiente->stripe_checkout_session_id;
                    $sesionStripe = $sesionesStripe->retrieve($identificadorSesionStripe);

                    // Solo pido a Stripe que cierre una sesión si todavía está abierta
                    if ($sesionStripe->status === 'open') {
                        $identificadorSesionAbierta = $sesionStripe->id;
                        $sesionStripe = $sesionesStripe->expire($identificadorSesionAbierta);
                    }

                    $this->actualizarEstado($sesionStripe);

                    if ($sesionStripe->status !== 'expired') {
                        $respuesta = $redireccion->route('carrito.index');
                        $respuesta->with('aviso', 'El pago ya se completó o sigue en proceso.');

                        return $respuesta;
                    }
                } catch (ApiErrorException $error) {
                    $respuesta = $redireccion->route('carrito.index');
                    $respuesta->with('aviso', 'No se pudo cerrar el intento en Stripe. Inténtalo de nuevo antes de modificar el pedido.');

                    return $respuesta;
                }
            }

            // Vuelvo al formulario de envío con los datos guardados del pedido
            $parametrosCarrito = [
                'envio' => 'pedido',
            ];
            $direccionCarrito = route('carrito.index', $parametrosCarrito);
            $direccionFormularioEnvio = $direccionCarrito . '#direccion-envio';

            return $redireccion->to($direccionFormularioEnvio);
        };

        return $carrito->modificar($peticion, $operacionModificarPedido);
    }

    // Compruebo el pago y preparo el mensaje que se verá al volver al carrito
    public function resultado(Request $peticion) {

        // Leo el identificador de la sesión de Stripe que viene en la URL
        $identificadorSesionStripe = $peticion->query('sesion');

        if (! is_string($identificadorSesionStripe)) {
            throw new NotFoundHttpException();
        }

        // Obtengo el identificador del usuario conectado
        $usuario = $peticion->user();
        $identificadorUsuario = $usuario->id;

        // Busco el pedido que coincide con el usuario conectado y la sesión de Stripe
        $sqlPedido = '
            SELECT id
            FROM pedidos
            WHERE usuario_id = ?
              AND stripe_checkout_session_id = ?
        ';

        $parametrosPedido = [
            $identificadorUsuario,
            $identificadorSesionStripe,
        ];

        $pedido = DB::selectOne($sqlPedido, $parametrosPedido);

        if ($pedido === null) {
            throw new NotFoundHttpException();
        }

        // Preparo la vuelta al carrito para mostrar el resultado de la consulta
        $redireccion = redirect();
        $respuesta = $redireccion->route('carrito.index');

        // Consulto el resultado en Stripe y actualizo el estado del pedido
        try {
            $clienteStripe = $this->crearClienteStripe();
            $servicioCheckout = $clienteStripe->checkout;
            $sesionesStripe = $servicioCheckout->sessions;
            $sesionStripe = $sesionesStripe->retrieve($identificadorSesionStripe);

            $this->actualizarEstado($sesionStripe);
        } catch (ApiErrorException $error) {
            $mensajeAviso = 'No se pudo consultar Stripe. El pedido sigue disponible para comprobar su estado.';
            $respuesta->with('aviso', $mensajeAviso);

            return $respuesta;
        }

        // Vuelvo a leer el estado porque la consulta a Stripe puede haberlo cambiado
        $identificadorPedido = $pedido->id;

        $sqlEstadoPedido = '
            SELECT estado
            FROM pedidos
            WHERE id = ?
        ';

        $parametrosEstadoPedido = [
            $identificadorPedido,
        ];

        $estadoPedido = DB::scalar($sqlEstadoPedido, $parametrosEstadoPedido);

        if ($estadoPedido === 'pagado') {
            // Guardo el número del pedido en la sesión para mostrar el enlace al volver al carrito
            $respuesta->with('pedido_pagado_id', $identificadorPedido);

            return $respuesta;
        }

        // Muestro un aviso cuando el pago sigue sin confirmar
        $mensajeAviso = "El pedido n.º {$identificadorPedido} todavía no se ha confirmado.";
        $respuesta->with('aviso', $mensajeAviso);

        return $respuesta;
    }

    // Verifico el aviso que Stripe envía directamente y proceso el estado del pago
    public function webhook(Request $peticion) {

        $aplicacion = app();
        $entornoLocal = $aplicacion->environment('local');

        if (! $entornoLocal) {
            throw new NotFoundHttpException();
        }

        $secretoFirmaStripe = config('services.stripe.webhook_secret');

        if (! is_string($secretoFirmaStripe) || ! str_starts_with($secretoFirmaStripe, 'whsec_')) {
            throw new HttpException(503);
        }

        $respuesta = response();

        // Verifico la firma antes de usar los datos del aviso
        try {
            $contenidoAviso = $peticion->getContent();
            $firmaAviso = $peticion->header('Stripe-Signature', '');
            $evento = Webhook::constructEvent($contenidoAviso, $firmaAviso, $secretoFirmaStripe);
        } catch (\UnexpectedValueException | SignatureVerificationException $error) {
            $datosRespuesta = [
                'message' => 'Aviso de Stripe no válido.',
            ];

            return $respuesta->json($datosRespuesta, 400);
        }

        $avisosDePago = [
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
            'checkout.session.async_payment_failed',
            'checkout.session.expired',
        ];

        $tipoAviso = $evento->type;
        $esAvisoDePago = in_array($tipoAviso, $avisosDePago, true);

        // Solo proceso los avisos de pago que vienen del modo de pruebas
        if (! $evento->livemode && $esAvisoDePago) {
            $datosAviso = $evento->data;
            $sesionStripe = $datosAviso->object;
            $pagoFallido = $tipoAviso === 'checkout.session.async_payment_failed';

            $this->actualizarEstado($sesionStripe, $pagoFallido);
        }

        return $respuesta->noContent();
    }

    // Compruebo los datos del cobro antes de actualizar el pedido
    private function actualizarEstado(Session $sesionStripe, bool $pagoFallido = false): void
    {
        $identificadorSesionStripe = $sesionStripe->id;
        $sqlPedido = '
            SELECT id, usuario_id
            FROM pedidos
            WHERE stripe_checkout_session_id = ?
        ';

        $parametrosPedido = [
            $identificadorSesionStripe,
        ];

        $pedido = DB::selectOne($sqlPedido, $parametrosPedido);

        if ($pedido === null) {
            return;
        }

        $operacionActualizarEstado = function () use ($pedido, $sesionStripe, $pagoFallido): void {
            // Bloqueo al usuario mientras actualizo el pedido y su carrito
            $identificadorUsuario = $pedido->usuario_id;
            $consultaUsuario = Usuario::query();
            $consultaUsuario->whereKey($identificadorUsuario);
            $consultaUsuario->lockForUpdate();
            $consultaUsuario->firstOrFail();

            // Vuelvo a leer el pedido porque otra petición pudo haberlo procesado
            $identificadorPedido = $pedido->id;
            $consultaPedido = DB::table('pedidos');
            $consultaPedido->where('id', $identificadorPedido);
            $pedidoActual = $consultaPedido->first();

            if ($pedidoActual === null) {
                return;
            }

            $pedidoSiguePendiente = $pedidoActual->estado === 'pendiente';
            $esLaMismaSesion = $pedidoActual->stripe_checkout_session_id === $sesionStripe->id;

            if (! $pedidoSiguePendiente || ! $esLaMismaSesion) {
                return;
            }

            // Verifico la referencia, la moneda y el importe que devuelve Stripe
            $referenciaPedido = (string) $pedidoActual->id;
            $referenciaCoincide = $sesionStripe->client_reference_id === $referenciaPedido;

            if ($sesionStripe->livemode || ! $referenciaCoincide) {
                return;
            }

            $totalCentimos = $this->convertirEurosACentimos($pedidoActual->total);
            $monedaEsEuro = $sesionStripe->currency === 'eur';
            $importeCoincide = $sesionStripe->amount_total === $totalCentimos;

            if (! $monedaEsEuro || ! $importeCoincide) {
                return;
            }

            // Marco el pedido como pagado y vacío el carrito cuando el cobro se ha confirmado
            if ($sesionStripe->payment_status === 'paid') {
                $datosPedidoPagado = [
                    'estado' => 'pagado',
                    'updated_at' => now(),
                ];
                $consultaPedido->update($datosPedidoPagado);

                $consultaCarrito = DB::table('carrito_productos');
                $consultaCarrito->where('usuario_id', $pedidoActual->usuario_id);
                $consultaCarrito->delete();

                return;
            }

            // Si el intento caducó o falló, quito la sesión para permitir cambios en el pedido
            if ($sesionStripe->status === 'expired' || $pagoFallido) {
                $datosIntentoCerrado = [
                    'stripe_checkout_session_id' => null,
                    'updated_at' => now(),
                ];
                $consultaPedido->update($datosIntentoCerrado);
            }
        };

        DB::transaction($operacionActualizarEstado);
    }

    // Compruebo el stock y calculo el total a partir de los precios de MySQL
    private function prepararLineas(array $cantidadesCarrito): array
    {
        $lineasPedido = [];
        $totalCentimos = 0;

        foreach ($cantidadesCarrito as $identificadorProducto => $cantidad) {
            $consultaProducto = Producto::query();
            $consultaProducto->where('activo', true);
            $consultaProducto->whereHas('categoria', function ($consultaCategoria) {
                $consultaCategoria->where('activa', true);
            });

            $producto = $consultaProducto->find($identificadorProducto);
            if ($producto === null || $cantidad < 1 || $cantidad > $producto->stock) {
                $errores = [
                    'pago' => 'Ha cambiado la disponibilidad. Recarga el carrito y revisa las cantidades.',
                ];

                throw ValidationException::withMessages($errores);
            }

            // Hago las cuentas en céntimos y convierto el subtotal a euros para guardarlo
            $precioUnitarioEuros = $producto->precio;
            $precioCentimos = $this->convertirEurosACentimos($precioUnitarioEuros);
            $subtotalCentimos = $precioCentimos * $cantidad;
            $totalCentimos = $totalCentimos + $subtotalCentimos;
            $subtotalEuros = $this->convertirCentimosAEuros($subtotalCentimos);

            $lineasPedido[] = [
                'producto_id' => $identificadorProducto,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitarioEuros,
                'subtotal' => $subtotalEuros,
            ];
        }

        // Compruebo los límites del importe antes de abrir la sesión de Stripe
        if ($totalCentimos < 50 || $totalCentimos > 99999999) {
            $errores = [
                'pago' => 'El importe del carrito está fuera de los límites del pago en euros.',
            ];

            throw ValidationException::withMessages($errores);
        }

        return [
            'lineas' => $lineasPedido,
            'totalCentimos' => $totalCentimos,
        ];
    }

    // Si el importe tiene dos decimales, quitar el punto convierte por ejemplo 12.35 en 1235 céntimos
    private function convertirEurosACentimos(string $importeEuros): int
    {
        $importeSinPunto = str_replace('.', '', $importeEuros);
        $importeCentimos = (int) $importeSinPunto;

        return $importeCentimos;
    }

    // Devuelvo el importe en euros con dos decimales para guardarlo en MySQL
    private function convertirCentimosAEuros(int $importeCentimos): string
    {
        $importeEuros = $importeCentimos / 100;
        $importeFormateado = number_format($importeEuros, 2, '.', '');

        return $importeFormateado;
    }

    // Creo el cliente de Stripe solo en el entorno local y con una clave de pruebas
    private function crearClienteStripe(): StripeClient
    {
        $aplicacion = app();
        $entornoLocal = $aplicacion->environment('local');

        if (! $entornoLocal) {
            throw new NotFoundHttpException();
        }

        $claveSecretaStripe = config('services.stripe.secret');
        if (! is_string($claveSecretaStripe) || ! str_starts_with($claveSecretaStripe, 'sk_test_')) {
            throw new HttpException(503, 'Configura la clave secreta de pruebas de Stripe.');
        }

        return new StripeClient($claveSecretaStripe);
    }
}
