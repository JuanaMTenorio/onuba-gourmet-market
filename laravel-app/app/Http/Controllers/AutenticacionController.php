<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Services\Carrito;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AutenticacionController extends Controller
{
    // Valido los datos del registro, creo la cuenta y abro la sesión
    public function registrar(Request $peticion, Carrito $carrito): RedirectResponse {

        // Compruebo los bytes de la contraseña antes de que el modelo la convierta en un hash
        $validarFormatoContrasena = function (string $campo, string $contrasena, \Closure $mostrarError): void {
            $cantidadBytes = strlen($contrasena);
            $superaLimiteBytes = $cantidadBytes > 72;
            $contieneByteNulo = str_contains($contrasena, "\0");

            if ($superaLimiteBytes || $contieneByteNulo) {
                $mostrarError('La contraseña es demasiado larga o contiene un carácter no válido.');
            }
        };

        $reglasRegistro = [
            'nombre' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:usuarios,email'],
            'telefono' => ['required', 'string', 'max:20'],
            'direccion' => ['required', 'string', 'max:255'],
            'codigo_postal' => ['required', 'string', 'max:10'],
            'localidad' => ['required', 'string', 'max:100'],
            'provincia' => ['required', 'string', 'max:100'],
            'password' => [
                'bail',
                'required',
                'string',
                'min:8',
                'confirmed',
                $validarFormatoContrasena,
            ],
        ];

        $mensajesRegistro = [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute admite hasta :max caracteres.',
            'email.email' => 'Escribe un correo electrónico válido.',
            'email.unique' => 'Ya existe una cuenta con este correo electrónico.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];

        $nombresCamposRegistro = [
            'direccion' => 'dirección',
            'codigo_postal' => 'código postal',
            'telefono' => 'teléfono',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
        ];

        $datosValidados = $peticion->validate($reglasRegistro, $mensajesRegistro, $nombresCamposRegistro);

        // El modelo guarda la contraseña como hash y la tabla asigna el rol de cliente
        $usuario = Usuario::create($datosValidados);
        Auth::login($usuario);

        // Paso los productos del visitante a su cuenta y renuevo el identificador de sesión
        $carrito->incorporarVisitante($peticion);
        $sesion = $peticion->session();
        $sesion->regenerate();

        // Vuelvo a la página que pidió el cliente o a la portada si no había ninguna
        $redireccion = redirect();

        return $redireccion->intended('/');
    }

    // Compruebo el correo y la contraseña para iniciar sesión
    public function entrar(Request $peticion, Carrito $carrito): RedirectResponse {

        $reglasEntrada = [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];

        $mensajesEntrada = [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'email.email' => 'Escribe un correo electrónico válido.',
            'email.max' => 'El correo electrónico admite hasta :max caracteres.',
        ];

        $nombresCamposEntrada = [
            'email' => 'correo electrónico',
            'password' => 'contraseña',
        ];

        $credenciales = $peticion->validate($reglasEntrada, $mensajesEntrada, $nombresCamposEntrada);

        // Laravel busca la cuenta por correo y verifica la contraseña
        $sesionIniciada = Auth::attempt($credenciales);

        if (! $sesionIniciada) {
            $erroresEntrada = [
                'email' => 'El correo electrónico o la contraseña no son correctos.',
            ];

            // Devuelvo el error al formulario y recupero únicamente el correo escrito
            $respuesta = back();
            $respuesta->withErrors($erroresEntrada);
            $respuesta->onlyInput('email');

            return $respuesta;
        }

        // Incorporo el carrito visitante a la cuenta y renuevo el identificador de sesión
        $carrito->incorporarVisitante($peticion);
        $sesion = $peticion->session();
        $sesion->regenerate();

        $redireccion = redirect();

        return $redireccion->intended('/');
    }

    // Cierro la sesión del navegador y conservo el carrito de la cuenta en MySQL
    public function salir(Request $peticion, Carrito $carrito): RedirectResponse {

        $carrito->incorporarVisitante($peticion);
        Auth::logout();

        // Elimino los datos de la sesión anterior y renuevo el token de los formularios
        $sesion = $peticion->session();
        $sesion->invalidate();
        $sesion->regenerateToken();

        return redirect('/');
    }
}
