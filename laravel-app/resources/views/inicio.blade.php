<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ONUBA GOURMET MARKET</title>
</head>

<body>
    <h1>ONUBA GOURMET MARKET</h1>
    <p>Productos gourmet de la provincia de Huelva</p>
    <h2>Categorías</h2>

    @foreach ($categorias as $categoria)
    <p>{{ $categoria->nombre }}</p>
    @endforeach

</body>

</html>