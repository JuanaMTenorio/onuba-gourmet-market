{{-- Prueba local de Stripe por 5,00 € con un importe fijo --}}
@extends('layouts.app')

@section('titulo', 'Prueba de Stripe | Onuba Gourmet Market')

@section('contenido')
<div class="container py-5">
    <h1>Prueba de pago con Stripe</h1>

    @if ($mensaje)
        <p class="alert alert-info">{{ $mensaje }}</p>
    @endif

    @if ($configurada)
        <form id="formulario-stripe" method="POST" action="{{ url('/stripe-prueba') }}">
            @csrf
            <button type="submit" class="btn btn-dark">Probar pago de 5,00 €</button>
        </form>
        <p id="estado-stripe" class="mt-3" role="status"></p>
        <div id="pago-stripe"></div>
    @else
        <p>Para abrir la prueba, configura la clave publicable de Stripe de tu entorno de pruebas.</p>
    @endif
</div>
@endsection

@if ($configurada)
    @push('scripts')
        <script src="https://js.stripe.com/endive/stripe.js"></script>
        <script>
            const formularioStripe = document.getElementById('formulario-stripe');
            const botonStripe = formularioStripe.querySelector('button');
            const estadoStripe = document.getElementById('estado-stripe');

            // Función: Pedir a Laravel el código de una sesión de prueba de 5,00 €
            async function pedirSesionDePrueba() {
                const camposFormulario = new FormData(formularioStripe);
                const respuesta = await fetch(formularioStripe.action, {
                    method: 'POST',
                    headers: { Accept: 'application/json' },
                    body: camposFormulario,
                });
                const datos = await respuesta.json();

                if (!respuesta.ok || !datos.clientSecret) {
                    throw new Error(datos.message || 'No se pudo preparar el pago de prueba.');
                }

                return datos.clientSecret;
            }

            // Función: Mostrar el formulario de prueba o avisar si Stripe no se abre
            async function mostrarPagoDePrueba(evento) {
                evento.preventDefault();
                botonStripe.disabled = true;
                estadoStripe.textContent = 'Abriendo el formulario de pago…';

                try {
                    if (typeof Stripe !== 'function') {
                        throw new Error('No se pudo cargar Stripe. Recarga la página e inténtalo otra vez.');
                    }

                    const clavePublicable = @json($clavePublicable);
                    const stripe = Stripe(clavePublicable);
                    const checkout = await stripe.createEmbeddedCheckoutPage({
                        fetchClientSecret: pedirSesionDePrueba,
                    });

                    checkout.mount('#pago-stripe');
                    formularioStripe.hidden = true;
                    estadoStripe.textContent = '';
                } catch (error) {
                    estadoStripe.textContent = error.message || 'No se pudo abrir el pago de prueba.';
                    botonStripe.disabled = false;
                }
            }

            formularioStripe.addEventListener('submit', mostrarPagoDePrueba);
        </script>
    @endpush
@endif
