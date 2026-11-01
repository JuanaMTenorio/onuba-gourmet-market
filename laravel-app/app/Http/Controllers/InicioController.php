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

        return view('inicio', compact('categorias')); // 'inicio' = resources/views/inicio.blade.php, y envía a la vista la variable $categoria
    }

    // Creo el método que:
    public function categoria($slug)
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

        // 3. Consultar los productos de la categoría seleccionada
        $productos = DB::table('productos')
            ->where('categoria_id', $categoria->id)
            ->where('activo', 1)
            ->orderBy('nombre', 'asc')
            ->paginate(8);

        // 4. Enviar los datos a la vista
        return view('categoria', compact('categoria', 'categorias', 'productos'));
    }


}
