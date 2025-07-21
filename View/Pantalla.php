<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pantalla de Tíckets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../View/styles3.css"/>

   
</head>
<body>

    <div class="container-fluid px-0 text-center mt-5" >
<img src="../IMG/LOGO MC - NEGRO.png" class="mb-3" alt="LOGO" style="width: 500px; max-width: 100%;">
        <div id="tablaDatos">
            <?php include '../Logica/datos.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function actualizarDatos() {
    fetch('../Logica/datos.php')
        .then(response => response.text())
        .then(data => {
            document.getElementById('tablaDatos').innerHTML = data;
        })
        .catch(error => console.error('Error al actualizar los datos:', error));
}

setInterval(actualizarDatos, 1000);

    </script>

</body>
</html>
