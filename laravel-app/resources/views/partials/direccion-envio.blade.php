{{-- El cliente puede usar la dirección de su cuenta o escribir otra para este pedido --}}
<section id="direccion-envio" class="carrito-resumen carrito-direccion" aria-labelledby="titulo-direccion-envio">
    <h2 id="titulo-direccion-envio">Dirección de envío</h2>

    @if (! $pagoAbierto)
        @php
            // Preparo los enlaces y hago que ambos lleven al formulario de envío
            $direccionHabitual = route('carrito.index', ['envio' => 'habitual']);
            $enlaceDireccionHabitual = $direccionHabitual . '#direccion-envio';

            $direccionNueva = route('carrito.index', ['envio' => 'nueva']);
            $enlaceDireccionNueva = $direccionNueva . '#direccion-envio';

            // Resalto la opción de envío que tiene seleccionada el cliente
            $usaDireccionHabitual = $tipoDireccion === 'habitual';
            $usaDireccionNueva = $tipoDireccion === 'nueva';
            $claseDireccionHabitual = 'btn btn-outline-dark';
            $claseDireccionNueva = 'btn btn-outline-dark';

            if ($usaDireccionHabitual) {
                $claseDireccionHabitual = 'btn btn-dark';
            }

            if ($usaDireccionNueva) {
                $claseDireccionNueva = 'btn btn-dark';
            }
        @endphp

        <nav id="opciones-envio" class="d-flex flex-wrap gap-2 mb-4" aria-label="Elegir dirección de envío">
            <a href="{{ $enlaceDireccionHabitual }}"
                class="{{ $claseDireccionHabitual }}"
                @if ($usaDireccionHabitual)
                    aria-current="true"
                @endif>
                Usar mi dirección habitual
            </a>

            <a href="{{ $enlaceDireccionNueva }}"
                class="{{ $claseDireccionNueva }}"
                @if ($usaDireccionNueva)
                    aria-current="true"
                @endif>
                Introducir otra dirección
            </a>
        </nav>
    @endif

    <form id="formulario-pago" method="POST" action="{{ route('carrito.pago.iniciar') }}">
    @csrf
    {{-- Envío el total visible para compararlo con el importe que calcula Laravel --}}
    <input type="hidden" name="total_mostrado" value="{{ $totalCentimos }}">
    <fieldset id="campos-envio" class="row g-3" @disabled($pagoAbierto)>
        <legend class="visually-hidden">Datos del destinatario</legend>
        <div class="col-12 col-sm-6">
            <label for="envio-nombre" class="form-label">Nombre</label>
            <input id="envio-nombre" name="nombre" type="text" class="form-control"
                autocomplete="shipping given-name" maxlength="100"
                value="{{ $datosEnvio['nombre'] }}" required>
        </div>

        <div class="col-12 col-sm-6">
            <label for="envio-apellidos" class="form-label">Apellidos</label>
            <input id="envio-apellidos" name="apellidos" type="text" class="form-control"
                autocomplete="shipping family-name" maxlength="150"
                value="{{ $datosEnvio['apellidos'] }}" required>
        </div>

        <div class="col-12 col-sm-6">
            <label for="envio-email" class="form-label">Correo electrónico</label>
            <input id="envio-email" name="email" type="email" class="form-control"
                autocomplete="shipping email" maxlength="255"
                value="{{ $datosEnvio['email'] }}" required>
        </div>

        <div class="col-12 col-sm-6">
            <label for="envio-telefono" class="form-label">Teléfono</label>
            <input id="envio-telefono" name="telefono" type="tel" class="form-control"
                autocomplete="shipping tel" maxlength="20"
                value="{{ $datosEnvio['telefono'] }}" required>
        </div>

        <div class="col-12 col-sm-6">
            <label for="envio-direccion" class="form-label">Dirección</label>
            <input id="envio-direccion" name="direccion" type="text" class="form-control"
                autocomplete="shipping address-line1" maxlength="255"
                value="{{ $datosEnvio['direccion'] }}" required>
        </div>

        <div class="col-12 col-sm-6">
            <label for="envio-codigo_postal" class="form-label">Código postal</label>
            <input id="envio-codigo_postal" name="codigo_postal" type="text" class="form-control"
                autocomplete="shipping postal-code" maxlength="10"
                value="{{ $datosEnvio['codigo_postal'] }}" required>
        </div>

        <div class="col-12 col-sm-6">
            <label for="envio-localidad" class="form-label">Localidad</label>
            <input id="envio-localidad" name="localidad" type="text" class="form-control"
                autocomplete="shipping address-level2" maxlength="100"
                value="{{ $datosEnvio['localidad'] }}" required>
        </div>

        <div class="col-12 col-sm-6">
            <label for="envio-provincia" class="form-label">Provincia</label>
            <input id="envio-provincia" name="provincia" type="text" class="form-control"
                autocomplete="shipping address-level1" maxlength="100"
                value="{{ $datosEnvio['provincia'] }}" required>
        </div>
    </fieldset>
    </form>
</section>
