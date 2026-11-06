{{--
    PLANTILLA COMÚN (layout)
    Lenguajes utilizados: HTML y Blade.
    Contiene todo lo que se repite en todas las páginas: el head, la barra superior,
    la cabecera con el menú y los scripts.

    Cada página (inicio, categoría, carrito...) usará @extends('layouts.app') y rellenará
    solo los "huecos" que aquí marcamos con yield:
      - yield('titulo') = el título de la pestaña del navegador
      - yield('contenido') = el contenido propio de cada página (dentro de main)
    Los stack son huecos opcionales donde una página puede añadir su propio CSS o JavaScript.
--}}
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- Si la página no define un título, se usa el que va como segundo valor --}}
    <title>@yield('titulo', 'ONUBA GOURMET MARKET')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/estilos.css') }}">
    @stack('estilos')
</head>

<body class="@yield('clase-body')">

    <!-- Permite a quien navega con teclado o lector de pantalla saltar la cabecera -->
    <a href="#contenido" class="saltar-contenido">Saltar al contenido</a>

    <div class="barra-superior">
        <div class="container">
            <div class="barra-contenido">
                <span>
                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    Productos gourmet de Huelva
                </span>
                <span>
                    <i class="bi bi-award" aria-hidden="true"></i>
                    Calidad y tradición
                </span>
                <!-- En categorías también se muestran los cuatro mensajes en móvil. -->
                <span class="mensaje-superior-adicional d-none d-sm-inline">
                    <i class="bi bi-truck" aria-hidden="true"></i>
                    Envíos a toda España
                </span>
                <span class="mensaje-superior-adicional d-none d-sm-inline">
                    <i class="bi bi-headset" aria-hidden="true"></i>
                    Atención al cliente
                </span>
            </div>
        </div>
    </div>

    <header class="cabecera-principal">
        <div class="container-fluid px-3 px-xl-5">
            <div class="cabecera-contenido">

                <!-- LOGO -->
                <a href="/" class="logo-enlace">
                    <img
                        src="{{ asset('imagen/logo/LOGO ONUBA GOURMET MARKET.webp') }}"
                        alt="ONUBA Gourmet Market, ir a la página de inicio"
                        class="logo-onuba">
                </a>

                <!-- MENÚ: en pantallas menores de 992 px se despliega con el botón de hamburguesa -->
                <nav id="menu-principal" class="menu-principal collapse" aria-label="Categorías de productos">
                    <a href="{{ url('/categoria/jamones-y-embutidos') }}">Jamones y embutidos</a>
                    <a href="{{ url('/categoria/aceites-de-oliva') }}">Aceites de oliva</a>
                    <a href="{{ url('/categoria/conservas-y-salazones') }}">Conservas y salazones</a>
                    <a href="{{ url('/categoria/quesos') }}">Quesos</a>
                    <a href="{{ url('/categoria/miel-y-dulces') }}">Miel y dulces</a>
                    <a href="{{ url('/categoria/vinos') }}">Vinos</a>
                </nav>

                <!-- ICONOS -->
                <div class="iconos-cabecera">
                    <a href="#" aria-label="Buscar">
                        <i class="bi bi-search" aria-hidden="true"></i>
                    </a>
                    <a href="#" aria-label="Mi cuenta">
                        <i class="bi bi-person" aria-hidden="true"></i>
                    </a>
                    <a href="#" aria-label="Carrito">
                        <i class="bi bi-bag" aria-hidden="true"></i>
                    </a>
                    <button
                        type="button"
                        class="btn-menu d-lg-none"
                        data-bs-toggle="collapse"
                        data-bs-target="#menu-principal"
                        aria-controls="menu-principal"
                        aria-expanded="false"
                        aria-label="Mostrar u ocultar el menú de categorías">
                        <i class="bi bi-list" aria-hidden="true"></i>
                    </button>
                </div>

            </div>
        </div>
    </header>

    <main id="contenido" tabindex="-1">
        {{-- Aquí se inserta el contenido de cada página --}}
        @yield('contenido')
    </main>

    {{-- Necesario para que funcione el botón del menú en móvil --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')

</body>

</html>
