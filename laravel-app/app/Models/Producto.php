<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\Rule;

// Representa un producto del catálogo y permite consultar sus datos con Eloquent
// Permite asignar estos campos juntos al crear o actualizar un producto
// El identificador y las fechas los gestiona Laravel con la base de datos
#[Fillable([
    'categoria_id',
    'nombre',
    'slug',
    'descripcion',
    'precio',
    'stock',
    'formato',
    'imagen',
    'activo',
    'destacado',
])]
class Producto extends Model
{
    // Indica el nombre de la tabla que utiliza este modelo
    protected $table = 'productos';

    // Define cómo debe interpretar Laravel estos valores de la base de datos
    protected function casts(): array
    {
        return [
            'categoria_id' => 'integer',
            'precio' => 'decimal:2',
            'stock' => 'integer',
            'activo' => 'boolean',
            'destacado' => 'boolean',
        ];
    }

    // Reglas para comprobar los datos cuando se prepare el formulario de productos
    // Deben utilizarse con validate antes de guardar porque Eloquent no las ejecuta solo
    public function reglasValidacion(): array
    {
        return [
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'nombre' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:170', Rule::unique('productos', 'slug')->ignore($this)],
            'descripcion' => ['nullable', 'string'],
            'precio' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'stock' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'formato' => ['nullable', 'string', 'max:100'],
            'imagen' => ['nullable', 'string', 'max:255'],
            'activo' => ['required', 'boolean'],
            'destacado' => ['required', 'boolean'],
        ];
    }

    // Cada producto pertenece a una categoría mediante el campo categoria_id
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }
}
