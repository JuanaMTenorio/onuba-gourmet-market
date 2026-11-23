<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;

// Representa un usuario de la tienda y permite que Laravel lo autentique
// Fillable enumera los campos que puedo guardar con Usuario::create al registrar la cuenta
// La base de datos asigna el identificador y el rol de cliente
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
// Hidden oculta el hash de la contraseña al convertir el usuario en un array o en JSON
#[Hidden(['password'])]
class Usuario extends Authenticatable
{
    // Indica el nombre de la tabla que utiliza este modelo
    protected $table = 'usuarios';

    // Laravel convierte la contraseña en un hash antes de guardarla
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
