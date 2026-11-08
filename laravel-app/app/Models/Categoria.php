<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

// Representa una categoría del catálogo y permite consultar sus datos con Eloquent
// Permite asignar estos campos juntos al crear o actualizar una categoría
// El identificador y las fechas los gestiona Laravel con la base de datos
#[Fillable([
    'nombre',
    'slug',
    'descripcion',
    'imagen',
    'activa',
])]
class Categoria extends Model
{
    // Indica el nombre de la tabla para que Laravel no lo deduzca en inglés
    protected $table = 'categorias';

    // Interpreta el campo activa como un valor verdadero o falso
    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    // Reglas para comprobar los datos cuando se prepare el formulario de categorías
    // Deben utilizarse con validate antes de guardar porque Eloquent no las ejecuta solo
    public function reglasValidacion(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:50'],
            'slug' => ['required', 'string', 'max:120', Rule::unique('categorias', 'slug')->ignore($this)],
            'descripcion' => ['nullable', 'string'],
            'imagen' => ['nullable', 'string', 'max:255'],
            'activa' => ['required', 'boolean'],
        ];
    }

    // Una categoría puede tener varios productos relacionados mediante categoria_id
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'categoria_id');
    }
}
