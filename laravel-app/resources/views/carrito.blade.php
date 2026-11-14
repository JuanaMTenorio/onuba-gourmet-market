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
                                <button type="button" class="carrito-menos" aria-label="Reducir cantidad de {{ $linea['producto']->nombre }}" @disabled($linea['cantidad'] <= 1)>−</button>
                                <input type="number" id="cantidad-{{ $linea['producto']->id }}" name="cantidad"
                                    value="{{ $linea['cantidad'] }}" min="1" max="{{ $linea['producto']->stock }}" required>
                                <button type="button" class="carrito-mas" aria-label="Aumentar cantidad de {{ $linea['producto']->nombre }}" @disabled($linea['cantidad'] >= $linea['producto']->stock)>+</button>
                            </div>
                            <button type="submit" class="carrito-actualizar">Actualizar</button>
                        </form>

                        <p class="carrito-subtotal"><span class="carrito-etiqueta-subtotal">Subtotal </span>{{ number_format($linea['subtotal'], 2, ',', '.') }} €</p>

                        <form method="POST" action="{{ route('carrito.eliminar', $linea['producto']->id) }}" class="carrito-formulario-eliminar">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="carrito-eliminar" aria-label="Eliminar {{ $linea['producto']->nombre }} del carrito"><i class="bi bi-trash3" aria-hidden="true"></i></button>
                        </form>
                    </article>
                @endforeach
            </section>

            <aside class="carrito-resumen" aria-labelledby="titulo-resumen">
                <h2 id="titulo-resumen">Resumen del pedido</h2>
                <div class="carrito-importes">
                    <p><span>Subtotal ({{ array_sum(array_column($lineas, 'cantidad')) }} unidades)</span><span>{{ number_format($total, 2, ',', '.') }} €</span></p>
                    <p><span>Gastos de envío</span><span>Por calcular</span></p>
                    <p class="carrito-total"><span>Total de productos</span><strong>{{ number_format($total, 2, ',', '.') }} €</strong></p>
                </div>

                {{-- El botón se activará cuando se implemente el proceso de pago --}}
                <button type="button" class="carrito-pagar" disabled aria-describedby="aviso-pago"><i class="bi bi-credit-card" aria-hidden="true"></i> Finalizar compra</button>
                <p id="aviso-pago" class="carrito-aviso-pago">El pago estará disponible próximamente</p>
                <p class="carrito-envio"><i class="bi bi-truck" aria-hidden="true"></i> Envíos a toda España</p>
            </aside>

            <a href="{{ url('/') }}" class="carrito-seguir"><i class="bi bi-arrow-left" aria-hidden="true"></i> Seguir comprando</a>
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
@endpush
