<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductosDestacadosSeeder extends Seeder
{
    public function run(): void
    {
        $seleccionarProductosDestacados = function (): void {
            // Busco las categorías activas del catálogo
            $sqlCategoriasActivas = '
                SELECT id
                FROM categorias
                WHERE activa = ?
            ';

            $parametrosCategoriasActivas = [
                true,
            ];

            $categoriasActivas = DB::select($sqlCategoriasActivas, $parametrosCategoriasActivas);

            // Busco el producto activo con el identificador más bajo de cada categoría
            $sqlPrimerProductoActivo = '
                SELECT id
                FROM productos
                WHERE categoria_id = ?
                  AND activo = ?
                ORDER BY id ASC
                LIMIT 1
            ';

            $sqlMarcarProductoDestacado = '
                UPDATE productos
                SET destacado = ?
                WHERE id = ?
            ';

            foreach ($categoriasActivas as $categoria) {
                $identificadorCategoria = $categoria->id;
                $parametrosPrimerProducto = [
                    $identificadorCategoria,
                    true,
                ];

                $producto = DB::selectOne($sqlPrimerProductoActivo, $parametrosPrimerProducto);

                // Si la categoría no tiene productos activos, paso a la siguiente
                if ($producto === null) {
                    continue;
                }

                // Marco el producto elegido y conservo los destacados que ya había
                $identificadorProducto = $producto->id;
                $parametrosProductoDestacado = [
                    true,
                    $identificadorProducto,
                ];

                DB::update($sqlMarcarProductoDestacado, $parametrosProductoDestacado);
            }
        };

        // Aplico todos los cambios dentro de la misma transacción
        DB::transaction($seleccionarProductosDestacados);
    }
}
