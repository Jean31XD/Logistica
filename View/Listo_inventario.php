<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Búsqueda Avanzada en Inventario</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/@zxing/library@latest/umd/zxing.min.js"></script>

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
        #videoStream {
            width: 100%;
            border-radius: 8px;
        }
    </style>
</head>
<body class="py-5">

    <div class="container">
        <div class="search-container">
            
            <div class="text-center mb-4">
                <img src="../IMG/Logo Listo - Negro.png"
                     class="img-fluid mb-3" 
                     alt="Logo de la empresa" 
                     style="max-width: 280px; height: auto;">
                <p class="text-muted">
                    Ingresa el <strong>MC</strong>, el <strong>código de barras</strong> o usa el escáner.
                </p>
            </div>

            <div class="input-group mb-3 shadow-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="buscador" class="form-control form-control-lg" placeholder="Escribe para buscar...">
            </div>

            <div class="d-grid gap-2 d-sm-flex justify-content-sm-end">
                <button class="btn btn-primary btn-lg" id="btnEscanear" type="button">
                    <i class="bi bi-upc-scan"></i> Escanear Código
                </button>
                <button class="btn btn-outline-secondary btn-lg" id="btnLimpiar" type="button">
                    <i class="bi bi-x-circle"></i> Limpiar
                </button>
            </div>
            <div id="cargando" class="text-center my-4" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </div>

            <div class="table-responsive mt-4">
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

    <div class="modal fade" id="escanerModal" tabindex="-1" aria-labelledby="escanerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="escanerModalLabel">Escanear Código de Barras</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="text-muted small">Apunta la cámara al código de barras.</p>
                    <video id="videoStream" width="100%"></video>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    $(document).ready(function(){
        let timeout = null;

        // Función de búsqueda (sin cambios)
        function buscar(valor){
            if (valor.length < 2) {
                $("#tablaResultados").fadeOut();
                $("#mensaje").fadeOut();
                return;
            }
            $.ajax({
                url: "../Logica/buscar.php",
                method: "GET", data: { q: valor }, dataType: 'json',
                beforeSend: function(){ $("#cargando").show(); $("#tablaResultados").hide(); $("#mensaje").hide(); },
                success: function(response){
                    let tbody = $("#tablaResultados tbody");
                    tbody.empty();
                    if (response.success && response.data && response.data.length > 0) {
                        response.data.forEach(function(item){
                            let promedio = item.promedio_Ventas_3M ? parseFloat(item.promedio_Ventas_3M).toFixed(1) : "0.0";
                            let mi = item.MI ? parseFloat(item.MI).toFixed(1) : "0.0";
                            let inventario = item.Inventario_Listo ? parseFloat(item.Inventario_Listo).toFixed(1) : "0.0";
                            const fila = `<tr><td>${item.itemid}</td><td>${item.description}</td><td>${item.itembarcode}</td><td>${item.unitid}</td><td class="text-center fw-bold">${inventario}</td><td class="text-end">${promedio}</td><td class="text-end">${mi}</td></tr>`;
                            tbody.append(fila);
                        });
                        $("#tablaResultados").fadeIn();
                        $("#mensaje").fadeOut();
                    } else if (response.success && response.data.length === 0) {
                        $("#tablaResultados").fadeOut();
                        $("#mensaje").html('<i class="bi bi-emoji-frown"></i> No se encontraron resultados para <strong>"' + valor + '"</strong>.').removeClass('alert-danger').addClass('alert-info').fadeIn();
                    } else {
                        $("#tablaResultados").fadeOut();
                        const mensajeError = response.message || 'Ocurrió un error desconocido.';
                        $("#mensaje").html('⚠️ <strong>Error:</strong> ' + mensajeError).removeClass('alert-info').addClass('alert-danger').fadeIn();
                    }
                },
                error: function(){
                    $("#tablaResultados").fadeOut();
                    $("#mensaje").html('⚠️ <strong>Error de Conexión:</strong> No se pudo comunicar con el servidor.').removeClass('alert-info').addClass('alert-danger').fadeIn();
                },
                complete: function(){ $("#cargando").hide(); }
            });
        }

        $("#buscador").on("keyup", function(){
            clearTimeout(timeout);
            timeout = setTimeout(() => buscar($(this).val().trim()), 300);
        });

        $("#btnLimpiar").on("click", function(){
            $("#buscador").val("").focus();
            $("#tablaResultados").fadeOut().find("tbody").empty();
            $("#mensaje").fadeOut();
        });

        // --- LÓGICA DEL ESCÁNER MEJORADA ---
        const codeReader = new ZXing.BrowserMultiFormatReader();
        const escanerModal = new bootstrap.Modal(document.getElementById('escanerModal'));

        $('#btnEscanear').on('click', function () {
            escanerModal.show();
            // Intenta iniciar la cámara
            codeReader.decodeFromVideoDevice(undefined, 'videoStream', (result, err) => {
                if (result) {
                    $('#buscador').val(result.text);
                    escanerModal.hide();
                    buscar(result.text);
                }
                if (err && !(err instanceof ZXing.NotFoundException)) {
                    console.error("Error durante el escaneo:", err);
                    escanerModal.hide();
                }
            }).catch(err => {
                 // CAMBIO 2: ALERTAS MÁS CLARAS PARA EL USUARIO
                 console.error("Error al iniciar la cámara:", err);
                 escanerModal.hide();
                 if (err.name === 'NotAllowedError') {
                    alert('Acceso a la cámara denegado. Por favor, permite el acceso en la configuración de tu navegador.');
                 } else if (err.name === 'NotFoundError') {
                    alert('No se encontró ninguna cámara en este dispositivo.');
                 } else {
                    alert('No se pudo iniciar la cámara. Asegúrate de que estás en un sitio seguro (HTTPS) y has concedido los permisos.');
                 }
            });
        });

        $('#escanerModal').on('hidden.bs.modal', function () {
            codeReader.reset(); // Detiene la cámara cuando se cierra el modal
        });
    });
    </script>

</body>
</html>