<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Añado la columna cuando se ejecuta la migración
    public function up(): void
    {
        $agregarColumnaDestacado = function (Blueprint $tablaProductos): void {
            $columnaDestacado = $tablaProductos->boolean('destacado');

            // Los productos empiezan sin destacar si al guardarlos no indico otro valor
            $columnaDestacado->default(false);
        };

        Schema::table('productos', $agregarColumnaDestacado);
    }

    // Retiro la columna cuando se deshace la migración
    public function down(): void
    {
        $quitarColumnaDestacado = function (Blueprint $tablaProductos): void {
            $tablaProductos->dropColumn('destacado');
        };

        Schema::table('productos', $quitarColumnaDestacado);
    }
};
