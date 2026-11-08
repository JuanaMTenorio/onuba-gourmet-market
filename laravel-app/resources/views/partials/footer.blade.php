{{-- El fondo es decorativo y todo el contenido se mantiene como texto HTML --}}
<footer class="pie-pagina" aria-label="Pie de página">
    <div class="pie-contenido">
        <div class="pie-marca">
            <a href="{{ url('/') }}" class="pie-logo" aria-label="ONUBA Gourmet Market, ir al inicio">
                <img src="{{ asset('imagen/logo/LOGO ONUBA GOURMET MARKET.webp') }}" alt="ONUBA Gourmet Market" width="260" height="91" loading="lazy">
            </a>
            <p>Productos gourmet de Huelva, seleccionados por su calidad, tradición y origen<br>Directo del productor a tu mesa</p>
            <p class="pie-origen"><i class="bi bi-geo-alt" aria-hidden="true"></i> Huelva, Andalucía</p>
        </div>

        <div class="pie-columna">
            <h2>Información</h2>
            <ul>
                <li><a href="{{ url('/#titulo-sabores') }}">Sabores de nuestra tierra</a></li>
                <li><a href="{{ url('/#titulo-productores') }}">Nuestros productores</a></li>
                <li><span>Envíos a toda España</span></li>
            </ul>
        </div>

        <div class="pie-columna">
            <h2>Mi cuenta</h2>
            <ul>
                <li><a href="{{ route('carrito.index') }}">Mi carrito</a></li>
                {{-- Estas opciones se convertirán en enlaces cuando existan sus páginas --}}
                <li><span>Iniciar sesión</span></li>
                <li><span>Mis pedidos</span></li>
            </ul>
        </div>

        <div class="pie-columna pie-columna-redes">
            <h2>Síguenos</h2>
            {{-- Los nombres se convertirán en enlaces cuando tengamos los perfiles oficiales --}}
            <ul class="pie-redes">
                <li><span><i class="bi bi-instagram" aria-hidden="true"></i> Instagram</span></li>
                <li><span><i class="bi bi-facebook" aria-hidden="true"></i> Facebook</span></li>
                <li><span><i class="bi bi-tiktok" aria-hidden="true"></i> TikTok</span></li>
            </ul>
        </div>
    </div>

    <div class="pie-base">
        <div class="pie-base-contenido">
            <p>© {{ date('Y') }} ONUBA GOURMET MARKET · Todos los derechos reservados</p>
            {{-- Los textos legales tendrán enlaces cuando se redacten sus páginas --}}
            <ul class="pie-legales" aria-label="Información legal">
                <li><span>Aviso legal</span></li>
                <li><span>Política de privacidad</span></li>
                <li><span>Política de cookies</span></li>
            </ul>
        </div>
    </div>
</footer>
