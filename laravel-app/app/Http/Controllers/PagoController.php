<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Usuario;
use App\Services\Carrito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PagoController extends Controller
{
    // Método: Guardar el pedido y devolver los datos para abrir el pago
    public function iniciar(Request $request, Carrito $carrito)
    {
        $stripe = $this->stripe();
        $carrito->incorporarVisitante($request);

        return $carrito->modificar($request, function () use ($request, $carrito, $stripe) {
            $usuario = $request->user();
            $usuarioId = $usuario->id;
            $pedido = $carrito->pedidoPendiente($request);

            try {
                // Si el intento sigue abierto, devuelvo el mismo código de pago
                if ($carrito->hayPagoAbierto($pedido)) {
                    $checkout = $stripe->checkout;
                    $sesionesStripe = $checkout->sessions;
                    $idSesion = $pedido->stripe_checkout_session_id;
                    $sesion = $sesionesStripe->retrieve($idSesion);

                    if ($sesion->status !== 'open') {
                        $this->actualizarEstado($sesion);

                        return response()->json(['message' => 'El intento anterior ha cambiado de estado. Recarga el carrito para continuar.'], 409);
                    }

                    return response()->json(['clientSecret' => $sesion->client_secret]);
                }

                $envio = $request->validate([
                    'nombre' => ['required', 'string', 'max:100'],
                    'apellidos' => ['required', 'string', 'max:150'],
                    'email' => ['required', 'email', 'max:255'],
                    'telefono' => ['required', 'string', 'max:20'],
                    'direccion' => ['required', 'string', 'max:255'],
                    'codigo_postal' => ['required', 'string', 'max:10'],
                    'localidad' => ['required', 'string', 'max:100'],
                    'provincia' => ['required', 'string', 'max:100'],
                    'total_mostrado' => ['required', 'integer'],
                ]);

                // Calculo el importe con el carrito guardado y lo comparo con el que vio el cliente
                $cantidades = $carrito->leer($request);
                $carritoCalculado = $this->prepararLineas($cantidades);
                $lineas = $carritoCalculado['lineas'];
                $totalCentimos = $carritoCalculado['totalCentimos'];
                $totalMostrado = (int) $envio['total_mostrado'];

                if ($totalCentimos !== $totalMostrado) {
                    $errores = ['pago' => 'El total del carrito ha cambiado. Recarga la página y revisa el importe.'];
                    throw ValidationException::withMessages($errores);
                }

                $datosPedido = [
                    'nombre_envio' => $envio['nombre'],
                    'apellidos_envio' => $envio['apellidos'],
                    'email_envio' => $envio['email'],
                    'telefono_envio' => $envio['telefono'],
                    'direccion_envio' => $envio['direccion'],
                    'codigo_postal_envio' => $envio['codigo_postal'],
                    'localidad_envio' => $envio['localidad'],
                    'provincia_envio' => $envio['provincia'],
                    'total' => $this->euros($totalCentimos),
                    'updated_at' => now(),
                ];

                $pedidoId = $this->guardarPedido($pedido, $usuarioId, $datosPedido, $lineas);
                $sesion = $this->crearSesionPago($stripe, $pedidoId, $envio['email']);

                $consultaPedido = DB::table('pedidos');
                $consultaPedido->where('id', $pedidoId);
                $consultaPedido->update([
                    'stripe_checkout_session_id' => $sesion->id,
                ]);

                return response()->json(['clientSecret' => $sesion->client_secret]);
            } catch (ApiErrorException $error) {
                return response()->json(['message' => 'No se pudo preparar el pago en Stripe. Inténtalo de nuevo.'], 502);
            }
        });
    }

    // Método: Guardar los datos de envío y las líneas del pedido en MySQL
    private function guardarPedido(?object $pedido, int $usuarioId, array $datosPedido, array $lineas): int
    {
        $consultaPedido = DB::table('pedidos');

        if ($pedido === null) {
            $datosPedido['usuario_id'] = $usuarioId;
            $datosPedido['estado'] = 'pendiente';
            $datosPedido['fecha'] = now();
            $datosPedido['created_at'] = now();
            $pedidoId = $consultaPedido->insertGetId($datosPedido);
        } else {
            $pedidoId = $pedido->id;
            $consultaPedido->where('id', $pedidoId);
            $consultaPedido->update($datosPedido);

            $consultaDetalles = DB::table('detalles_pedido');
            $consultaDetalles->where('pedido_id', $pedidoId);
            $consultaDetalles->delete();
        }

        $tablaDetalles = DB::table('detalles_pedido');
        foreach ($lineas as $linea) {
            $linea['pedido_id'] = $pedidoId;
            $linea['created_at'] = now();
            $linea['updated_at'] = now();
            $tablaDetalles->insert($linea);
        }

        return $pedidoId;
    }

    // Método: Crear en Stripe un intento de pago por el total guardado
    private function crearSesionPago(StripeClient $stripe, int $pedidoId, string $email): Session
    {
        $consultaPedido = DB::table('pedidos');
        $consultaPedido->where('id', $pedidoId);
        // Recupero de la bd el total guardado en pedidos para enviarlo a Stripe
        $totalGuardado = $consultaPedido->value('total');
        $totalCentimos = $this->centimos($totalGuardado);

        $precio = [
            'currency' => 'eur',
            'product_data' => ['name' => "Compra en Onuba Gourmet Market. Pedido n.º {$pedidoId}"],
            'unit_amount' => $totalCentimos,
        ];
        $lineaDeCobro = [
            'price_data' => $precio,
            'quantity' => 1,
        ];
        $urlResultado = route('carrito.pago.resultado') . '?sesion={CHECKOUT_SESSION_ID}';

        $datosSesion = [
            'mode' => 'payment',
            'ui_mode' => 'embedded_page',
            'client_reference_id' => (string) $pedidoId,
            'customer_email' => $email,
            'line_items' => [$lineaDeCobro],
            'return_url' => $urlResultado,
        ];

        $checkout = $stripe->checkout;
        $sesionesStripe = $checkout->sessions;
        return $sesionesStripe->create($datosSesion);
    }

    // Método: Cerrar el intento de Stripe antes de permitir cambios en el pedido
    public function modificar(Request $request, Carrito $carrito)
    {
        $stripe = $this->stripe();

        return $carrito->modificar($request, function () use ($request, $carrito, $stripe) {
            $pedido = $carrito->pedidoPendiente($request);

            if ($carrito->hayPagoAbierto($pedido)) {
                try {
                    $checkout = $stripe->checkout;
                    $sesionesStripe = $checkout->sessions;
                    $idSesion = $pedido->stripe_checkout_session_id;
                    $sesion = $sesionesStripe->retrieve($idSesion);

                    if ($sesion->status === 'open') {
                        $sesion = $sesionesStripe->expire($sesion->id);
                    }

                    $this->actualizarEstado($sesion);

                    if ($sesion->status !== 'expired') {
                        $respuesta = redirect()->route('carrito.index');
                        return $respuesta->with('aviso', 'El pago ya se completó o sigue en proceso.');
                    }
                } catch (ApiErrorException $error) {
                    $respuesta = redirect()->route('carrito.index');
                    return $respuesta->with('aviso', 'No se pudo cerrar el intento en Stripe. Inténtalo de nuevo antes de modificar el pedido.');
                }
            }

            $urlEnvio = route('carrito.index', ['envio' => 'pedido']) . '#direccion-envio';
            return redirect()->to($urlEnvio);
        });
    }

    // Método: Comprobar a quién pertenece el pedido y consultar si Stripe lo cobró
    public function resultado(Request $request)
    {
        $idSesion = $request->query('sesion');
        if (! is_string($idSesion)) {
            throw new NotFoundHttpException();
        }

        $usuario = $request->user();
        $consultaPedido = DB::table('pedidos');
        $consultaPedido->where('usuario_id', $usuario->id);
        $consultaPedido->where('stripe_checkout_session_id', $idSesion);
        // Recupero de la tabla pedidos de la bd el registro que coincide con el cliente y la sesión de Stripe
        $pedido = $consultaPedido->first();
        if ($pedido === null) {
            throw new NotFoundHttpException();
        }

        try {
            $stripe = $this->stripe();
            $checkout = $stripe->checkout;
            $sesionesStripe = $checkout->sessions;
            $sesion = $sesionesStripe->retrieve($idSesion);
            $this->actualizarEstado($sesion);
        } catch (ApiErrorException $error) {
            $respuesta = redirect()->route('carrito.index');
            return $respuesta->with('aviso', 'No se pudo consultar Stripe. El pedido sigue disponible para comprobar su estado.');
        }

        $consultaEstado = DB::table('pedidos');
        $consultaEstado->where('id', $pedido->id);
        // Recupero de la bd el estado del pedido que está guardado en pedidos
        $estado = $consultaEstado->value('estado');

        $respuesta = redirect()->route('carrito.index');
        if ($estado === 'pagado') {
            return $respuesta->with('mensaje', "Pago confirmado. Pedido n.º {$pedido->id}.");
        }

        return $respuesta->with('aviso', "El pedido n.º {$pedido->id} todavía no se ha confirmado.");
    }

    // Método: Verificar el aviso de Stripe y procesar el resultado del pago
    public function webhook(Request $request)
    {
        if (! app()->environment('local')) {
            throw new NotFoundHttpException();
        }

        $secreto = config('services.stripe.webhook_secret');
        if (! is_string($secreto) || ! str_starts_with($secreto, 'whsec_')) {
            throw new HttpException(503);
        }

        try {
            // El SDK de Stripe comprueba la firma del aviso
            $contenidoAviso = $request->getContent();
            $firmaAviso = $request->header('Stripe-Signature', '');
            $evento = Webhook::constructEvent($contenidoAviso, $firmaAviso, $secreto);
        } catch (\UnexpectedValueException | SignatureVerificationException $error) {
            return response()->json(['message' => 'Aviso de Stripe no válido.'], 400);
        }

        $avisosDePago = [
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
            'checkout.session.async_payment_failed',
            'checkout.session.expired',
        ];
        if (! $evento->livemode && in_array($evento->type, $avisosDePago, true)) {
            $datosAviso = $evento->data;
            $sesion = $datosAviso->object;
            $pagoFallido = $evento->type === 'checkout.session.async_payment_failed';
            $this->actualizarEstado($sesion, $pagoFallido);
        }

        return response()->noContent();
    }

    // Método: Comprobar el cobro y actualizar el pedido
    private function actualizarEstado(Session $sesion, bool $pagoFallido = false): void
    {
        $consultaPedido = DB::table('pedidos');
        $consultaPedido->where('stripe_checkout_session_id', $sesion->id);
        // Recupero de la bd el pedido asociado a esta sesión de Stripe en pedidos
        $pedido = $consultaPedido->first();

        if ($pedido === null) {
            return;
        }

        DB::transaction(function () use ($pedido, $sesion, $pagoFallido): void {
            $consultaUsuario = Usuario::whereKey($pedido->usuario_id);
            $consultaUsuario->lockForUpdate();
            // Recupero de la bd la fila del cliente en usuarios y la bloqueo durante la operación
            $consultaUsuario->firstOrFail();

            $consultaPedido = DB::table('pedidos');
            $consultaPedido->where('id', $pedido->id);
            // Vuelvo a recuperar el pedido de la bd para leer su estado actual en pedidos
            $actual = $consultaPedido->first();

            // Si ya procesé este pedido o cambió de intento, lo dejo como está
            if ($actual->estado !== 'pendiente' || $actual->stripe_checkout_session_id !== $sesion->id) {
                return;
            }

            $referenciaPedido = (string) $actual->id;
            if ($sesion->livemode || $sesion->client_reference_id !== $referenciaPedido) {
                return;
            }

            $totalCentimos = $this->centimos($actual->total);
            if ($sesion->currency !== 'eur' || $sesion->amount_total !== $totalCentimos) {
                return;
            }

            $actualizarPedido = DB::table('pedidos');
            $actualizarPedido->where('id', $actual->id);

            // Si se ha cobrado, marco el pedido como pagado y vacío el carrito
            if ($sesion->payment_status === 'paid') {
                $actualizarPedido->update(['estado' => 'pagado', 'updated_at' => now()]);

                $consultaCarrito = DB::table('carrito_productos');
                $consultaCarrito->where('usuario_id', $actual->usuario_id);
                $consultaCarrito->delete();
            } elseif ($sesion->status === 'expired' || $pagoFallido) {
                // Si el intento caducó o falló, el pedido queda pendiente para volver a editarlo
                $actualizarPedido->update(['stripe_checkout_session_id' => null, 'updated_at' => now()]);
            }
        });
    }

    // Método: Comprobar el stock y calcular los subtotales y el total en céntimos
    private function prepararLineas(array $cantidades): array
    {
        $lineas = [];
        $totalCentimos = 0;

        foreach ($cantidades as $productoId => $cantidad) {
            $consultaProducto = Producto::where('activo', true);
            $consultaProducto->whereHas('categoria', function ($consultaCategoria) {
                $consultaCategoria->where('activa', true);
            });
            // Recupero de la bd el precio y el stock de la tabla productos si el producto y su categoría están activos
            $producto = $consultaProducto->find($productoId);

            if ($producto === null || $cantidad < 1 || $cantidad > $producto->stock) {
                $errores = ['pago' => 'Ha cambiado la disponibilidad. Recarga el carrito y revisa las cantidades.'];
                throw ValidationException::withMessages($errores);
            }

            $precioCentimos = $this->centimos($producto->precio);
            $subtotalCentimos = $precioCentimos * $cantidad;
            $totalCentimos = $totalCentimos + $subtotalCentimos;
            $lineas[] = [
                'producto_id' => $productoId,
                'cantidad' => $cantidad,
                'precio_unitario' => $producto->precio,
                'subtotal' => $this->euros($subtotalCentimos),
            ];
        }

        if ($totalCentimos < 50 || $totalCentimos > 99999999) {
            $errores = ['pago' => 'El importe del carrito está fuera de los límites del pago en euros.'];
            throw ValidationException::withMessages($errores);
        }

        return [
            'lineas' => $lineas,
            'totalCentimos' => $totalCentimos,
        ];
    }

    // Método: Convertir un importe de euros a céntimos quitando el separador decimal
    private function centimos(string $importe): int
    {
        $importeSinPunto = str_replace('.', '', $importe);
        return (int) $importeSinPunto;
    }

    // Método: Convertir céntimos a euros con dos decimales
    private function euros(int $centimos): string
    {
        $euros = $centimos / 100;
        return number_format($euros, 2, '.', '');
    }

    // Método: Crear el cliente de Stripe con la clave secreta de pruebas
    private function stripe(): StripeClient
    {
        if (! app()->environment('local')) {
            throw new NotFoundHttpException();
        }

        $clave = config('services.stripe.secret');
        if (! is_string($clave) || ! str_starts_with($clave, 'sk_test_')) {
            throw new HttpException(503, 'Configura la clave secreta de pruebas de Stripe.');
        }

        return new StripeClient($clave);
    }
}
