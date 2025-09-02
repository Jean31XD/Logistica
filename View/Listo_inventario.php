<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Búsqueda en Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="container py-4">

    <h2 class="mb-4">Buscar en Inventario</h2>

    <div class="mb-3">
        <input type="text" id="buscador" class="form-control" placeholder="Escribe ItemID o Barcode...">
    </div>

    <table class="table table-bordered table-striped shadow" id="tablaResultados" style="display:none;">
        <thead class="table-dark">
            <tr>
                <th>ItemID</th>
                <th>Descripción</th>
                <th>Barcode</th>
                <th>Unidad</th>
                <th>Inventario</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <div id="mensaje" class="alert alert-info" style="display:none;"></div>

    <script>
    $(document).ready(function(){
        $("#buscador").on("keyup", function(){
            let valor = $(this).val().trim();
            
            if(valor.length < 2){
                $("#tablaResultados").hide();
                $("#mensaje").hide();
                return;
            }

            $.ajax({
                url: "buscar.php",
                method: "GET",
                data: { q: valor },
                success: function(data){
                    let tbody = $("#tablaResultados tbody");
                    tbody.empty();

                    if(data.length > 0){
                        data.forEach(function(item){
                            tbody.append(`
                                <tr>
                                    <td>${item.itemid}</td>
                                    <td>${item.description}</td>
                                    <td>${item.itembarcode}</td>
                                    <td>${item.unitid}</td>
                                    <td>${item.Inventario_Listo}</td>
                                </tr>
                            `);
                        });
                        $("#tablaResultados").show();
                        $("#mensaje").hide();
                    } else {
                        $("#tablaResultados").hide();
                        $("#mensaje").text("No se encontraron resultados.").show();
                    }
                },
                error: function(){
                    $("#tablaResultados").hide();
                    $("#mensaje").text("⚠️ Error al buscar en el servidor.").show();
                }
            });
        });
    });
    </script>

</body>
</html>
