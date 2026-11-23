@extends('layouts.app')

@section('titulo', 'Crear cuenta | Onuba Gourmet Market')

@section('contenido')
    @php
        $direccionRegistro = route('registro');
        $direccionInicioSesion = route('login');
    @endphp

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-9 col-md-6 col-lg-5">
                <h1 class="h2 mb-4">Crear cuenta</h1>

                {{-- El formulario envía los datos de la nueva cuenta al controlador de registro --}}
                <form action="{{ $direccionRegistro }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input id="nombre" name="nombre" type="text" class="form-control"
                            autocomplete="given-name" maxlength="100" value="{{ old('nombre') }}" required>
                    </div>

                    <div class="mb-3">
                        <label for="apellidos" class="form-label">Apellidos</label>
                        <input id="apellidos" name="apellidos" type="text" class="form-control"
                            autocomplete="family-name" maxlength="150" value="{{ old('apellidos') }}" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input id="email" name="email" type="email" class="form-control"
                            autocomplete="username" maxlength="255" value="{{ old('email') }}" required>
                    </div>

                    {{-- Mantengo los campos de contraseña vacíos si el formulario vuelve con errores --}}
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="form-control"
                            autocomplete="new-password"
                            minlength="8"
                            aria-describedby="ayuda-password aviso-password"
                            required>
                        <div id="ayuda-password" class="form-text">Usa al menos 8 caracteres.</div>
                        <div id="aviso-password" class="invalid-feedback d-block" aria-live="polite"></div>
                    </div>

                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            class="form-control"
                            autocomplete="new-password"
                            minlength="8"
                            required>
                    </div>

                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input id="telefono" name="telefono" type="tel" class="form-control"
                            autocomplete="tel" maxlength="20" value="{{ old('telefono') }}" required>
                    </div>

                    <fieldset class="mb-4">
                        <legend class="h5 mb-3">Dirección habitual</legend>

                        <div class="mb-3">
                            <label for="direccion" class="form-label">Dirección</label>
                            <input id="direccion" name="direccion" type="text" class="form-control"
                                autocomplete="address-line1" maxlength="255" value="{{ old('direccion') }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="codigo_postal" class="form-label">Código postal</label>
                            <input id="codigo_postal" name="codigo_postal" type="text" class="form-control"
                                autocomplete="postal-code" maxlength="10" value="{{ old('codigo_postal') }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="localidad" class="form-label">Localidad</label>
                            <input id="localidad" name="localidad" type="text" class="form-control"
                                autocomplete="address-level2" maxlength="100" value="{{ old('localidad') }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="provincia" class="form-label">Provincia</label>
                            <input id="provincia" name="provincia" type="text" class="form-control"
                                autocomplete="address-level1" maxlength="100" value="{{ old('provincia') }}" required>
                        </div>
                    </fieldset>

                    <button type="submit" class="btn btn-dark">Crear cuenta</button>
                </form>

                <p class="mt-3">¿Ya tienes una cuenta?
                    <a href="{{ $direccionInicioSesion }}">Inicia sesión</a>
                </p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const campoContrasena = document.getElementById('password');
        const avisoContrasena = document.getElementById('aviso-password');

        // Muestro un aviso al escribir una contraseña más corta de lo permitido
        function revisarLongitudContrasena() {
            const contrasenaEscrita = campoContrasena.value;
            const caracteresContrasena = Array.from(contrasenaEscrita);
            const cantidadCaracteres = caracteresContrasena.length;

            const longitudMinima = campoContrasena.minLength;
            const hayCaracteres = cantidadCaracteres > 0;
            const contrasenaDemasiadoCorta = hayCaracteres && cantidadCaracteres < longitudMinima;

            let mensajeAviso = '';
            if (contrasenaDemasiadoCorta) {
                mensajeAviso = 'La contraseña debe tener al menos 8 caracteres.';
            }

            campoContrasena.classList.toggle('is-invalid', contrasenaDemasiadoCorta);
            const valorAccesible = String(contrasenaDemasiadoCorta);
            campoContrasena.setAttribute('aria-invalid', valorAccesible);

            if (avisoContrasena.textContent !== mensajeAviso) {
                avisoContrasena.textContent = mensajeAviso;
            }
        }

        campoContrasena.addEventListener('input', revisarLongitudContrasena);
    </script>
@endpush
