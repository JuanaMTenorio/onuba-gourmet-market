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
    {{-- El número de pedido solo llega al carrito después de confirmar el pago --}}
    @php
        $identificadorPedidoPagado = session('pedido_pagado_id');
        $hayPedidoPagado = $identificadorPedidoPagado !== null;

        // Compruebo si el carrito tiene productos para elegir qué sección mostrar
        $cantidadLineasCarrito = count($lineas);
        $carritoEstaVacio = $cantidadLineasCarrito === 0;
    @endphp

    @if ($carritoEstaVacio)
        <section class="carrito-vacio" aria-label="Carrito vacío">
            <i class="bi bi-bag" aria-hidden="true"></i>
            <h2>Tu carrito está vacío</h2>

            {{-- Si se acaba de confirmar un pago, muestro el aviso debajo del título --}}
            @if ($hayPedidoPagado)
                @include('partials.confirmacion-pago')
            @endif

            <p>Descubre los sabores de Huelva y elige tus favoritos</p>
            <a href="{{ url('/') }}" class="carrito-seguir"><i class="bi bi-arrow-left" aria-hidden="true"></i> Seguir comprando</a>
        </section>
    @else
        @if ($hayPedidoPagado)
            @include('partials.confirmacion-pago')
        @endif

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
                        @php
                            // Separo los datos del producto de la cantidad y el subtotal del carrito
                            $producto = $linea['producto'];
                            $identificadorProducto = $producto->id;
                            $cantidadProducto = $linea['cantidad'];
                            $stockDisponible = $producto->stock;

                            // Convierto el nombre de la imagen del catálogo en su dirección pública
                            $nombreImagen = (string) $producto->imagen;
                            $nombreImagenWebp = preg_replace('/\.(png|jpe?g)$/i', '.webp', $nombreImagen);
                            $direccionImagenProducto = asset('imagen/productos/' . $nombreImagenWebp);

                            $precioFormateado = number_format($producto->precio, 2, ',', '.');
                            $subtotalFormateado = number_format($linea['subtotal'], 2, ',', '.');

                            // Con el pago abierto desactivo los cambios y aplico los límites de cantidad
                            $desactivarBotonReducir = $pagoAbierto || $cantidadProducto <= 1;
                            $desactivarBotonAumentar = $pagoAbierto || $cantidadProducto >= $stockDisponible;

                            $direccionActualizarProducto = route('carrito.actualizar', $identificadorProducto);
                            $direccionEliminarProducto = route('carrito.eliminar', $identificadorProducto);
                        @endphp

                        <article class="carrito-producto" aria-labelledby="nombre-{{ $identificadorProducto }}">
                            <div class="carrito-detalle">
                                <img src="{{ $direccionImagenProducto }}"
                                    alt="" width="140" height="100">
                                <div>
                                    <h2 id="nombre-{{ $identificadorProducto }}">{{ $producto->nombre }}</h2>
                                    @if ($producto->formato)
                                        <p>{{ $producto->formato }}</p>
                                    @endif
                                </div>
                            </div>

                            <p class="carrito-precio">
                                <span class="visually-hidden">Precio por unidad </span>{{ $precioFormateado }} €
                            </p>

                            {{-- Los botones cambian la cantidad y envían este mismo formulario --}}
                            <form method="POST" action="{{ $direccionActualizarProducto }}" class="carrito-formulario-cantidad">
                                @csrf
                                @method('PATCH')
                                <label for="cantidad-{{ $identificadorProducto }}" class="visually-hidden">Cantidad de {{ $producto->nombre }}</label>
                                <div class="carrito-selector">
                                    <button
                                        type="button"
                                        class="carrito-menos"
                                        aria-label="Reducir cantidad de {{ $producto->nombre }}"
                                        @disabled($desactivarBotonReducir)>−</button>

                                    <input
                                        type="number"
                                        id="cantidad-{{ $identificadorProducto }}"
                                        name="cantidad"
                                        value="{{ $cantidadProducto }}"
                                        min="1"
                                        max="{{ $stockDisponible }}"
                                        @disabled($pagoAbierto)
                                        required>

                                    <button
                                        type="button"
                                        class="carrito-mas"
                                        aria-label="Aumentar cantidad de {{ $producto->nombre }}"
                                        @disabled($desactivarBotonAumentar)>+</button>
                                </div>
                                <button type="submit" class="carrito-actualizar" @disabled($pagoAbierto)>Actualizar</button>
                            </form>

                            <p class="carrito-subtotal">
                                <span class="carrito-etiqueta-subtotal">Subtotal </span>{{ $subtotalFormateado }} €
                            </p>

                            <form method="POST" action="{{ $direccionEliminarProducto }}" class="carrito-formulario-eliminar">
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="carrito-eliminar"
                                    aria-label="Eliminar {{ $producto->nombre }} del carrito"
                                    @disabled($pagoAbierto)>
                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                </button>
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
                @php
                    // Cuento unidades para que dos ejemplares del mismo producto sumen dos
                    $cantidadesProductos = array_column($lineas, 'cantidad');
                    $cantidadTotalProductos = array_sum($cantidadesProductos);
                    $totalFormateado = number_format($total, 2, ',', '.');
                @endphp

                <h2 id="titulo-resumen">Resumen del pedido</h2>
                <div class="carrito-importes">
                    <p><span>Subtotal ({{ $cantidadTotalProductos }} unidades)</span><span>{{ $totalFormateado }} €</span></p>
                    <p><span>Gastos de envío estándar</span><span>0,00 €</span></p>
                    <p class="carrito-total"><span>Total del pedido</span><strong>{{ $totalFormateado }} €</strong></p>
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
                    <button id="boton-pago" type="submit" form="formulario-pago" class="carrito-seguir" disabled>
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
                <noscript><p class="carrito-aviso-stripe">Activa JavaScript para abrir el formulario de pago.</p></noscript>
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
@if ($mostrarEnvio && ! $carritoEstaVacio)
    @php
        $clavePublicableStripe = config('services.stripe.publicable');
    @endphp

    <script src="https://js.stripe.com/endive/stripe.js" defer></script>
    <script>
        const formularioPago = document.getElementById('formulario-pago');
        const botonPago = document.getElementById('boton-pago');
        const avisoPago = document.getElementById('aviso-stripe');
        const seccionPago = document.getElementById('seccion-pago');

        // Desactivo los campos que ya no pueden cambiarse mientras Stripe prepara el cobro
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

        // Envío el formulario a Laravel para pedir el código de la sesión de Stripe
        async function pedirSesionPago() {
            const camposFormulario = new FormData(formularioPago);
            const direccionIniciarPago = formularioPago.action;
            const opcionesPeticion = {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                },
                body: camposFormulario,
            };

            const respuesta = await fetch(direccionIniciarPago, opcionesPeticion);
            if (!respuesta.headers.get('Content-Type')?.includes('application/json')) {
                throw new Error('No se pudo abrir el pago. Recarga el carrito e inténtalo de nuevo.');
            }

            const datosRespuesta = await respuesta.json();
            const codigoSesionPago = datosRespuesta.clientSecret;
            const peticionCorrecta = respuesta.ok;

            if (!peticionCorrecta || !codigoSesionPago) {
                let mensaje = 'No se pudo abrir el pago.';
                if (datosRespuesta.message) {
                    mensaje = datosRespuesta.message;
                }

                const erroresValidacion = datosRespuesta.errors;
                // Muestro el primer error de validación que devuelve Laravel
                if (erroresValidacion) {
                    const erroresPorCampo = Object.values(erroresValidacion);
                    for (const erroresDelCampo of erroresPorCampo) {
                        if (erroresDelCampo.length > 0) {
                            const primerError = erroresDelCampo[0];
                            if (primerError) {
                                mensaje = primerError;
                            }

                            break;
                        }
                    }
                }
                throw new Error(mensaje);
            }

            bloquearEdicionDelPedido();
            botonPago.textContent = 'Continuar pago';

            return codigoSesionPago;
        }

        // Muestro el formulario de Stripe dentro del carrito
        async function mostrarPago(evento) {
            evento.preventDefault();
            botonPago.disabled = true;
            avisoPago.hidden = false;
            avisoPago.textContent = 'Abriendo el pago…';

            try {
                const clavePublicable = @json($clavePublicableStripe);
                const claveEsTexto = typeof clavePublicable === 'string';
                const hayClaveDePrueba = claveEsTexto && clavePublicable.startsWith('pk_test_');
                const bibliotecaStripeCargada = typeof Stripe === 'function';

                if (!bibliotecaStripeCargada) {
                    throw new Error('El formulario de Stripe aún no está disponible. Inténtalo en unos segundos o recarga el carrito.');
                }

                if (!hayClaveDePrueba) {
                    throw new Error('Stripe no está disponible. Comprueba la clave publicable de pruebas y recarga la página.');
                }

                const clienteStripe = Stripe(clavePublicable);
                const opcionesFormularioStripe = {
                    fetchClientSecret: pedirSesionPago,
                };
                const formularioStripe = await clienteStripe.createEmbeddedCheckoutPage(opcionesFormularioStripe);

                seccionPago.hidden = false;
                formularioStripe.mount('#checkout-stripe');
                seccionPago.scrollIntoView({ block: 'start' });
                botonPago.hidden = true;
                avisoPago.hidden = true;
            } catch (error) {
                let mensajeAviso = 'No se pudo abrir el pago.';
                if (error.message) {
                    mensajeAviso = error.message;
                }

                avisoPago.textContent = mensajeAviso;
                botonPago.disabled = false;
            }
        }

        formularioPago.addEventListener('submit', mostrarPago);
        botonPago.disabled = false;
    </script>
@endif
@endpush
