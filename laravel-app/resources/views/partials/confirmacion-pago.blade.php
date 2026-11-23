@php
    $mensajeConfirmacion = "Pago confirmado. Pedido n.º {$identificadorPedidoPagado}.";

    // Uso el número de pedido para completar la parte {pedido} de la ruta
    $parametrosRutaJustificante = [
        'pedido' => $identificadorPedidoPagado,
    ];
    $direccionJustificante = route('pedidos.pdf', $parametrosRutaJustificante);
    $direccionEnviarJustificante = route('pedidos.enviar-justificante', $parametrosRutaJustificante);

    // Compruebo si el justificante del pedido que estoy mostrando se acaba de enviar
    $identificadorJustificanteEnviado = session('justificante_enviado_id');
    $justificanteEnviado = $identificadorJustificanteEnviado === $identificadorPedidoPagado;
    $facturaDescargada = session('factura_descargada_id') === $identificadorPedidoPagado;
@endphp

<div class="container mt-3">
    <div class="alert alert-success">
        <span role="status">{{ $mensajeConfirmacion }}</span>

        @if (! $facturaDescargada)
            <a id="descargar-factura" class="alert-link d-inline-block ms-sm-2" href="{{ $direccionJustificante }}">
                Descargar justificante en PDF
            </a>
        @endif
        <span id="estado-descarga-factura" class="ms-sm-2" role="status">{{ $facturaDescargada ? 'La factura pdf se ha descargado correctamente' : '' }}</span>

        @if ($justificanteEnviado)
            <span class="d-inline-block ms-sm-2" role="status">
                Se ha enviado el justificante por correo
            </span>
        @else
            <form class="d-inline-block ms-sm-2" method="POST" action="{{ $direccionEnviarJustificante }}">
                @csrf
                <input id="factura-descargada" type="hidden" name="factura_descargada" value="{{ $facturaDescargada ? '1' : '0' }}">

                <button type="submit" class="alert-link bg-transparent border-0 p-0 text-decoration-underline">
                    Enviar justificante por correo
                </button>
            </form>
        @endif
    </div>
</div>

@if (! $facturaDescargada)
    @push('scripts')
        <script>
            const enlaceFactura = document.getElementById('descargar-factura');
            const estadoDescargaFactura = document.getElementById('estado-descarga-factura');
            const campoFacturaDescargada = document.getElementById('factura-descargada');

            enlaceFactura.addEventListener('click', async function (evento) {
                evento.preventDefault();

                if (enlaceFactura.hasAttribute('aria-disabled')) {
                    return;
                }

                enlaceFactura.setAttribute('aria-disabled', 'true');
                estadoDescargaFactura.textContent = 'Descargando factura…';

                try {
                    const respuesta = await fetch(enlaceFactura.href);
                    const tipoContenido = respuesta.headers.get('Content-Type');

                    if (!respuesta.ok || !tipoContenido?.startsWith('application/pdf')) {
                        throw new Error('La respuesta no contiene un PDF.');
                    }

                    const archivo = await respuesta.blob();
                    const direccionArchivo = URL.createObjectURL(archivo);
                    const descarga = document.createElement('a');
                    const disposicion = respuesta.headers.get('Content-Disposition');
                    const nombreArchivo = disposicion?.match(/filename="?([^";]+)/i)?.[1];

                    descarga.href = direccionArchivo;
                    descarga.download = nombreArchivo ?? 'factura.pdf';
                    descarga.click();
                    setTimeout(() => URL.revokeObjectURL(direccionArchivo), 1000);

                    enlaceFactura.remove();
                    estadoDescargaFactura.textContent = 'La factura pdf se ha descargado correctamente';

                    if (campoFacturaDescargada !== null) {
                        campoFacturaDescargada.value = '1';
                    }
                } catch {
                    estadoDescargaFactura.textContent = 'No se pudo descargar la factura. Vuelve a intentarlo.';
                    enlaceFactura.removeAttribute('aria-disabled');
                }
            });
        </script>
    @endpush
@endif
