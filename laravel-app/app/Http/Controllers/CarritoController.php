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

    // Incorporo los productos del visitante y preparo la página del carrito
    public function index(Request $peticion)
    {
        $this->carrito->incorporarVisitante($peticion);

        // Con use llevo la petición dentro de la función que va a ejecutar el servicio
        $operacionMostrarCarrito = function () use ($peticion) {
            return $this->mostrarCarrito($peticion);
        };

        return $this->carrito->modificar($peticion, $operacionMostrarCarrito);
    }

    // Método: Preparar los productos, el total y la dirección para la vista del carrito
    private function mostrarCarrito(Request $peticion)
    {
        $cantidadesCarrito = $this->carrito->leer($peticion);
        $lineas = [];
        $totalCentimos = 0;

        $pedido = $this->carrito->pedidoPendiente($peticion);
        $pagoAbierto = $this->carrito->hayPagoAbierto($pedido);

        // Elijo si mostrar las líneas guardadas del pedido o el carrito que aún se puede editar
        if ($pagoAbierto) {
            $identificadorPedido = $pedido->id;
            $sqlDetallesPedido = '
                SELECT producto_id, cantidad, precio_unitario, subtotal
                FROM detalles_pedido
                WHERE pedido_id = ?
            ';

            $parametrosDetallesPedido = [
                $identificadorPedido,
            ];

            $detalles = DB::select($sqlDetallesPedido, $parametrosDetallesPedido);

            foreach ($detalles as $detalle) {
                // Uso los datos del catálogo con el precio que se guardó al abrir el pago
                $identificadorProducto = $detalle->producto_id;
                $producto = Producto::findOrFail($identificadorProducto);
                $producto->precio = $detalle->precio_unitario;

                $lineas[] = [
                    'producto' => $producto,
                    'cantidad' => $detalle->cantidad,
                    'subtotal' => $detalle->subtotal,
                ];
            }

            $totalSinPuntoDecimal = str_replace('.', '', $pedido->total);
            $totalCentimos = (int) $totalSinPuntoDecimal;
        } else {
            foreach ($cantidadesCarrito as $identificadorProducto => $cantidad) {
                $consultaProducto = Producto::query();
                $consultaProducto->where('id', $identificadorProducto);
                $consultaProducto->where('activo', true);
                $consultaProducto->whereHas('categoria', function ($consultaCategoria) {
                    $consultaCategoria->where('activa', true);
                });
                // Recupero de la tabla productos de la bd el producto si sigue activo y su categoría también
                $producto = $consultaProducto->first();

                // Retira los productos que ya no se pueden comprar
                if ($producto === null || $producto->stock < 1) {
                    $this->carrito->guardarCantidad($peticion, $identificadorProducto, 0);
                    $sesion = $peticion->session();
                    $sesion->flash('aviso', 'El carrito se ha ajustado a la disponibilidad actual');
                    continue;
                }

                if ($cantidad > $producto->stock) {
                    $cantidad = $producto->stock;
                    $this->carrito->guardarCantidad($peticion, $identificadorProducto, $cantidad);
                    $sesion = $peticion->session();
                    $sesion->flash('aviso', 'El carrito se ha ajustado a la disponibilidad actual');
                }

                // Calcula el dinero en céntimos para evitar errores al sumar decimales
                $precioSinPuntoDecimal = str_replace('.', '', $producto->precio);
                $precioCentimos = (int) $precioSinPuntoDecimal;
                $subtotalCentimos = $precioCentimos * $cantidad;
                $totalCentimos = $totalCentimos + $subtotalCentimos;
                $subtotalEuros = $subtotalCentimos / 100;

                $lineas[] = [
                    'producto' => $producto,
                    'cantidad' => $cantidad,
                    'subtotal' => $subtotalEuros,
                ];
            }
        }

        $total = $totalCentimos / 100;

        // Elijo qué datos de envío se muestran en función de la opción elegida
        $usuario = $peticion->user();
        $tipoDireccion = $peticion->query('envio');
        $tiposDireccion = ['habitual', 'nueva', 'pedido'];
        $tipoDireccionValido = in_array($tipoDireccion, $tiposDireccion, true);

        $mostrarEnvio = $pagoAbierto;
        if ($usuario !== null && $tipoDireccionValido) {
            $mostrarEnvio = true;
        }

        $camposEnvio = [
            'nombre',
            'apellidos',
            'email',
            'telefono',
            'direccion',
            'codigo_postal',
            'localidad',
            'provincia',
        ];

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

        $datosVistaCarrito = [
            'lineas' => $lineas,
            'total' => $total,
            'totalCentimos' => $totalCentimos,
            'mostrarEnvio' => $mostrarEnvio,
            'tipoDireccion' => $tipoDireccion,
            'datosEnvio' => $datosEnvio,
            'pagoAbierto' => $pagoAbierto,
        ];

        return view('carrito', $datosVistaCarrito);
    }

    // Método: Añadir una unidad al carrito sin superar el stock disponible
    public function agregar(Request $peticion, Producto $producto)
    {
        $productoDisponible = $this->productoDisponibleEnCatalogo($producto);
        if (! $productoDisponible) {
            $respuesta = back();
            $respuesta->with('aviso', 'Este producto ya no está disponible');

            return $respuesta;
        }

        $this->carrito->incorporarVisitante($peticion);

        // El servicio ejecuta este bloque mientras protege el carrito del cliente
        $operacionAgregar = function () use ($peticion, $producto) {
            $pedido = $this->carrito->pedidoPendiente($peticion);
            $hayPagoAbierto = $this->carrito->hayPagoAbierto($pedido);

            if ($hayPagoAbierto) {
                return $this->pagoEnCurso();
            }

            // Busco cuántas unidades había y sumo una
            $identificadorProducto = $producto->id;
            $cantidadesCarrito = $this->carrito->leer($peticion);
            $cantidadAnterior = 0;

            if (isset($cantidadesCarrito[$identificadorProducto])) {
                $cantidadAnterior = $cantidadesCarrito[$identificadorProducto];
            }

            $cantidadNueva = $cantidadAnterior + 1;
            if ($cantidadNueva > $producto->stock) {
                $respuesta = back();
                $respuesta->with('aviso', 'No hay más unidades disponibles de este producto');

                return $respuesta;
            }

            $this->carrito->guardarCantidad($peticion, $identificadorProducto, $cantidadNueva);

            return back();
        };

        return $this->carrito->modificar($peticion, $operacionAgregar);
    }

    // Método: Validar y guardar la cantidad enviada desde el carrito
    public function actualizar(Request $peticion, Producto $producto)
    {
        $productoDisponible = $this->productoDisponibleEnCatalogo($producto);
        $stockDisponible = $producto->stock;

        if (! $productoDisponible || $stockDisponible < 1) {
            $redireccion = redirect();
            $respuesta = $redireccion->route('carrito.index');
            $respuesta->with('aviso', 'Este producto ya no está disponible');

            return $respuesta;
        }

        // La cantidad del formulario debe ser un entero entre uno y el stock disponible
        $reglasCantidad = [
            'cantidad' => [
                'required',
                'integer',
                'min:1',
                'max:' . $stockDisponible,
            ],
        ];

        $mensajesCantidad = [
            'cantidad.required' => 'Indica una cantidad',
            'cantidad.integer' => 'La cantidad debe ser un número entero',
            'cantidad.min' => 'La cantidad mínima es una unidad',
            'cantidad.max' => 'La cantidad supera el stock disponible',
        ];

        $datosValidados = $peticion->validate($reglasCantidad, $mensajesCantidad);
        $cantidadNueva = (int) $datosValidados['cantidad'];
        $identificadorProducto = $producto->id;

        $this->carrito->incorporarVisitante($peticion);

        $operacionActualizar = function () use ($peticion, $identificadorProducto, $cantidadNueva) {
            $pedido = $this->carrito->pedidoPendiente($peticion);
            $hayPagoAbierto = $this->carrito->hayPagoAbierto($pedido);

            if ($hayPagoAbierto) {
                return $this->pagoEnCurso();
            }

            // Solo actualizo productos que ya están en el carrito
            $cantidadesCarrito = $this->carrito->leer($peticion);
            if (isset($cantidadesCarrito[$identificadorProducto])) {
                $this->carrito->guardarCantidad($peticion, $identificadorProducto, $cantidadNueva);
            }

            $redireccion = redirect();

            return $redireccion->route('carrito.index');
        };

        return $this->carrito->modificar($peticion, $operacionActualizar);
    }

    // Método: Retirar un producto del carrito usando su identificador
    public function eliminar(Request $peticion, $identificador)
    {
        $identificadorProducto = (int) $identificador;
        $this->carrito->incorporarVisitante($peticion);

        $operacionEliminar = function () use ($peticion, $identificadorProducto) {
            $pedido = $this->carrito->pedidoPendiente($peticion);
            $hayPagoAbierto = $this->carrito->hayPagoAbierto($pedido);

            if ($hayPagoAbierto) {
                return $this->pagoEnCurso();
            }

            // Una cantidad de cero indica al servicio que debe retirar el producto
            $this->carrito->guardarCantidad($peticion, $identificadorProducto, 0);

            $redireccion = redirect();
            $respuesta = $redireccion->route('carrito.index');
            $respuesta->with('mensaje', 'Producto eliminado del carrito');

            return $respuesta;
        };

        return $this->carrito->modificar($peticion, $operacionEliminar);
    }

    // Un producto solo se puede comprar si él y su categoría están activos
    private function productoDisponibleEnCatalogo(Producto $producto): bool
    {
        if (! $producto->activo) {
            return false;
        }

        $categoria = $producto->categoria;
        if ($categoria === null) {
            return false;
        }

        return $categoria->activa;
    }

    // Método: Avisar de que hay que cerrar el pago antes de editar el carrito
    private function pagoEnCurso()
    {
        $redireccion = redirect();
        $respuesta = $redireccion->route('carrito.index');
        $respuesta->with('aviso', 'Pulsa «Modificar pedido» antes de cambiar el carrito.');

        return $respuesta;
    }
}
