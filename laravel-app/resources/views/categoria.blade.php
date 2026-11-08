{{-- Página de productos de una categoría de ONUBA GOURMET MARKET --}}

@extends('layouts.app')

{{-- Estilos de la página de categorías --}}
@push('estilos')
    <link rel="stylesheet" href="{{ asset('css/paginaCategorias.css') }}?v={{ filemtime(public_path('css/paginaCategorias.css')) }}">
@endpush

{{-- Título de la pestaña del navegador --}}
@section('titulo', $categoria->nombre . ' | ONUBA GOURMET MARKET')

@section('clase-body', 'vista-categoria')

@section('contenido')

<div class="pagina-categoria">

    {{-- MENÚ LATERAL DE CATEGORÍAS --}}
    <aside class="categorias-lateral" aria-label="Categorías de productos">

        <h2>Categorías</h2>

        @include('partials.menu-categorias')

    </aside>


    {{-- CONTENIDO PRINCIPAL DE LA CATEGORÍA --}}
    <div class="contenido-categoria">

        {{-- BANNER DE LA CATEGORÍA --}}
        <section class="banner-categoria {{ $bannerCategoria ? 'banner-categoria-con-imagen' : '' }}"
            aria-labelledby="titulo-categoria">

            @if ($bannerCategoria)
            {{-- El texto equivalente permanece disponible para lectores de pantalla. --}}
            <img class="banner-categoria-imagen"
                src="{{ asset($bannerCategoria) }}"
                alt="" loading="eager" fetchpriority="high">
            @endif

            <div class="banner-categoria-contenido {{ $bannerCategoria ? 'visually-hidden' : '' }}">

                <h1 id="titulo-categoria">
                    {{ $categoria->nombre }}
                </h1>

                @if ($categoria->descripcion)
                <p>{{ $categoria->descripcion }}</p>
                @endif

            </div>

        </section>


        {{-- Móvil y tablet: selector cerrado inicialmente, después del banner. --}}
        <details class="categorias-desplegable">
            <summary>
                <i class="bi bi-list" aria-hidden="true"></i>
                <span>Categorías</span>
                <i class="bi bi-chevron-down indicador-categorias" aria-hidden="true"></i>
            </summary>
            @include('partials.menu-categorias')
        </details>

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
                    aria-describedby="ayuda-orden"
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

                <span id="ayuda-orden" class="visually-hidden">
                    Al cambiar la opción se recarga la página con los productos ordenados.
                </span>

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

                @include('partials.tarjeta-producto')

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

@push('scripts')
<script>
    // Ordenar al seleccionar en todos los dispositivos. Sin JavaScript se conserva el botón
    const selectorOrden = document.getElementById('orden-productos');
    if (selectorOrden) {
        selectorOrden.form.classList.add('ordenacion-automatica');
        selectorOrden.addEventListener('change', () => {
            selectorOrden.form.requestSubmit();
        });
    }
</script>
@endpush
