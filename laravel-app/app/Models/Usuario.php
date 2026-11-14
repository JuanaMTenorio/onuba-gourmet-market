<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;

// Representa un usuario de la tienda y permite que Laravel lo autentique
// Permite asignar estos campos juntos al registrar un usuario
// La base de datos asigna el identificador y el rol de cliente, y Laravel gestiona las fechas
#[Fillable([
    'nombre',
    'apellidos',
    'email',
    'password',
    'telefono',
    'direccion',
    'codigo_postal',
    'localidad',
    'provincia',
])]
// Oculta la contraseña cuando el modelo se convierte en un array o en JSON
#[Hidden(['password'])]
class Usuario extends Authenticatable
{
    // Indica el nombre de la tabla que utiliza este modelo
    protected $table = 'usuarios';

    // Laravel convierte la contraseña en un hash antes de guardarla
    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
