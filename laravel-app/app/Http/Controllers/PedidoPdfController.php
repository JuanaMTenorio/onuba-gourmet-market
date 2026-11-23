<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Exception\RfcComplianceException;

class PedidoPdfController extends Controller{

    // Laravel me pasa la petición del navegador y el número de pedido de la URL
    // Response indica que devuelvo una respuesta HTTP con el PDF para descargar
    public function descargarJustificante(Request $peticion, int $pedido): Response {

        $datosPreparados = $this->prepararJustificante($peticion, $pedido);
        $pdf = $datosPreparados['pdf'];
        $nombreArchivo = $datosPreparados['nombreArchivo'];

        $respuesta = $pdf->download($nombreArchivo);

        // Indico al navegador que no debe guardar la respuesta en caché
        $respuesta->header('Cache-Control', 'private, no-store');

        return $respuesta;
    }

    // Envío el justificante al correo que quedó guardado al hacer el pedido
    public function enviarEmailJustificante(Request $peticion, int $pedido): Response {

        $datosPreparados = $this->prepararJustificante($peticion, $pedido);
        $pedidoPagado = $datosPreparados['pedido'];
        $identificadorPedido = $pedidoPagado->id;
        $destinatario = $pedidoPagado->email_envio;

        // Preparo la vuelta al carrito y conservo el enlace para descargar o reintentar
        $redireccion = redirect();
        $respuesta = $redireccion->route('carrito.index');
        $respuesta->with('pedido_pagado_id', $identificadorPedido);

        if ($peticion->boolean('factura_descargada')) {
            $respuesta->with('factura_descargada_id', $identificadorPedido);
        }

        if (! is_string($destinatario) || trim($destinatario) === '') {
            $respuesta->with('aviso', 'El pedido no tiene un correo electrónico guardado.');

            return $respuesta;
        }

        // Obtengo el contenido del PDF y le doy un nombre para adjuntarlo
        $pdf = $datosPreparados['pdf'];
        $contenidoPdf = $pdf->output();
        $nombreArchivo = $datosPreparados['nombreArchivo'];
        $opcionesAdjunto = [
            'mime' => 'application/pdf',
        ];

        $asunto = "Justificante del pedido n.º {$identificadorPedido}";
        $textoCorreo = 'Gracias por tu compra en Onuba Gourmet Market. Te adjuntamos el justificante de tu pedido.';

        // Con use paso a la función los datos que acabo de preparar
        $prepararCorreo = function (Message $correo) use (
            $destinatario,
            $asunto,
            $contenidoPdf,
            $nombreArchivo,
            $opcionesAdjunto
        ): void {
            $correo->to($destinatario);
            $correo->subject($asunto);
            $correo->attachData($contenidoPdf, $nombreArchivo, $opcionesAdjunto);
        };

        try {
            Mail::raw($textoCorreo, $prepararCorreo);
        } catch (TransportExceptionInterface | RfcComplianceException $error) {
            report($error);
            $respuesta->with('aviso', 'No se pudo enviar el justificante por correo. Puedes volver a intentarlo.');

            return $respuesta;
        }

        // En modo log Laravel guarda una copia de prueba en vez de enviar el correo
        $servicioCorreo = config('mail.default');

        if ($servicioCorreo === 'log') {
            $mensaje = 'El justificante se ha registrado como correo de prueba. No se ha enviado a la bandeja de entrada.';
            $respuesta->with('mensaje', $mensaje);

            return $respuesta;
        }

        // Guardo el número del pedido para sustituir el botón por la confirmación
        $respuesta->with('justificante_enviado_id', $identificadorPedido);

        return $respuesta;
    }

    // Busco un pedido pagado del cliente y preparo el PDF para la descarga o el correo
    private function prepararJustificante(Request $peticion, int $pedido): array {

        // Obtengo identificador del usuario que ha iniciado sesión
        $usuario = $peticion->user();
        $identificadorUsuario = $usuario->id;

        // Busco pedido pagado que pertenezca a ese usuario
        $sqlPedido = '
            SELECT id, fecha, total, email_envio
            FROM pedidos
            WHERE id = ?
              AND usuario_id = ?
              AND estado = ?
        ';

        // Cada signo ? recibe el valor que le corresponde en este orden
        $parametrosPedido = [
            $pedido,
            $identificadorUsuario,
            'pagado',
        ];

        // Ejecuto la consulta y recupero un solo pedido
        $pedidoPagado = DB::selectOne($sqlPedido, $parametrosPedido);

        // Si la consulta no encuentra el pedido, devuelvo un error 404
        if ($pedidoPagado === null) {
            abort(404);
        }

        // Obtengo el número del pedido que acabo de encontrar
        $identificadorPedido = $pedidoPagado->id;

        // Busco las líneas del pedido con los importes guardados en la compra
        // Uso LEFT JOIN para conservar la línea aunque el producto ya no esté en el catálogo
        $sqlLineasPedido = '
            SELECT detalle.producto_id,
                   producto.nombre,
                   detalle.cantidad,
                   detalle.precio_unitario,
                   detalle.subtotal
            FROM detalles_pedido AS detalle
            LEFT JOIN productos AS producto ON producto.id = detalle.producto_id
            WHERE detalle.pedido_id = ?
            ORDER BY detalle.producto_id
        ';

        $parametrosLineasPedido = [
            $identificadorPedido,
        ];

        // Ejecuto la consulta y obtengo todas las líneas del pedido
        $lineasPedido = DB::select($sqlLineasPedido, $parametrosLineasPedido);

        // Doy formato a la fecha del pedido para mostrarla en el justificante
        $fechaPedido = Carbon::parse($pedidoPagado->fecha);
        $fechaFormateada = $fechaPedido->format('d/m/Y');

        // Reúno los datos que voy a pasar a la plantilla
        $totalPedido = $pedidoPagado->total;
        $datosJustificante = [
            'identificadorPedido' => $identificadorPedido,
            'fechaFormateada' => $fechaFormateada,
            'lineasPedido' => $lineasPedido,
            'totalPedido' => $totalPedido,
        ];

        // Cargo la plantilla del justificante con los datos del pedido
        $pdf = Pdf::loadView('pedidos.justificante', $datosJustificante);

        $nombreArchivo = "pedido-{$identificadorPedido}.pdf";
        $datosPreparados = [
            'pedido' => $pedidoPagado,
            'pdf' => $pdf,
            'nombreArchivo' => $nombreArchivo,
        ];

        return $datosPreparados;
    }
}
