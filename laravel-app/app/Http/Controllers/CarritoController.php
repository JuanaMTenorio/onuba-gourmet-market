<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;

// La sesión guarda el identificador y la cantidad de cada producto
// Los precios y la disponibilidad se consultan siempre en la base de datos
class CarritoController extends Controller
{
    public function index(Request $request)
    {
        $carrito = $request->session()->get('carrito', []);
        $lineas = [];
        $totalCentimos = 0;

        foreach ($carrito as $identificador => $cantidad) {
            $producto = Producto::where('id', $identificador)
                ->where('activo', true)
                ->whereHas('categoria', function ($consultaCategoria) {
                    $consultaCategoria->where('activa', true);
                })
                ->first();

            // Retira los productos que ya no se pueden comprar
            if ($producto === null || $producto->stock < 1) {
                unset($carrito[$identificador]);
                $request->session()->flash('aviso', 'El carrito se ha ajustado a la disponibilidad actual');
                continue;
            }

            if ($cantidad > $producto->stock) {
                $cantidad = $producto->stock;
                $carrito[$identificador] = $cantidad;
                $request->session()->flash('aviso', 'El carrito se ha ajustado a la disponibilidad actual');
            }

            // Calcula el dinero en céntimos para evitar errores al sumar decimales
            $precioCentimos = (int) str_replace('.', '', $producto->precio);
            $subtotalCentimos = $precioCentimos * $cantidad;
            $totalCentimos = $totalCentimos + $subtotalCentimos;

            $lineas[] = [
                'producto' => $producto,
                'cantidad' => $cantidad,
                'subtotal' => $subtotalCentimos / 100,
            ];
        }

        $request->session()->put('carrito', $carrito);
        $total = $totalCentimos / 100;

        return view('carrito', compact('lineas', 'total'));
    }

    // Añade una unidad sin superar el stock disponible
    public function agregar(Request $request, Producto $producto)
    {
        if (! $producto->activo || ! $producto->categoria || ! $producto->categoria->activa) {
            return back()->with('aviso', 'Este producto ya no está disponible');
        }

        $carrito = $request->session()->get('carrito', []);
        $cantidad = 1;

        if (isset($carrito[$producto->id])) {
            $cantidad = $carrito[$producto->id] + 1;
        }

        if ($cantidad > $producto->stock) {
            return back()->with('aviso', 'No hay más unidades disponibles de este producto');
        }

        $carrito[$producto->id] = $cantidad;
        $request->session()->put('carrito', $carrito);

        return back();
    }

    // Comprueba la cantidad enviada por el formulario antes de guardarla
    public function actualizar(Request $request, Producto $producto)
    {
        if (! $producto->activo || ! $producto->categoria || ! $producto->categoria->activa || $producto->stock < 1) {
            return redirect()->route('carrito.index')->with('aviso', 'Este producto ya no está disponible');
        }

        $datos = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1', 'max:' . $producto->stock],
        ], [
            'cantidad.required' => 'Indica una cantidad',
            'cantidad.integer' => 'La cantidad debe ser un número entero',
            'cantidad.min' => 'La cantidad mínima es una unidad',
            'cantidad.max' => 'La cantidad supera el stock disponible',
        ]);

        $carrito = $request->session()->get('carrito', []);

        if (isset($carrito[$producto->id])) {
            $carrito[$producto->id] = (int) $datos['cantidad'];
            $request->session()->put('carrito', $carrito);
        }

        return redirect()->route('carrito.index');
    }

    // No necesita consultar el producto para poder retirar también uno eliminado
    public function eliminar(Request $request, $identificador)
    {
        $carrito = $request->session()->get('carrito', []);
        unset($carrito[$identificador]);
        $request->session()->put('carrito', $carrito);

        return redirect()->route('carrito.index')->with('mensaje', 'Producto eliminado del carrito');
    }
}
