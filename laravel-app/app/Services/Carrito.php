<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Carrito
{
    // Busco el último pedido pendiente del cliente
    public function pedidoPendiente(Request $peticion): ?object
    {
        $usuario = $peticion->user();

        if ($usuario === null) {
            return null;
        }

        $identificadorUsuario = $usuario->id;
        $sqlPedidoPendiente = '
            SELECT *
            FROM pedidos
            WHERE usuario_id = ?
              AND estado = ?
            ORDER BY id DESC
            LIMIT 1
        ';

        $parametrosPedidoPendiente = [
            $identificadorUsuario,
            'pendiente',
        ];

        return DB::selectOne($sqlPedidoPendiente, $parametrosPedidoPendiente);
    }

    // Compruebo si ya se abrió una sesión de Stripe para el pedido pendiente
    public function hayPagoAbierto(?object $pedido): bool
    {
        if ($pedido === null) {
            return false;
        }

        $identificadorSesionStripe = $pedido->stripe_checkout_session_id;

        return ! empty($identificadorSesionStripe);
    }

    // Leo el carrito de la sesión del visitante o de MySQL si ha iniciado sesión
    public function leer(Request $peticion): array
    {
        $usuario = $peticion->user();

        if ($usuario === null) {
            $sesion = $peticion->session();

            return $sesion->get('carrito', []);
        }

        $this->incorporarVisitante($peticion);

        // Uso el número de producto como clave y su cantidad como valor
        $identificadorUsuario = $usuario->id;
        $consultaCarrito = DB::table('carrito_productos');
        $consultaCarrito->where('usuario_id', $identificadorUsuario);
        $cantidades = $consultaCarrito->pluck('cantidad', 'producto_id');

        return $cantidades->all();
    }

    // Guardo la cantidad o retiro el producto cuando la cantidad es cero
    public function guardarCantidad(Request $peticion, int $identificadorProducto, int $cantidad): void
    {
        $usuario = $peticion->user();

        if ($usuario === null) {
            $sesion = $peticion->session();
            $carrito = $sesion->get('carrito', []);

            if ($cantidad === 0) {
                unset($carrito[$identificadorProducto]);
            } else {
                $carrito[$identificadorProducto] = $cantidad;
            }

            $sesion->put('carrito', $carrito);

            return;
        }

        // En MySQL identifico la línea mediante el usuario y el producto juntos
        $identificadorUsuario = $usuario->id;
        $linea = [
            'usuario_id' => $identificadorUsuario,
            'producto_id' => $identificadorProducto,
        ];

        $consultaCarrito = DB::table('carrito_productos');

        if ($cantidad === 0) {
            $consultaCarrito->where($linea);
            $consultaCarrito->delete();

            return;
        }

        // upsert añade la línea si es nueva o actualiza solo la cantidad si ya existe
        $linea['cantidad'] = $cantidad;
        $lineasParaGuardar = [$linea];
        $columnasIdentificadoras = ['usuario_id', 'producto_id'];
        $columnasParaActualizar = ['cantidad'];

        $consultaCarrito->upsert($lineasParaGuardar, $columnasIdentificadoras, $columnasParaActualizar);
    }

    // Paso los productos del visitante al carrito que tiene guardado en su cuenta
    public function incorporarVisitante(Request $peticion): void
    {
        $sesion = $peticion->session();
        $cantidadesVisitante = $sesion->get('carrito', []);
        $usuario = $peticion->user();

        if ($usuario === null || $cantidadesVisitante === []) {
            return;
        }

        $identificadorUsuario = $usuario->id;
        $operacionIncorporar = function () use ($peticion, $cantidadesVisitante, $identificadorUsuario): bool {
            // Espero a que se cierre el intento de pago antes de mezclar ambos carritos
            $pedido = $this->pedidoPendiente($peticion);
            $hayPagoAbierto = $this->hayPagoAbierto($pedido);

            if ($hayPagoAbierto) {
                return false;
            }

            // Recupero las cantidades que ya estaban guardadas en MySQL
            $consultaCarrito = DB::table('carrito_productos');
            $consultaCarrito->where('usuario_id', $identificadorUsuario);
            $cantidadesGuardadas = $consultaCarrito->pluck('cantidad', 'producto_id');

            // Busco los productos del visitante que todavía se pueden comprar
            $identificadoresProductos = array_keys($cantidadesVisitante);
            $consultaProductos = Producto::query();
            $consultaProductos->whereIn('id', $identificadoresProductos);
            $consultaProductos->where('activo', true);
            $consultaProductos->where('stock', '>', 0);
            $consultaProductos->whereHas('categoria', function ($consultaCategoria) {
                $consultaCategoria->where('activa', true);
            });
            $productos = $consultaProductos->get();

            foreach ($productos as $producto) {
                $identificadorProducto = $producto->id;
                $cantidadGuardada = 0;

                if (isset($cantidadesGuardadas[$identificadorProducto])) {
                    $cantidadGuardada = $cantidadesGuardadas[$identificadorProducto];
                }

                // Sumo ambas cantidades y guardo como máximo lo que queda en stock
                $cantidadVisitante = $cantidadesVisitante[$identificadorProducto];
                $cantidadTotal = $cantidadGuardada + $cantidadVisitante;
                $cantidadParaGuardar = min($producto->stock, $cantidadTotal);

                $this->guardarCantidad($peticion, $identificadorProducto, $cantidadParaGuardar);
            }

            return true;
        };

        $incorporado = $this->modificar($peticion, $operacionIncorporar);

        // Borro el carrito visitante de la sesión después de guardarlo en MySQL
        if ($incorporado) {
            $sesion->forget('carrito');
        }
    }

    // Evito que dos peticiones cambien a la vez el carrito de la misma cuenta
    public function modificar(Request $peticion, \Closure $operacion): mixed
    {
        $usuario = $peticion->user();

        if ($usuario === null) {
            return $operacion();
        }

        $identificadorUsuario = $usuario->id;
        $operacionConBloqueo = function () use ($identificadorUsuario, $operacion) {
            $consultaUsuario = Usuario::query();
            $consultaUsuario->whereKey($identificadorUsuario);
            $consultaUsuario->lockForUpdate();

            // Al leer la fila del cliente, MySQL la bloquea hasta que termina la transacción
            $consultaUsuario->firstOrFail();

            return $operacion();
        };

        return DB::transaction($operacionConBloqueo);
    }
}
