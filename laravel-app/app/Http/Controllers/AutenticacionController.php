<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AutenticacionController extends Controller
{
    public function registrar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:usuarios,email'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'direccion' => ['required', 'string', 'max:255'],
            'codigo_postal' => ['required', 'string', 'max:10'],
            'localidad' => ['required', 'string', 'max:100'],
            'provincia' => ['required', 'string', 'max:100'],
            'password' => ['bail', 'required', 'string', 'min:8', 'confirmed', function (string $atributo, string $valor, \Closure $error): void {
                if (strlen($valor) > 72 || str_contains($valor, "\0")) {
                    $error('La contraseña es demasiado larga o contiene un carácter no válido.');
                }
            }],
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute admite hasta :max caracteres.',
            'email.email' => 'Escribe un correo electrónico válido.',
            'email.unique' => 'Ya existe una cuenta con este correo electrónico.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ], [
            'direccion' => 'dirección',
            'codigo_postal' => 'código postal',
            'telefono' => 'teléfono',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
        ]);

        // El modelo guarda el hash y la tabla asigna el rol de cliente.
        $usuario = Usuario::create($datos);
        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function entrar(Request $request): RedirectResponse
    {
        $credenciales = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'email.email' => 'Escribe un correo electrónico válido.',
            'email.max' => 'El correo electrónico admite hasta :max caracteres.',
        ], [
            'email' => 'correo electrónico',
            'password' => 'contraseña',
        ]);

        if (! Auth::attempt($credenciales)) {
            return back()->withErrors([
                'email' => 'El correo electrónico o la contraseña no son correctos.',
            ])->onlyInput('email');
        }

        // Cambia el identificador de sesión y conserva los datos del carrito.
        $request->session()->regenerate();

        return redirect()->intended('/');
    }
}
