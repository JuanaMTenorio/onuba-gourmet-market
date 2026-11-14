@extends('layouts.app')

@section('titulo', 'Iniciar sesión | Onuba Gourmet Market')

@section('contenido')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-9 col-md-6 col-lg-5">
                <h1 class="h2 mb-4">Iniciar sesión</h1>

                <form action="{{ route('login') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input id="email" name="email" type="email" class="form-control"
                            autocomplete="username" maxlength="255" value="{{ old('email') }}" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input id="password" name="password" type="password" class="form-control"
                            autocomplete="current-password" required>
                    </div>

                    <button type="submit" class="btn btn-dark">Iniciar sesión</button>
                </form>

                <p class="mt-3">¿Aún no tienes cuenta?
                    <a href="{{ route('registro') }}">Regístrate</a>
                </p>
            </div>
        </div>
    </div>
@endsection
