<article class="tarjeta-producto">

    {{-- IMAGEN DEL PRODUCTO --}}
    <div class="imagen-producto">

        <img
            src="{{ asset('imagen/productos/' . preg_replace('/\.(png|jpe?g)$/i', '.webp', $producto->imagen)) }}"
            srcset="{{ asset('imagen/productos/' . preg_replace('/\.(png|jpe?g)$/i', '-400.webp', $producto->imagen)) }} 400w, {{ asset('imagen/productos/' . preg_replace('/\.(png|jpe?g)$/i', '.webp', $producto->imagen)) }} 800w"
            sizes="{{ $tamanosImagen ?? '(min-width: 1400px) 25vw, (min-width: 768px) 33vw, 50vw' }}"
            alt="{{ $producto->nombre }}"
            loading="{{ $loop->index < 3 ? 'eager' : 'lazy' }}"
            decoding="async">

    </div>


    {{-- INFORMACIÓN DEL PRODUCTO --}}
    <div class="informacion-producto">

        @if ($esDestacado ?? false)
        <h3 class="nombre-producto">{{ $producto->nombre }}</h3>
        @else
        <h2 class="nombre-producto">{{ $producto->nombre }}</h2>
        @endif

        @if ($producto->formato)
        <p class="formato-producto">
            {{ $producto->formato }}
        </p>
        @endif

        @if ($esDestacado ?? false)
        <div class="compra-destacado">
        @else
        <div class="compra-categoria">
        @endif
        <p class="precio-producto">
            {{ number_format($producto->precio, 2, ',', '.') }} €
        </p>

        @if ($producto->stock > 0)

        <form method="POST" action="{{ route('carrito.agregar', $producto->id) }}">
        {{-- Laravel comprueba este token para proteger el formulario --}}
        @csrf
        <button
            type="submit"
            class="btn-carrito">
            <i class="bi bi-cart3" aria-hidden="true"></i>
            <span class="visually-hidden">Añadir al carrito: {{ $producto->nombre }}</span>
        </button>
        </form>

        @else

        <span class="producto-agotado">
            Producto agotado
        </span>

        @endif
        </div>

    </div>

</article>
