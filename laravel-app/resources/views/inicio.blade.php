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
            <a href="#" class="btn-hero">Descubrir productos</a>
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
                        src="{{ asset('imagen/categorias/' . $categoria->imagen) }}"
                        alt=""
                        loading="lazy">
 
                    <h3>{{ $categoria->nombre }}</h3>
                </div>
 
            </a>
 
            @endforeach
 
        </div>
 
    </div>
</section>
 
@endsection