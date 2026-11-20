<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Services\Carrito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Los precios y la disponibilidad se consultan siempre en la base de datos
class CarritoController extends Controller
{
    private Carrito $carrito;

    // Método: Recibir el servicio que lee y guarda los productos del carrito
    public function __construct(Carrito $carrito)
    {
        $this->carrito = $carrito;
    }

    // Método: Incorporar el carrito del visitante y mostrar los productos
    public function index(Request $request)
    {
        $this->carrito->incorporarVisitante($request);

        return $this->carrito->modificar($request, function () use ($request) {
            return $this->mostrarCarrito($request);
        });
    }

    // Método: Preparar los productos, el total y la dirección para la vista del carrito
    private function mostrarCarrito(Request $request)
    {
        $carrito = $this->carrito->leer($request);
        $lineas = [];
        $totalCentimos = 0;
        $pedido = $this->carrito->pedidoPendiente($request);
        $pagoAbierto = $this->carrito->hayPagoAbierto($pedido);

        // Elijo si mostrar las líneas guardadas del pedido o el carrito que aún se puede editar
        if ($pagoAbierto) {
            $consultaDetalles = DB::table('detalles_pedido');
            $consultaDetalles->where('pedido_id', $pedido->id);
            // Recupero de la bd las líneas de este pedido guardadas en detalles_pedido
            $detalles = $consultaDetalles->get();
            foreach ($detalles as $detalle) {
                // Recupero de la bd el nombre y los demás datos guardados en la tabla productos
                $producto = Producto::findOrFail($detalle->producto_id);
                $producto->precio = $detalle->precio_unitario;
                $lineas[] = [
                    'producto' => $producto,
                    'cantidad' => $detalle->cantidad,
                    'subtotal' => $detalle->subtotal,
                ];
            }
            $totalCentimos = (int) str_replace('.', '', $pedido->total);
        } else {
            foreach ($carrito as $identificador => $cantidad) {
                $consultaProducto = Producto::where('id', $identificador);
                $consultaProducto->where('activo', true);
                $consultaProducto->whereHas('categoria', function ($consultaCategoria) {
                    $consultaCategoria->where('activa', true);
                });
                // Recupero de la tabla productos de la bd el producto si sigue activo y su categoría también
                $producto = $consultaProducto->first();

                // Retira los productos que ya no se pueden comprar
                if ($producto === null || $producto->stock < 1) {
                    $this->carrito->guardarCantidad($request, $identificador, 0);
                    $sesion = $request->session();
                    $sesion->flash('aviso', 'El carrito se ha ajustado a la disponibilidad actual');
                    continue;
                }

                if ($cantidad > $producto->stock) {
                    $cantidad = $producto->stock;
                    $this->carrito->guardarCantidad($request, $identificador, $cantidad);
                    $sesion = $request->session();
                    $sesion->flash('aviso', 'El carrito se ha ajustado a la disponibilidad actual');
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
        }

        $total = $totalCentimos / 100;

        $usuario = $request->user();
        $tipoDireccion = $request->query('envio');
        $tiposDireccion = ['habitual', 'nueva', 'pedido'];
        $mostrarEnvio = $pagoAbierto;
        if ($usuario !== null && in_array($tipoDireccion, $tiposDireccion, true)) {
            $mostrarEnvio = true;
        }

        $camposEnvio = ['nombre', 'apellidos', 'email', 'telefono', 'direccion', 'codigo_postal', 'localidad', 'provincia'];
        // Empiezo con un formulario vacío y lo relleno si hay una dirección que recuperar
        $datosEnvio = array_fill_keys($camposEnvio, '');
        if ($pedido !== null && ($tipoDireccion === 'pedido' || $pagoAbierto)) {
            $datosEnvio = [
                'nombre' => $pedido->nombre_envio,
                'apellidos' => $pedido->apellidos_envio,
                'email' => $pedido->email_envio,
                'telefono' => $pedido->telefono_envio,
                'direccion' => $pedido->direccion_envio,
                'codigo_postal' => $pedido->codigo_postal_envio,
                'localidad' => $pedido->localidad_envio,
                'provincia' => $pedido->provincia_envio,
            ];
        } elseif ($usuario !== null && $tipoDireccion === 'habitual') {
            foreach ($camposEnvio as $campo) {
                $datosEnvio[$campo] = $usuario->getAttribute($campo);
            }
        }

        return view('carrito', [
            'lineas' => $lineas,
            'total' => $total,
            'totalCentimos' => $totalCentimos,
            'mostrarEnvio' => $mostrarEnvio,
            'tipoDireccion' => $tipoDireccion,
            'datosEnvio' => $datosEnvio,
            'pagoAbierto' => $pagoAbierto,
        ]);
    }

    // Método: Añadir una unidad al carrito sin superar el stock disponible
    public function agregar(Request $request, Producto $producto)
    {
        // Laravel carga de la bd los datos de productos y consulta su categoría en categorias
        if (! $producto->activo || ! $producto->categoria || ! $producto->categoria->activa) {
            return back()->with('aviso', 'Este producto ya no está disponible');
        }

        $this->carrito->incorporarVisitante($request);

        return $this->carrito->modificar($request, function () use ($request, $producto) {
            $pedido = $this->carrito->pedidoPendiente($request);
            if ($this->carrito->hayPagoAbierto($pedido)) {
                return $this->pagoEnCurso();
            }

            $carrito = $this->carrito->leer($request);
            $cantidadAnterior = 0;
            if (isset($carrito[$producto->id])) {
                $cantidadAnterior = $carrito[$producto->id];
            }
            $cantidad = $cantidadAnterior + 1;

            if ($cantidad > $producto->stock) {
                return back()->with('aviso', 'No hay más unidades disponibles de este producto');
            }

            $this->carrito->guardarCantidad($request, $producto->id, $cantidad);

            return back();
        });
    }

    // Método: Validar y guardar la cantidad enviada desde el carrito
    public function actualizar(Request $request, Producto $producto)
    {
        // Laravel carga de la bd los datos de productos y consulta su categoría en categorias
        if (! $producto->activo || ! $producto->categoria || ! $producto->categoria->activa || $producto->stock < 1) {
            $respuesta = redirect()->route('carrito.index');
            return $respuesta->with('aviso', 'Este producto ya no está disponible');
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
        return $this->carrito->modificar($request, function () use ($request, $producto, $datos) {
            $pedido = $this->carrito->pedidoPendiente($request);
            if ($this->carrito->hayPagoAbierto($pedido)) {
                return $this->pagoEnCurso();
            }

            $carrito = $this->carrito->leer($request);
            if (isset($carrito[$producto->id])) {
                $this->carrito->guardarCantidad($request, $producto->id, (int) $datos['cantidad']);
            }
            return redirect()->route('carrito.index');
        });
    }

    // Método: Retirar un producto del carrito usando su identificador
    public function eliminar(Request $request, $identificador)
    {
        $this->carrito->incorporarVisitante($request);
        return $this->carrito->modificar($request, function () use ($request, $identificador) {
            $pedido = $this->carrito->pedidoPendiente($request);
            if ($this->carrito->hayPagoAbierto($pedido)) {
                return $this->pagoEnCurso();
            }

            $this->carrito->guardarCantidad($request, (int) $identificador, 0);
            $respuesta = redirect()->route('carrito.index');
            return $respuesta->with('mensaje', 'Producto eliminado del carrito');
        });
    }

    // Método: Avisar de que hay que cerrar el pago antes de editar el carrito
    private function pagoEnCurso()
    {
        $respuesta = redirect()->route('carrito.index');
        return $respuesta->with('aviso', 'Pulsa «Modificar pedido» antes de cambiar el carrito.');
    }
}
