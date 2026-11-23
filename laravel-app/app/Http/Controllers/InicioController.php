<?php
namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Http\Request;

class InicioController extends Controller
{
    // Preparo las categorías y los productos destacados para la página de inicio
    public function index() {
        $categorias = Categoria::all();

        // Busco hasta seis productos destacados que estén activos y tengan una categoría activa
        $sqlProductosDestacados = '
            SELECT producto.*
            FROM productos AS producto
            INNER JOIN categorias AS categoria ON categoria.id = producto.categoria_id
            WHERE producto.destacado = ?
              AND producto.activo = ?
              AND categoria.activa = ?
            ORDER BY producto.categoria_id, producto.id
            LIMIT 6
        ';

        $parametrosProductosDestacados = [
            true,
            true,
            true,
        ];

        // fromQuery ejecuta el SQL y devuelve el resultado como modelos Producto
        $consultaProductosDestacados = Producto::query();
        $productosDestacados = $consultaProductosDestacados->fromQuery(
            $sqlProductosDestacados,
            $parametrosProductosDestacados,
        );

        $datosInicio = [
            'categorias' => $categorias,
            'destacados' => $productosDestacados,
        ];

        return view('inicio', $datosInicio);
    }

    // Recibo el slug de la URL y preparo la página de esa categoría
    public function categoria(Request $peticion, $slug) {
        // Busco la categoría activa y devuelvo un error 404 si no existe
        $consultaCategoria = Categoria::query();
        $consultaCategoria->where('slug', $slug);
        $consultaCategoria->where('activa', true);
        $categoria = $consultaCategoria->firstOrFail();

        // Recupero las categorías activas que aparecen en el menú
        $consultaCategoriasMenu = Categoria::query();
        $consultaCategoriasMenu->where('activa', true);
        $categorias = $consultaCategoriasMenu->get();

        // Leo la opción elegida en el formulario y uso solo las columnas permitidas
        $ordenSeleccionado = $peticion->query('orden', 'nombre');
        $columnaOrden = 'nombre';
        $direccionOrden = 'asc';

        if ($ordenSeleccionado === 'precio_asc') {
            $columnaOrden = 'precio';
        } elseif ($ordenSeleccionado === 'precio_desc') {
            $columnaOrden = 'precio';
            $direccionOrden = 'desc';
        }

        // Parto de los productos de esta categoría y recupero ocho por página
        $consultaProductos = $categoria->productos();
        $consultaProductos->where('activo', true);
        $consultaProductos->orderBy($columnaOrden, $direccionOrden);
        $consultaProductos->orderBy('id', 'asc');
        $productos = $consultaProductos->paginate(8);

        // Cada slug tiene asociado el nombre de su imagen de cabecera
        $nombresImagenesCabecera = [
            'aceites-de-oliva' => 'banner_aceites',
            'conservas-y-salazones' => 'banner_conservas_salazones',
            'jamones-y-embutidos' => 'banner_jamones_embutidos',
            'miel-y-dulces' => 'banner_miel_dulces',
            'quesos' => 'banner_quesos',
            'vinos' => 'banner_vinos',
        ];

        $bannerCategoria = null;
        $slugCategoria = $categoria->slug;

        if (isset($nombresImagenesCabecera[$slugCategoria])) {
            $nombreImagenCabecera = $nombresImagenesCabecera[$slugCategoria];
            $extensionesImagen = ['webp', 'png'];

            // Compruebo si existe primero la imagen WebP y después la PNG
            foreach ($extensionesImagen as $extensionImagen) {
                $rutaImagenCabecera = "imagen/banners/{$nombreImagenCabecera}.{$extensionImagen}";
                $rutaPublicaImagen = public_path($rutaImagenCabecera);

                if (is_file($rutaPublicaImagen)) {
                    $bannerCategoria = $rutaImagenCabecera;
                    break;
                }
            }
        }

        // Las claves de este array son los nombres que uso en la vista Blade
        $datosCategoria = [
            'categoria' => $categoria,
            'categorias' => $categorias,
            'productos' => $productos,
            'bannerCategoria' => $bannerCategoria,
            'ordenSeleccionado' => $ordenSeleccionado,
        ];

        return view('categoria', $datosCategoria);
    }
}
