<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Justificante del pedido {{ $identificadorPedido }}</title>
    <style>
        @page {
            margin: 42pt;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10pt;
            color: #242b22;
        }

        h1 {
            margin: 8pt 0 4pt;
            font-size: 22pt;
        }

        .marca {
            color: #365833;
            font-weight: bold;
        }

        .referencia {
            margin: 0 0 22pt;
            color: #525950;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 9pt 7pt;
            border-bottom: 1pt solid #dbe1d8;
            text-align: left;
        }

        th {
            background-color: #edf2e9;
        }

        .importe {
            text-align: right;
            white-space: nowrap;
        }

        .total {
            margin-top: 14pt;
            text-align: right;
            font-size: 13pt;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <div class="marca">Onuba Gourmet Market</div>
    <h1>Justificante de compra</h1>
    <p class="referencia">Pedido n.º {{ $identificadorPedido }} · {{ $fechaFormateada }}</p>

    <table>
        <thead>
            <tr>
                <th scope="col">Producto</th>
                <th scope="col" class="importe">Cantidad</th>
                <th scope="col" class="importe">Precio unitario</th>
                <th scope="col" class="importe">Subtotal</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($lineasPedido as $lineaPedido)
                {{-- Si falta el nombre en el catálogo, muestro el número del producto --}}
                @php
                    $nombreProducto = $lineaPedido->nombre;

                    if ($nombreProducto === null) {
                        $identificadorProducto = $lineaPedido->producto_id;
                        $nombreProducto = "Producto n.º {$identificadorProducto}";
                    }

                    // Formateo los importes con dos decimales y coma decimal
                    $precioUnitarioGuardado = $lineaPedido->precio_unitario;
                    $precioUnitarioFormateado = number_format($precioUnitarioGuardado, 2, ',', '.');

                    $subtotalGuardado = $lineaPedido->subtotal;
                    $subtotalFormateado = number_format($subtotalGuardado, 2, ',', '.');
                @endphp

                <tr>
                    <td>{{ $nombreProducto }}</td>
                    <td class="importe">{{ $lineaPedido->cantidad }}</td>
                    <td class="importe">{{ $precioUnitarioFormateado }} €</td>
                    <td class="importe">{{ $subtotalFormateado }} €</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $totalFormateado = number_format($totalPedido, 2, ',', '.');
    @endphp

    <p class="total">
        <strong>Total pagado {{ $totalFormateado }} €</strong>
    </p>
</body>
</html>
