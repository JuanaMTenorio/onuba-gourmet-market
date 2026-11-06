<!-- 
    Vista Blade que contiene la parte visual de la página 
    Se llama inicio.blade.php porque no estoy creando un archivo normal si no una vista de Laravel,
    y Laravel utiliza un sistema de plantillas llamdo Blade, por eso la extensión es blade.php.

    Blade facilitará traer las categorías desde MySQL, que necesitaré, y generar HTML utilizando esos datos. 
-->


@extends('layouts.app')
 
{{-- Título de la pestaña (opcional: si lo quitas, se usa el de la plantilla) --}}
@section('titulo', 'ONUBA GOURMET MARKET')
 
@section('contenido')
 
<section class="hero">
    <div class="container">
        <div class="hero-contenido">
            <h1>Productos gourmet de Huelva,<br>directos a tu mesa</h1>
            <p>
                Descubre una selección de productos elaborados con
                tradición y sabor onubense
            </p>
            <a href="#titulo-categorias" class="btn-hero">Descubrir productos</a>
        </div>
    </div>
</section>
 
<section class="ventajas" aria-label="Ventajas de comprar en ONUBA">
    <div class="container">
        <ul class="ventajas-contenido">
 
            <li class="ventaja">
                <i class="bi bi-truck" aria-hidden="true"></i>
                <div>
                    <strong>Envíos a toda España</strong>
                    <span>Recibe tu pedido cómodamente</span>
                </div>
            </li>
            <li class="ventaja">
                <i class="bi bi-award" aria-hidden="true"></i>
                <div>
                    <strong>Calidad seleccionada</strong>
                    <span>Productos cuidadosamente elegidos</span>
                </div>
            </li>
            <li class="ventaja">
                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                <div>
                    <strong>Producto local</strong>
                    <span>Sabores de nuestra tierra</span>
                </div>
            </li>
            <li class="ventaja">
                <i class="bi bi-shield-check" aria-hidden="true"></i>
                <div>
                    <strong>Pago seguro</strong>
                    <span>Compra con total tranquilidad</span>
                </div>
            </li>
 
        </ul>
    </div>
</section>
 
<section class="categorias" aria-labelledby="titulo-categorias">
    <div class="container">
 
        <div class="titulo-seccion">
            <h2 id="titulo-categorias">Nuestras categorías</h2>
            <p>Descubre una selección de los mejores sabores de Huelva</p>
        </div>
 
        <div class="categorias-grid">
            {{-- Blade (bucle): por cada categoría que Laravel haya obtenido de MySQL
                 se muestra una tarjeta, sin repetir todo el código HTML --}}
            @foreach ($categorias as $categoria)
 
            <a href="/categoria/{{ $categoria->slug }}" class="categoria-enlace">
 
                <div class="categoria-card">
                    {{-- alt vacío: el nombre ya lo lee el lector de pantalla en el <h3>,
                         así no se repite dos veces --}}
                    <img
                        src="{{ asset('imagen/categorias/' . preg_replace('/\.(png|jpe?g)$/i', '.webp', $categoria->imagen)) }}"
                            srcset="{{ asset('imagen/categorias/' . preg_replace('/\.(png|jpe?g)$/i', '-400.webp', $categoria->imagen)) }} 400w, {{ asset('imagen/categorias/' . preg_replace('/\.(png|jpe?g)$/i', '.webp', $categoria->imagen)) }} 800w"
                            sizes="(min-width: 2000px) 320px, (min-width: 1400px) 16vw, 33vw"
                        alt=""
                        loading="{{ $loop->index < 6 ? 'eager' : 'lazy' }}"
                            decoding="async">
 
                    <h3>{{ $categoria->nombre }}</h3>
                </div>
 
            </a>
 
            @endforeach
 
        </div>
 
    </div>
</section>
 
{{-- Productos activos seleccionados mediante el campo destacado de la base de datos. --}}
@if ($destacados->isNotEmpty())
<section class="productos-destacados" aria-labelledby="titulo-destacados">
    <div class="container">
        <div class="titulo-seccion">
            <h2 id="titulo-destacados">Productos destacados</h2>
        </div>
        <div class="productos-grid productos-destacados-grid">
            @foreach ($destacados as $producto)
                @include('partials.tarjeta-producto', [
                    'esDestacado' => true,
                    'tamanosImagen' => '(min-width: 2000px) 320px, (min-width: 1400px) 16vw, 33vw',
                ])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Paisajes y gastronomía de Huelva: texto y enlace independientes de la imagen. --}}
<section class="sabores-tierra" aria-labelledby="titulo-sabores">
    <div class="sabores-contenido">
        <div class="sabores-texto">
            <h2 id="titulo-sabores">Sabores de nuestra tierra</h2>
            <p class="sabores-subtitulo">Tres paisajes, una provincia, un mismo sabor.</p>
            <p>
                Del mar, la sierra y la campiña nace una tierra única que nos regala
                productos con identidad, tradición y un sabor inconfundible. Te lo enseñamos.
            </p>
            <a class="btn-sabores" href="https://www.instagram.com/">
                <i class="bi bi-instagram" aria-hidden="true"></i>
                Descubre Huelva
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
        <figure class="sabores-paisajes">
            <img src="{{ asset('imagen/secciones/sabores-de-nuestra-tierra.webp') }}"
                width="2172" height="724"
                alt="Costa con barcos pesqueros, dehesa de la sierra y campos de viñedos y olivos de Huelva."
                loading="lazy" decoding="async">
            <figcaption class="visually-hidden">
                Costa de Huelva: conservas, salazones y vinos.
                Sierra de Huelva: jamones, embutidos, quesos y miel.
                Campiña: aceites de oliva y dulces tradicionales.
            </figcaption>
        </figure>
    </div>
</section>

{{-- Productores: fotografías decorativas y contenido accesible en HTML. --}}
<section class="productores" aria-labelledby="titulo-productores">
    <div class="productores-contenido">
        <div class="productores-mapa">
            <img src="{{ asset('imagen/secciones/mapa-productores-huelva.webp') }}"
                width="1536" height="1024"
                alt="" loading="lazy" decoding="async">
        </div>
        <div class="productores-texto">
            <h2 id="titulo-productores">Conoce a nuestros productores</h2>
            <p>Personas que mantienen viva la esencia de Huelva.</p>
            <ul class="productores-valores">
                <li>
                    <i class="bi bi-people" aria-hidden="true"></i>
                    <h3>Familias con historia</h3>
                    <p>Tradición y conocimiento de generación en generación.</p>
                </li>
                <li>
                    <i class="bi bi-feather" aria-hidden="true"></i>
                    <h3>Calidad y sostenibilidad</h3>
                    <p>Respeto por la tierra y el entorno.</p>
                </li>
                <li>
                    <i class="bi bi-heart" aria-hidden="true"></i>
                    <h3>Producto local</h3>
                    <p>Apoyo al productor de nuestra provincia.</p>
                </li>
            </ul>
            <a class="btn-sabores" href="https://www.instagram.com/">
                <i class="bi bi-instagram" aria-hidden="true"></i>
                Conoce sus historias
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
        <div class="productores-foto">
            <img src="{{ asset('imagen/secciones/productor-dehesa-huelva.webp') }}"
                width="1536" height="1024"
                alt="Ganadero de espaldas junto a cerdos ibéricos en una dehesa de encinas."
                loading="lazy" decoding="async">
        </div>
    </div>
</section>

@endsection
