<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Services\Carrito;
use Illuminate\Http\Request;

// Los precios y la disponibilidad se consultan siempre en la base de datos
class CarritoController extends Controller
{
    public function __construct(private Carrito $carrito)
    {
    }

    public function index(Request $request)
    {
        $this->carrito->incorporarVisitante($request);

        return $this->carrito->modificar($request, fn () => $this->mostrarCarrito($request));
    }

    private function mostrarCarrito(Request $request)
    {
        $carrito = $this->carrito->leer($request);
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
                $this->carrito->guardarCantidad($request, $identificador, 0);
                $request->session()->flash('aviso', 'El carrito se ha ajustado a la disponibilidad actual');
                continue;
            }

            if ($cantidad > $producto->stock) {
                $cantidad = $producto->stock;
                $this->carrito->guardarCantidad($request, $identificador, $cantidad);
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

        $total = $totalCentimos / 100;

        return view('carrito', compact('lineas', 'total'));
    }

    // Añade una unidad sin superar el stock disponible
    public function agregar(Request $request, Producto $producto)
    {
        if (! $producto->activo || ! $producto->categoria || ! $producto->categoria->activa) {
            return back()->with('aviso', 'Este producto ya no está disponible');
        }

        $this->carrito->incorporarVisitante($request);

        return $this->carrito->modificar($request, function () use ($request, $producto) {
            $carrito = $this->carrito->leer($request);
            $cantidad = ($carrito[$producto->id] ?? 0) + 1;

            if ($cantidad > $producto->stock) {
                return back()->with('aviso', 'No hay más unidades disponibles de este producto');
            }

            $this->carrito->guardarCantidad($request, $producto->id, $cantidad);

            return back();
        });
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

        $this->carrito->incorporarVisitante($request);
        $this->carrito->modificar($request, function () use ($request, $producto, $datos): void {
            $carrito = $this->carrito->leer($request);
            if (isset($carrito[$producto->id])) {
                $this->carrito->guardarCantidad($request, $producto->id, (int) $datos['cantidad']);
            }
        });

        return redirect()->route('carrito.index');
    }

    // No necesita consultar el producto para poder retirar también uno eliminado
    public function eliminar(Request $request, $identificador)
    {
        $this->carrito->incorporarVisitante($request);
        $this->carrito->modificar($request, function () use ($request, $identificador): void {
            $this->carrito->guardarCantidad($request, (int) $identificador, 0);
        });

        return redirect()->route('carrito.index')->with('mensaje', 'Producto eliminado del carrito');
    }
}
