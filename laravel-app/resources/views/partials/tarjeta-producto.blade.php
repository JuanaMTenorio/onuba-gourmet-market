@php
    // Preparo las dos versiones de imagen para distintos tamaños de pantalla
    $imagenProducto = (string) $producto->imagen;
    $nombreImagenGrande = preg_replace('/\.(png|jpe?g)$/i', '.webp', $imagenProducto);
    $nombreImagenPequena = preg_replace('/\.(png|jpe?g)$/i', '-400.webp', $imagenProducto);

    $direccionImagenGrande = asset('imagen/productos/' . $nombreImagenGrande);
    $direccionImagenPequena = asset('imagen/productos/' . $nombreImagenPequena);

    $tamanosImagenProducto = '(min-width: 1400px) 25vw, (min-width: 768px) 33vw, 50vw';
    if (isset($tamanosImagen)) {
        $tamanosImagenProducto = $tamanosImagen;
    }

    // Cargo primero las imágenes de los primeros tres productos
    $cargaImagen = 'lazy';
    if ($loop->index < 3) {
        $cargaImagen = 'eager';
    }

    // El mismo HTML sirve para la portada y para las páginas de categorías
    $productoEsDestacado = false;
    $claseCompra = 'compra-categoria';
    if (!empty($esDestacado)) {
        $productoEsDestacado = true;
        $claseCompra = 'compra-destacado';
    }

    $precioFormateado = number_format($producto->precio, 2, ',', '.');
    $hayExistencias = $producto->stock > 0;
@endphp

<article class="tarjeta-producto">

    {{-- IMAGEN DEL PRODUCTO --}}
    <div class="imagen-producto">

        <img
            src="{{ $direccionImagenGrande }}"
            srcset="{{ $direccionImagenPequena }} 400w, {{ $direccionImagenGrande }} 800w"
            sizes="{{ $tamanosImagenProducto }}"
            alt="{{ $producto->nombre }}"
            loading="{{ $cargaImagen }}"
            decoding="async">

    </div>


    {{-- INFORMACIÓN DEL PRODUCTO --}}
    <div class="informacion-producto">

        @if ($productoEsDestacado)
        <h3 class="nombre-producto">{{ $producto->nombre }}</h3>
        @else
        <h2 class="nombre-producto">{{ $producto->nombre }}</h2>
        @endif

        @if ($producto->formato)
        <p class="formato-producto">
            {{ $producto->formato }}
        </p>
        @endif

        <div class="{{ $claseCompra }}">
            <p class="precio-producto">
                {{ $precioFormateado }} €
            </p>

            @if ($hayExistencias)
                @php
                    $direccionAgregarProducto = route('carrito.agregar', $producto->id);
                @endphp

                <form method="POST" action="{{ $direccionAgregarProducto }}">
                    {{-- Laravel comprueba este token para proteger el formulario --}}
                    @csrf

                    <button type="submit" class="btn-carrito">
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
