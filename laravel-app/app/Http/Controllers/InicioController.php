<?php
// Controlador que prepara los datos (pide a MySQL las categorías y las entrega a la vista)

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Categoria;
use App\Models\Producto;

// Clase que hereda del controlador base (Controller) de Laravel
class InicioController extends Controller
{
    // Creo el método, que al ejecutarse:
    public function index()
    {
        // Obtiene las categorías utilizando el modelo Categoria
        $categorias = Categoria::all();

        // Muestra hasta seis productos destacados y activos de categorías activas
        // whereHas comprueba la categoría relacionada con cada producto
        $destacados = Producto::where('destacado', true)
            ->where('activo', true)
            ->whereHas('categoria', function ($consultaCategoria) {
                $consultaCategoria->where('activa', true);
            })
            ->orderBy('categoria_id')
            ->orderBy('id')
            ->limit(6)
            ->get();

        return view('inicio', compact('categorias', 'destacados'));
    }

    // Creo el método que:
    public function categoria(Request $request, $slug)
    {
        // 1. Buscar la categoría seleccionada por su slug
        $categoria = Categoria::where('slug', $slug)
            ->where('activa', 1)
            ->firstOrFail();

        // 2. Obtener las categorías activas para el menú lateral
        $categorias = Categoria::where('activa', true)
            ->get();

        // Solo permite ordenar por las opciones definidas en el catálogo
        $columna = 'nombre';
        $direccion = 'asc';

        if ($request->query('orden') === 'precio_asc') {
            $columna = 'precio';
            $direccion = 'asc';
        } elseif ($request->query('orden') === 'precio_desc') {
            $columna = 'precio';
            $direccion = 'desc';
        }

        // 3. Consultar los productos de la categoría seleccionada
        // Utiliza la relación productos definida en el modelo Categoria
        $productos = $categoria->productos()
            ->where('activo', 1)
            ->orderBy($columna, $direccion)
            ->orderBy('id', 'asc')
            ->paginate(8);

        // Banner propio de cada categoría, sin modificar la base de datos
        $banners = [
            'aceites-de-oliva' => 'banner_aceites',
            'conservas-y-salazones' => 'banner_conservas_salazones',
            'jamones-y-embutidos' => 'banner_jamones_embutidos',
            'miel-y-dulces' => 'banner_miel_dulces',
            'quesos' => 'banner_quesos',
            'vinos' => 'banner_vinos',
        ];

        $bannerCategoria = null;
        if (isset($banners[$categoria->slug])) {
            // Prioriza WebP y permite incorporar los PNG originales directamente
            foreach (['webp', 'png'] as $extension) {
                $ruta = 'imagen/banners/' . $banners[$categoria->slug] . '.' . $extension;
                if (is_file(public_path($ruta))) {
                    $bannerCategoria = $ruta;
                    break;
                }
            }
        }

        // 4. Enviar los datos a la vista
        return view('categoria', compact('categoria', 'categorias', 'productos', 'bannerCategoria'));
    }


}
