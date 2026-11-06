<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductosDestacadosSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $categorias = DB::table('categorias')->where('activa', 1)->get();

            foreach ($categorias as $categoria) {
                // Selección inicial: primer producto activo de cada categoría.
                $producto = DB::table('productos')
                    ->where('categoria_id', $categoria->id)
                    ->where('activo', 1)
                    ->orderBy('id')
                    ->first();

                if ($producto) {
                    DB::table('productos')->where('id', $producto->id)
                        ->update(['destacado' => true]);
                }
            }
        });
    }
}
