        <nav aria-label="Seleccionar categoría">
            <ul class="lista-categorias">

                @foreach ($categorias as $categoriaMenu)

                @php
                    // Marco la categoría del menú que coincide con la página que estoy mostrando
                    $direccionCategoria = url('/categoria/' . $categoriaMenu->slug);
                    $esCategoriaActual = $categoriaMenu->id == $categoria->id;
                    $claseEnlaceCategoria = 'enlace-categoria';

                    if ($esCategoriaActual) {
                        $claseEnlaceCategoria = 'enlace-categoria categoria-activa';
                    }
                @endphp

                <li>
                    <a
                        href="{{ $direccionCategoria }}"
                        class="{{ $claseEnlaceCategoria }}"
                        @if ($esCategoriaActual)
                        aria-current="page"
                        @endif
                        >
                        <span>{{ $categoriaMenu->nombre }}</span>

                        @if ($esCategoriaActual)
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        @endif
                    </a>
                </li>

                @endforeach

            </ul>
        </nav>
