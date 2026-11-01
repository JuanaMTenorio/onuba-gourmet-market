{{-- Página de productos de una categoría de ONUBA GOURMET MARKET --}}

@extends('layouts.app')

{{-- Título de la pestaña del navegador --}}
@section('titulo', $categoria->nombre . ' | ONUBA GOURMET MARKET')

@section('contenido')

<div class="pagina-categoria">

    {{-- MENÚ LATERAL DE CATEGORÍAS --}}
    <aside class="categorias-lateral" aria-label="Categorías de productos">

        <h2>Categorías</h2>

        <nav aria-label="Seleccionar categoría">
            <ul class="lista-categorias">

                @foreach ($categorias as $item)

                <li>
                    <a
                        href="{{ url('/categoria/' . $item->slug) }}"
                        class="enlace-categoria {{ $item->id == $categoria->id ? 'categoria-activa' : '' }}"
                        @if ($item->id == $categoria->id)
                        aria-current="page"
                        @endif
                        >
                        <span>{{ $item->nombre }}</span>

                        @if ($item->id == $categoria->id)
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        @endif
                    </a>
                </li>

                @endforeach

            </ul>
        </nav>

    </aside>


    {{-- CONTENIDO PRINCIPAL DE LA CATEGORÍA --}}
    <div class="contenido-categoria">

        {{-- BANNER DE LA CATEGORÍA --}}
        <section class="banner-categoria"
            aria-labelledby="titulo-categoria">

            <div class="banner-categoria-contenido">

                <h1 id="titulo-categoria">
                    {{ $categoria->nombre }}
                </h1>

                @if ($categoria->descripcion)
                <p>{{ $categoria->descripcion }}</p>
                @endif

            </div>

        </section>


        {{-- BARRA DE RESULTADOS Y ORDENACIÓN --}}
        <div class="barra-productos">

            <p class="contador-productos">
                Mostrando {{ $productos->count() }}
                de {{ $productos->total() }} productos
            </p>

            <form
                method="GET"
                action="{{ url('/categoria/' . $categoria->slug) }}"
                class="formulario-ordenacion">

                <label for="orden-productos">
                    Ordenar por:
                </label>

                <select
                    id="orden-productos"
                    name="orden"
                    class="form-select">
                    <option value="nombre"
                        @selected(request('orden', 'nombre' )==='nombre' )>
                        Nombre
                    </option>

                    <option value="precio_asc"
                        @selected(request('orden')==='precio_asc' )>
                        Precio: menor a mayor
                    </option>

                    <option value="precio_desc"
                        @selected(request('orden')==='precio_desc' )>
                        Precio: mayor a menor
                    </option>

                </select>

                <button type="submit" class="btn-ordenar">
                    Ordenar
                </button>

            </form>

        </div>


        {{-- LISTADO DE PRODUCTOS --}}
        <section class="seccion-productos"
            aria-label="Productos de la categoría">

            <div class="productos-grid">

                @forelse ($productos as $producto)

                <article class="tarjeta-producto">

                    {{-- IMAGEN DEL PRODUCTO --}}
                    <div class="imagen-producto">

                        <img
                            src="{{ asset('imagen/productos/' . $producto->imagen) }}" 
                            alt="{{ $producto->nombre }}"
                            loading="lazy">

                    </div>


                    {{-- INFORMACIÓN DEL PRODUCTO --}}
                    <div class="informacion-producto">

                        <h2 class="nombre-producto">
                            {{ $producto->nombre }}
                        </h2>

                        @if ($producto->formato)
                        <p class="formato-producto">
                            {{ $producto->formato }}
                        </p>
                        @endif

                        <p class="precio-producto">
                            {{ number_format($producto->precio, 2, ',', '.') }} €
                        </p>

                        @if ($producto->stock > 0)

                        <button
                            type="button"
                            class="btn-carrito"
                            disabled
                            title="Funcionalidad del carrito pendiente de implementar">
                            <i class="bi bi-cart3" aria-hidden="true"></i>
                            Añadir al carrito
                        </button>

                        @else

                        <span class="producto-agotado">
                            Producto agotado
                        </span>

                        @endif

                    </div>

                </article>

                @empty

                <p class="sin-productos">
                    Actualmente no hay productos disponibles
                    en esta categoría.
                </p>

                @endforelse

            </div>

        </section>


        {{-- PAGINACIÓN --}}
        @if ($productos->hasPages())

        <nav class="paginacion-productos"
            aria-label="Páginas de productos">

            {{ $productos->withQueryString()->links('pagination::bootstrap-5') }}

        </nav>

        @endif

    </div>

</div>

@endsection