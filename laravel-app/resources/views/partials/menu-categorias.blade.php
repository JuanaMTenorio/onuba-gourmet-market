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
