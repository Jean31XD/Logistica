<!DOCTYPE html> 
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Búsqueda Avanzada en Inventario</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Librería para escaneo con cámara -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        body {
            background-color: #f8f9fa;
        }
        .search-container {
            max-width: 900px;
            margin: auto;
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .input-group-text {
            background-color: transparent;
            border-right: 0;
        }
        #buscador {
            border-left: 0;
        }
        #buscador:focus {
            box-shadow: none;
            border-color: #ced4da;
        }
        thead {
            position: sticky; 
            top: 0;
        }
        /* Contenedor del escáner */
        #reader {
            width: 100%;
            max-width: 400px;
            margin: 20px auto;
        }
    </style>
</head>
<body class="py-5">

    <div class="container">
        <div class="search-container">
            
            <!-- Logo de la empresa -->
            <div class="text-center mb-4">
                <img src="../IMG/Logo Listo - Negro.png"
                     class="img-fluid mb-3" 
                     alt="Logo de la empresa" 
                     style="max-width: 280px; height: auto;">
                <p class="text-muted">
                    Ingresa el <strong>MC</strong> o el <strong>código de barras</strong> para encontrar un producto.
                </p>
            </div>

            <!-- Input con botón limpiar -->
            <div class="input-group mb-4 shadow-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="buscador" class="form-control form-control-lg" placeholder="Escribe o escanea un código...">
                <button class="btn btn-outline-secondary" id="btnLimpiar" type="button">
                    <i class="bi bi-x-circle"></i> Limpiar
                </button>
            </div>

            <!-- Escáner con cámara -->
            <div id="reader"></div>

            <div id="cargando" class="text-center my-4" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle" id="tablaResultados" style="display:none;">
                    <thead class="table-dark">
                        <tr>
                            <th>ItemID</th>
                            <th>Descripción</th>
                            <th>Barcode</th>
                            <th>Unidad</th>
                            <th class="text-center">Inventario</th>
                            <th>Prom. Ventas 3M</th>
                            <th>MI</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div id="mensaje" class="alert alert-light text-center border-0" role="alert" style="display:none;"></div>

        </div>
    </div>

    <script>
    $(document).ready(function(){
        let timeout = null;

        // --- Función de búsqueda ---
        function buscar(valor){
            if (valor.length < 2) {
                $("#tablaResultados").fadeOut();
                $("#mensaje").fadeOut();
                return;
            }

            $.ajax({
                url: "../Logica/buscar.php",
                method: "GET",
                data: { q: valor },
                dataType: 'json',
                beforeSend: function(){
                    $("#cargando").show();
                    $("#tablaResultados").hide();
                    $("#mensaje").hide();
                },
                success: function(response){
                    let tbody = $("#tablaResultados tbody");
                    tbody.empty();

                    if (response.success && response.data && response.data.length > 0) {
                        response.data.forEach(function(item){
                            let promedio = item.promedio_Ventas_3M ? parseFloat(item.promedio_Ventas_3M).toFixed(1) : "0.0";
                            let mi = item.MI ? parseFloat(item.MI).toFixed(1) : "0.0";
                            let inventario = item.Inventario_Listo ? parseFloat(item.Inventario_Listo).toFixed(1) : "0.0";

                            const fila = `
                                <tr>
                                    <td>${item.itemid}</td>
                                    <td>${item.description}</td>
                                    <td>${item.itembarcode}</td>
                                    <td>${item.unitid}</td>
                                    <td class="text-center fw-bold">${inventario}</td>
                                    <td class="text-end">${promedio}</td>
                                    <td class="text-end">${mi}</td>
                                </tr>
                            `;
                            tbody.append(fila);
                        });
                        $("#tablaResultados").fadeIn();
                    } else if (response.success && response.data.length === 0) {
                        $("#mensaje").html('<i class="bi bi-emoji-frown"></i> No se encontraron resultados para <strong>"' + valor + '"</strong>.').removeClass('alert-danger').addClass('alert-info').fadeIn();
                    } else {
                        const mensajeError = response.message || 'Ocurrió un error desconocido en el servidor.';
                        $("#mensaje").html('⚠️ <strong>Error:</strong> ' + mensajeError).removeClass('alert-info').addClass('alert-danger').fadeIn();
                    }
                },
                error: function(){
                    $("#mensaje").html('⚠️ <strong>Error de Conexión:</strong> No se pudo comunicar con el servidor.').removeClass('alert-info').addClass('alert-danger').fadeIn();
                },
                complete: function(){
                    $("#cargando").hide();
                }
            });
        }

        // Búsqueda al escribir
        $("#buscador").on("keyup", function(){
            let valor = $(this).val().trim();
            clearTimeout(timeout);
            timeout = setTimeout(function(){ buscar(valor); }, 300);
        });

        // Botón Limpiar
        $("#btnLimpiar").on("click", function(){
            $("#buscador").val("");
            $("#tablaResultados tbody").empty();
            $("#tablaResultados").fadeOut();
            $("#mensaje").fadeOut();
            $("#buscador").focus();
        });

        // --- Inicializar escáner ---
        function onScanSuccess(decodedText) {
            $("#buscador").val(decodedText);
            buscar(decodedText);
        }

        let html5QrcodeScanner = new Html5QrcodeScanner(
            "reader",
            { fps: 10, qrbox: 250 }
        );
        html5QrcodeScanner.render(onScanSuccess);
    });
    </script>

</body>
</html>
