<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Búsqueda Avanzada en Inventario</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        /* Estilo para dar un aspecto más suave y profesional */
        body {
            background-color: #f8f9fa;
        }
        .search-container {
            max-width: 800px;
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
            position: sticky; /* Encabezado fijo al hacer scroll */
            top: 0;
        }
    </style>
</head>
<body class="py-5">

    <div class="container">
        <div class="search-container">
            <div class="text-center mb-4">
                <h1 class="h2">📦 Búsqueda en Inventario</h1>
                <p class="text-muted">Ingresa el ItemID o Barcode para encontrar un producto.</p>
            </div>

            <div class="input-group mb-4 shadow-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="buscador" class="form-control form-control-lg" placeholder="Escribe para buscar...">
            </div>

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
                        </tr>
                    </thead>
                    <tbody>
                        </tbody>
                </table>
            </div>

            <div id="mensaje" class="alert alert-light text-center border-0" role="alert" style="display:none;"></div>

        </div>
    </div>

    <script>
    $(document).ready(function(){
        let timeout = null; // Variable para controlar el temporizador de búsqueda

        $("#buscador").on("keyup", function(){
            let valor = $(this).val().trim();
            
            // Limpia el temporizador anterior cada vez que se presiona una tecla
            clearTimeout(timeout);

            // Oculta todo si el campo está casi vacío
            if (valor.length < 2) {
                $("#tablaResultados").fadeOut();
                $("#mensaje").fadeOut();
                return;
            }

            // Inicia un temporizador: la búsqueda se ejecutará después de 300ms de inactividad
            timeout = setTimeout(function(){
                $.ajax({
                    url: "../Logica/buscar.php",
                    method: "GET",
                    data: { q: valor },
                    dataType: 'json', // Especificamos que esperamos un JSON
                    beforeSend: function(){
                        // Muestra el spinner y oculta resultados anteriores
                        $("#cargando").show();
                        $("#tablaResultados").hide();
                        $("#mensaje").hide();
                    },
                    success: function(data){
                        let tbody = $("#tablaResultados tbody");
                        tbody.empty(); // Limpia resultados anteriores

                        if (data && data.length > 0) {
                            data.forEach(function(item){
                                // Usamos plantillas literales para crear las filas
                                const fila = `
                                    <tr>
                                        <td>${item.itemid}</td>
                                        <td>${item.description}</td>
                                        <td>${item.itembarcode}</td>
                                        <td>${item.unitid}</td>
                                        <td class="text-center fw-bold">${item.Inventario_Listo}</td>
                                    </tr>
                                `;
                                tbody.append(fila);
                            });
                            $("#tablaResultados").fadeIn();
                        } else {
                            // No se encontraron resultados
                            $("#mensaje").html('<i class="bi bi-emoji-frown"></i> No se encontraron resultados para <strong>"' + valor + '"</strong>.').removeClass('alert-danger').addClass('alert-info').fadeIn();
                        }
                    },
                    error: function(){
                        // Error en la petición AJAX
                        $("#mensaje").html('⚠️ <strong>Error:</strong> No se pudo conectar con el servidor.').removeClass('alert-info').addClass('alert-danger').fadeIn();
                    },
                    complete: function(){
                        // Oculta el spinner al finalizar la petición (ya sea con éxito o error)
                        $("#cargando").hide();
                    }
                });
            }, 300); // Espera 300 milisegundos antes de buscar
        });
    });
    </script>

</body>
</html>