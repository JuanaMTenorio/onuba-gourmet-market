<?php
// Controlador que prepara los datos (pide a MySQL las categorías y las entrega a la vista)

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; //DB es la puerta que proporciona Laravel para hablar con la base de datos.

// Clase que hereda del controlador base (Controller) de Laravel
class InicioController extends Controller
{
    // Creo el método, que al ejecutarse:
    public function index()
    {
        $categorias = DB::table('categorias')->get(); // Consulta y obtiene la info 'categorias' de la base de datos SELECT * FROM categorias;

        $destacados = DB::table('productos as p')
            ->join('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->where('p.destacado', 1)
            ->where('p.activo', 1)
            ->where('c.activa', 1)
            ->orderBy('c.id')
            ->orderBy('p.id')
            ->limit(6)
            ->select('p.*')
            ->get();

        return view('inicio', compact('categorias', 'destacados'));
    }

    // Creo el método que:
    public function categoria(Request $request, $slug)
    {
        // 1. Buscar la categoría seleccionada por su slug
        $categoria = DB::table('categorias')
            ->where('slug', $slug)
            ->where('activa', 1)
            ->firstOrFail();

        // 2. Obtener las categorías activas para el menú lateral
        $categorias = DB::table('categorias')
            ->where('activa', 1)
            ->get();

        // Solo se aceptan estas columnas y direcciones de ordenación.
        [$columna, $direccion] = match ($request->query('orden')) {
            'precio_asc' => ['precio', 'asc'],
            'precio_desc' => ['precio', 'desc'],
            default => ['nombre', 'asc'],
        };

        // 3. Consultar los productos de la categoría seleccionada
        $productos = DB::table('productos')
            ->where('categoria_id', $categoria->id)
            ->where('activo', 1)
            ->orderBy($columna, $direccion)
            ->orderBy('id', 'asc')
            ->paginate(8);

        // Banner propio de cada categoría, sin modificar la base de datos.
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
            // Prioriza WebP; permite incorporar los PNG originales directamente.
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
