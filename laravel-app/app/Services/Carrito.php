<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Carrito
{
    public function leer(Request $request): array
    {
        if (! $request->user()) {
            return $request->session()->get('carrito', []);
        }

        $this->incorporarVisitante($request);

        return DB::table('carrito_productos')
            ->where('usuario_id', $request->user()->id)
            ->pluck('cantidad', 'producto_id')
            ->all();
    }

    public function guardarCantidad(Request $request, int $productoId, int $cantidad): void
    {
        if (! $request->user()) {
            $carrito = $request->session()->get('carrito', []);
            if ($cantidad === 0) {
                unset($carrito[$productoId]);
            } else {
                $carrito[$productoId] = $cantidad;
            }
            $request->session()->put('carrito', $carrito);

            return;
        }

        $linea = ['usuario_id' => $request->user()->id, 'producto_id' => $productoId];

        if ($cantidad === 0) {
            DB::table('carrito_productos')->where($linea)->delete();

            return;
        }

        DB::table('carrito_productos')->upsert(
            [$linea + ['cantidad' => $cantidad]],
            ['usuario_id', 'producto_id'],
            ['cantidad'],
        );
    }

    public function incorporarVisitante(Request $request): void
    {
        $visitante = $request->session()->get('carrito', []);

        if (! $request->user() || $visitante === []) {
            return;
        }

        $this->modificar($request, function () use ($request, $visitante): void {
            $guardado = DB::table('carrito_productos')
                ->where('usuario_id', $request->user()->id)
                ->pluck('cantidad', 'producto_id');

            $productos = Producto::whereIn('id', array_keys($visitante))
                ->where('activo', true)
                ->where('stock', '>', 0)
                ->whereHas('categoria', fn ($consulta) => $consulta->where('activa', true))
                ->get();

            foreach ($productos as $producto) {
                $cantidad = min($producto->stock, ($guardado[$producto->id] ?? 0) + $visitante[$producto->id]);
                $this->guardarCantidad($request, $producto->id, $cantidad);
            }
        });

        $request->session()->forget('carrito');
    }

    public function modificar(Request $request, \Closure $operacion): mixed
    {
        if (! $request->user()) {
            return $operacion();
        }

        return DB::transaction(function () use ($request, $operacion) {
            Usuario::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

            return $operacion();
        });
    }
}
