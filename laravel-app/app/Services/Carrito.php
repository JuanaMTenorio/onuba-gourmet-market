<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Carrito
{
    // Método: Buscar el último pedido pendiente del cliente
    public function pedidoPendiente(Request $request): ?object
    {
        $usuario = $request->user();
        if ($usuario === null) {
            return null;
        }

        $consultaPedido = DB::table('pedidos');
        $consultaPedido->where('usuario_id', $usuario->id);
        $consultaPedido->where('estado', 'pendiente');
        $consultaPedido->orderBy('id', 'desc');
        // Recupero de la bd el último pedido pendiente del cliente en pedidos
        return $consultaPedido->first();
    }

    // Método: Comprobar si un pedido pendiente tiene una sesión de pago asociada
    public function hayPagoAbierto(?object $pedido): bool
    {
        if ($pedido === null) {
            return false;
        }

        return ! empty($pedido->stripe_checkout_session_id);
    }

    // Método: Leer el carrito de la sesión o de MySQL según quién está comprando
    public function leer(Request $request): array
    {
        $usuario = $request->user();
        if ($usuario === null) {
            $sesion = $request->session();
            return $sesion->get('carrito', []);
        }

        $this->incorporarVisitante($request);

        $consultaCarrito = DB::table('carrito_productos');
        $consultaCarrito->where('usuario_id', $usuario->id);
        // Recupero de la bd las cantidades del cliente por producto en carrito_productos
        $cantidades = $consultaCarrito->pluck('cantidad', 'producto_id');
        return $cantidades->all();
    }

    // Método: Guardar la cantidad de un producto o eliminarlo si la cantidad es cero
    public function guardarCantidad(Request $request, int $productoId, int $cantidad): void
    {
        $usuario = $request->user();
        if ($usuario === null) {
            $sesion = $request->session();
            $carrito = $sesion->get('carrito', []);
            if ($cantidad === 0) {
                unset($carrito[$productoId]);
            } else {
                $carrito[$productoId] = $cantidad;
            }
            $sesion->put('carrito', $carrito);

            return;
        }

        $linea = ['usuario_id' => $usuario->id, 'producto_id' => $productoId];
        $consultaCarrito = DB::table('carrito_productos');

        if ($cantidad === 0) {
            $consultaCarrito->where($linea);
            $consultaCarrito->delete();

            return;
        }

        $linea['cantidad'] = $cantidad;
        $consultaCarrito->upsert(
            [$linea],
            ['usuario_id', 'producto_id'],
            ['cantidad'],
        );
    }

    // Método: Incorporar los productos del visitante a su carrito sin superar el stock
    public function incorporarVisitante(Request $request): void
    {
        $sesion = $request->session();
        $visitante = $sesion->get('carrito', []);
        $usuario = $request->user();

        if ($usuario === null || $visitante === []) {
            return;
        }

        $incorporado = $this->modificar($request, function () use ($request, $visitante, $usuario): bool {
            // Espero a que se cierre el intento de pago antes de mezclar ambos carritos
            $pedido = $this->pedidoPendiente($request);
            if ($this->hayPagoAbierto($pedido)) {
                return false;
            }

            $consultaCarrito = DB::table('carrito_productos');
            $consultaCarrito->where('usuario_id', $usuario->id);
            // Recupero de la bd las cantidades de carrito_productos para sumarlas a las del visitante
            $guardado = $consultaCarrito->pluck('cantidad', 'producto_id');

            $identificadores = array_keys($visitante);
            $consultaProductos = Producto::whereIn('id', $identificadores);
            $consultaProductos->where('activo', true);
            $consultaProductos->where('stock', '>', 0);
            $consultaProductos->whereHas('categoria', function ($consultaCategoria) {
                $consultaCategoria->where('activa', true);
            });
            // Recupero de la bd los productos del visitante que siguen disponibles en productos
            $productos = $consultaProductos->get();

            foreach ($productos as $producto) {
                $cantidadGuardada = 0;
                if (isset($guardado[$producto->id])) {
                    $cantidadGuardada = $guardado[$producto->id];
                }

                $cantidadTotal = $cantidadGuardada + $visitante[$producto->id];
                $cantidad = min($producto->stock, $cantidadTotal);
                $this->guardarCantidad($request, $producto->id, $cantidad);
            }

            return true;
        });

        if ($incorporado) {
            $sesion->forget('carrito');
        }
    }

    // Método: Bloquear cambios simultáneos de la misma cuenta durante una operación
    public function modificar(Request $request, \Closure $operacion): mixed
    {
        $usuario = $request->user();
        if ($usuario === null) {
            return $operacion();
        }

        return DB::transaction(function () use ($usuario, $operacion) {
            $consultaUsuario = Usuario::whereKey($usuario->id);
            $consultaUsuario->lockForUpdate();
            // Recupero de la bd la fila del cliente en usuarios y la bloqueo durante la operación
            $consultaUsuario->firstOrFail();

            return $operacion();
        });
    }
}
