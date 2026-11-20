@extends('layouts.app')

@section('titulo', 'Carrito | ONUBA GOURMET MARKET')

@section('clase-body', 'vista-carrito')

@push('estilos')
    <link rel="stylesheet" href="{{ asset('css/paginaCarrito.css') }}?v={{ filemtime(public_path('css/paginaCarrito.css')) }}">
@endpush

@section('contenido')
{{-- El título permanece como texto para adaptarse a cualquier pantalla --}}
<section class="carrito-banner" aria-labelledby="titulo-carrito">
    <i class="bi bi-cart3" aria-hidden="true"></i>
    <h1 id="titulo-carrito">Tu carrito de compra</h1>
    <p>Revisa tus productos y prepara tu pedido</p>
</section>

<div class="carrito-contenedor">
    @if (count($lineas) === 0)
        <section class="carrito-vacio" aria-label="Carrito vacío">
            <i class="bi bi-bag" aria-hidden="true"></i>
            <h2>Tu carrito está vacío</h2>
            <p>Descubre los sabores de Huelva y elige tus favoritos</p>
            <a href="{{ url('/') }}" class="carrito-seguir"><i class="bi bi-arrow-left" aria-hidden="true"></i> Seguir comprando</a>
        </section>
    @else
        <div class="carrito-distribucion">
            <div class="carrito-columna-productos">
                <section class="carrito-productos" aria-label="Productos del carrito">
                    {{-- Los encabezados se muestran en ordenador y tablet --}}
                    <div class="carrito-encabezados" aria-hidden="true">
                        <span>Producto</span>
                        <span>Precio</span>
                        <span>Cantidad</span>
                        <span>Subtotal</span>
                        <span></span>
                    </div>

                    @foreach ($lineas as $linea)
                        <article class="carrito-producto" aria-labelledby="nombre-{{ $linea['producto']->id }}">
                            <div class="carrito-detalle">
                                <img src="{{ asset('imagen/productos/' . preg_replace('/\.(png|jpe?g)$/i', '.webp', $linea['producto']->imagen)) }}"
                                    alt="" width="140" height="100">
                                <div>
                                    <h2 id="nombre-{{ $linea['producto']->id }}">{{ $linea['producto']->nombre }}</h2>
                                    @if ($linea['producto']->formato)
                                        <p>{{ $linea['producto']->formato }}</p>
                                    @endif
                                </div>
                            </div>

                            <p class="carrito-precio"><span class="visually-hidden">Precio por unidad </span>{{ number_format($linea['producto']->precio, 2, ',', '.') }} €</p>

                            {{-- Los botones cambian la cantidad y envían este mismo formulario --}}
                            <form method="POST" action="{{ route('carrito.actualizar', $linea['producto']->id) }}" class="carrito-formulario-cantidad">
                                @csrf
                                @method('PATCH')
                                <label for="cantidad-{{ $linea['producto']->id }}" class="visually-hidden">Cantidad de {{ $linea['producto']->nombre }}</label>
                                <div class="carrito-selector">
                                    <button type="button" class="carrito-menos" aria-label="Reducir cantidad de {{ $linea['producto']->nombre }}" @disabled($pagoAbierto || $linea['cantidad'] <= 1)>−</button>
                                    <input type="number" id="cantidad-{{ $linea['producto']->id }}" name="cantidad"
                                        value="{{ $linea['cantidad'] }}" min="1" max="{{ $linea['producto']->stock }}" @disabled($pagoAbierto) required>
                                    <button type="button" class="carrito-mas" aria-label="Aumentar cantidad de {{ $linea['producto']->nombre }}" @disabled($pagoAbierto || $linea['cantidad'] >= $linea['producto']->stock)>+</button>
                                </div>
                                <button type="submit" class="carrito-actualizar" @disabled($pagoAbierto)>Actualizar</button>
                            </form>

                            <p class="carrito-subtotal"><span class="carrito-etiqueta-subtotal">Subtotal </span>{{ number_format($linea['subtotal'], 2, ',', '.') }} €</p>

                            <form method="POST" action="{{ route('carrito.eliminar', $linea['producto']->id) }}" class="carrito-formulario-eliminar">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="carrito-eliminar" aria-label="Eliminar {{ $linea['producto']->nombre }} del carrito" @disabled($pagoAbierto)><i class="bi bi-trash3" aria-hidden="true"></i></button>
                            </form>
                        </article>
                    @endforeach
                </section>

                @if ($mostrarEnvio)
                    {{-- La dirección y el formulario de Stripe se muestran debajo de los productos --}}
                    @include('partials.direccion-envio')
                    <section id="seccion-pago" class="carrito-resumen" aria-labelledby="titulo-pago" hidden>
                        <h2 id="titulo-pago">Pago</h2>
                        <div id="checkout-stripe"></div>
                    </section>
                @endif
            </div>

            <aside class="carrito-resumen" aria-labelledby="titulo-resumen">
                <h2 id="titulo-resumen">Resumen del pedido</h2>
                <div class="carrito-importes">
                    <p><span>Subtotal ({{ array_sum(array_column($lineas, 'cantidad')) }} unidades)</span><span>{{ number_format($total, 2, ',', '.') }} €</span></p>
                    <p><span>Gastos de envío estándar</span><span>0,00 €</span></p>
                    <p class="carrito-total"><span>Total del pedido</span><strong>{{ number_format($total, 2, ',', '.') }} €</strong></p>
                </div>

                @if (! $mostrarEnvio)
                    @auth
                        <a href="{{ route('carrito.index', ['envio' => 'habitual']) }}#direccion-envio" class="carrito-pagar">
                            Finalizar compra
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="carrito-pagar">Finalizar compra</a>
                    @endauth
                @endif
                <p class="carrito-envio"><i class="bi bi-truck" aria-hidden="true"></i> Envíos a toda España</p>
            </aside>

            {{-- Mantengo Seguir comprando y Realizar pago juntos al final del carrito --}}
            <div class="carrito-acciones">
                <a href="{{ url('/') }}" class="carrito-seguir"><i class="bi bi-arrow-left" aria-hidden="true"></i> Seguir comprando</a>
                @if ($mostrarEnvio)
                    <button id="boton-pago" type="submit" form="formulario-pago" class="carrito-seguir">
                        @if ($pagoAbierto)
                            Continuar pago
                        @else
                            Realizar pago
                        @endif
                    </button>
                    <form id="modificar-pedido" method="POST" action="{{ route('carrito.pago.modificar') }}" @if (! $pagoAbierto) hidden @endif>
                        @csrf
                        <button type="submit" class="carrito-seguir">Modificar pedido</button>
                    </form>
                @endif
            </div>
            @if ($mostrarEnvio)
                <p id="aviso-stripe" class="carrito-aviso-stripe" role="status" hidden></p>
            @endif
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    // Sin JavaScript se puede escribir la cantidad y pulsar Actualizar
    const formulariosCantidad = document.querySelectorAll('.carrito-formulario-cantidad');

    formulariosCantidad.forEach(function (formulario) {
        const campoCantidad = formulario.querySelector('input[name="cantidad"]');
        const botonMenos = formulario.querySelector('.carrito-menos');
        const botonMas = formulario.querySelector('.carrito-mas');
        const botonActualizar = formulario.querySelector('.carrito-actualizar');

        botonMenos.addEventListener('click', function () {
            campoCantidad.stepDown();
            formulario.requestSubmit();
        });

        botonMas.addEventListener('click', function () {
            campoCantidad.stepUp();
            formulario.requestSubmit();
        });

        campoCantidad.addEventListener('change', function () {
            formulario.requestSubmit();
        });

        botonActualizar.hidden = true;
    });
</script>
@if ($mostrarEnvio && count($lineas) > 0)
    <script src="https://js.stripe.com/endive/stripe.js"></script>
    <script>
        const formularioPago = document.getElementById('formulario-pago');
        const botonPago = document.getElementById('boton-pago');
        const avisoPago = document.getElementById('aviso-stripe');
        const seccionPago = document.getElementById('seccion-pago');

        // Función: Desactivar la edición del carrito y del envío al abrir el pago
        function bloquearEdicionDelPedido() {
            const formularioModificar = document.getElementById('modificar-pedido');
            const camposEnvio = document.getElementById('campos-envio');
            const opcionesEnvio = document.getElementById('opciones-envio');
            const camposCarrito = document.querySelectorAll('.carrito-productos button, .carrito-productos input');

            formularioModificar.hidden = false;
            camposEnvio.disabled = true;
            if (opcionesEnvio !== null) {
                opcionesEnvio.classList.add('d-none');
            }

            for (const campo of camposCarrito) {
                campo.disabled = true;
            }
        }

        // Función: Enviar el formulario a Laravel y pedir el código de la sesión de Stripe
        async function pedirSesionPago() {
            const camposFormulario = new FormData(formularioPago);
            const respuesta = await fetch(formularioPago.action, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: camposFormulario,
            });
            const datos = await respuesta.json();

            if (!respuesta.ok || !datos.clientSecret) {
                let mensaje = datos.message || 'No se pudo abrir el pago.';

                // Muestro el primer error de validación que devuelve Laravel
                if (datos.errors) {
                    for (const erroresDelCampo of Object.values(datos.errors)) {
                        if (erroresDelCampo.length > 0) {
                            mensaje = erroresDelCampo[0] || mensaje;
                            break;
                        }
                    }
                }
                throw new Error(mensaje);
            }

            bloquearEdicionDelPedido();
            return datos.clientSecret;
        }

        // Función: Abrir el formulario de Stripe o mostrar el error
        async function mostrarPago(evento) {
            evento.preventDefault();
            botonPago.disabled = true;
            avisoPago.hidden = false;
            avisoPago.textContent = 'Abriendo el pago…';

            try {
                const clave = @json(config('services.stripe.publicable'));

                const hayClaveDePrueba = typeof clave === 'string' && clave.startsWith('pk_test_');
                if (typeof Stripe !== 'function' || !hayClaveDePrueba) {
                    throw new Error('Stripe no está disponible. Comprueba la clave publicable de pruebas y recarga la página.');
                }

                const stripe = Stripe(clave);
                const checkout = await stripe.createEmbeddedCheckoutPage({
                    fetchClientSecret: pedirSesionPago,
                });

                seccionPago.hidden = false;
                checkout.mount('#checkout-stripe');
                seccionPago.scrollIntoView({ block: 'start' });
                botonPago.hidden = true;
                avisoPago.hidden = true;
            } catch (error) {
                avisoPago.textContent = error.message || 'No se pudo abrir el pago.';
                botonPago.disabled = false;
            }
        }

        formularioPago.addEventListener('submit', mostrarPago);
    </script>
@endif
@endpush
