<?php
require_once __DIR__ . '/../../conexionBD/session_config.php';
verificarAutenticacion();

// Cargar configuración de Azure
$config = require __DIR__ . '/../../config/app.php';
$azureConfig = $config['azure'];
?>
<!DOCTYPE html> 
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Búsqueda Avanzada en Inventario</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            min-height: 100vh;
            padding: 1rem;
        }
        
        .search-container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 1.5rem;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .logo-container img {
            max-width: 200px;
            height: auto;
        }
        
        .search-info {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .input-group {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .input-group-text {
            background-color: white;
            border: 2px solid #000;
            border-right: 0;
        }
        
        #buscador {
            border: 2px solid #000;
            border-left: 0;
            border-right: 0;
            font-size: 1rem;
            padding: 0.75rem;
        }
        
        #buscador:focus {
            box-shadow: none;
            border-color: #000;
        }
        
        #btnLimpiar {
            border: 2px solid #000;
            border-left: 0;
            background-color: #000;
            color: #ffd700;
            font-weight: 600;
        }
        
        #btnLimpiar:hover {
            background-color: #ffd700;
            color: #000;
        }
        
        /* Tarjetas para móvil */
        .product-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #ffd700;
        }
        
        .product-card .product-image {
            width: 100%;
            height: 200px;
            object-fit: contain;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .product-card .product-image:hover {
            transform: scale(1.05);
        }
        
        .product-card .item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
        }
        
        .product-card .item-id {
            font-weight: 700;
            color: #000;
            font-size: 1.1rem;
        }
        
        .product-card .item-description {
            font-size: 0.95rem;
            color: #333;
            margin-bottom: 0.75rem;
            font-weight: 500;
        }
        
        .product-card .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.4rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .product-card .info-row:last-child {
            border-bottom: none;
        }
        
        .product-card .label {
            color: #6c757d;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .product-card .value {
            color: #333;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .inventory-badge {
            background: linear-gradient(135deg, #000 0%, #333 100%);
            color: #ffd700;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 700;
            display: inline-block;
        }
        
        /* Tabla para escritorio */
        .table-container {
            display: none;
        }
        
        .table {
            font-size: 0.9rem;
        }
        
        thead {
            position: sticky; 
            top: 0;
            z-index: 10;
        }
        
        .table thead th {
            background: linear-gradient(135deg, #000 0%, #333 100%);
            color: #ffd700;
            border: none;
            padding: 1rem 0.5rem;
            font-weight: 600;
        }
        
        .table tbody tr {
            transition: all 0.2s;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9ff;
            transform: scale(1.01);
        }
        
        .table .product-thumb {
            width: 60px;
            height: 60px;
            object-fit: contain;
            border-radius: 6px;
            background: #f8f9fa;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .table .product-thumb:hover {
            transform: scale(1.5);
        }
        
        /* Modal para imagen */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            justify-content: center;
            align-items: center;
        }
        
        .image-modal.active {
            display: flex;
        }
        
        .modal-content-img {
            max-width: 90%;
            max-height: 90vh;
            object-fit: contain;
        }
        
        .close-modal {
            position: absolute;
            top: 20px;
            right: 40px;
            color: #fff;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        
        /* Mensajes */
        .alert {
            border-radius: 12px;
            border: none;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        
        /* Media queries */
        @media (min-width: 768px) {
            body {
                padding: 2rem;
            }
            
            .search-container {
                padding: 2.5rem;
            }
            
            .logo-container img {
                max-width: 250px;
            }
            
            .cards-container {
                display: none !important;
            }
            
            .table-container {
                display: block !important;
            }
        }
        
        @media (max-width: 767px) {
            .search-container {
                padding: 1rem;
            }
            
            .logo-container img {
                max-width: 150px;
            }
            
            #buscador {
                font-size: 16px;
            }
            
            .product-card {
                padding: 0.875rem;
            }
        }
    </style>
</head>
<body>

    <!-- Modal para imagen ampliada -->
    <div class="image-modal" id="imageModal">
        <span class="close-modal" id="closeModal">&times;</span>
        <img class="modal-content-img" id="modalImage">
    </div>

    <div class="container-fluid">
        <div class="search-container">
            
            <!-- Logo de la empresa -->
            <div class="logo-container">
                <img src="../../IMG/Logo Listo - Negro.png"
                     class="img-fluid" 
                     alt="Logo de la empresa">
                <p class="search-info">
                    <i class="bi bi-info-circle"></i> Ingresa el <strong>MC</strong> o el <strong>código de barras</strong> para encontrar un producto.
                </p>
            </div>

            <!-- Input con botón limpiar -->
            <div class="input-group mb-4">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="buscador" class="form-control" placeholder="Escribe para buscar...">
                <button class="btn" id="btnLimpiar" type="button">
                    <i class="bi bi-x-circle"></i> <span class="d-none d-sm-inline">Limpiar</span>
                </button>
            </div>

            <!-- Indicador de carga -->
            <div id="cargando" class="text-center my-4" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-2 text-muted">Buscando productos...</p>
            </div>

            <!-- Vista de tarjetas para móvil -->
            <div class="cards-container" id="cardsContainer" style="display:none;"></div>

            <!-- Vista de tabla para escritorio -->
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="tablaResultados" style="display:none;">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Imagen</th>
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
            </div>

            <!-- Mensaje de estado -->
            <div id="mensaje" class="alert text-center" role="alert" style="display:none;"></div>

        </div>
    </div>

    <script>
    $(document).ready(function(){
        let timeout = null;
        // Configuración de Azure desde config/app.php
        const azureAccountName = '<?= $azureConfig['account_name'] ?>';
        const azureContainerName = '<?= $azureConfig['container_name'] ?>';

        // Función para generar URL de imagen
        function getImageUrl(itemid) {
            return `https://${azureAccountName}.blob.core.windows.net/${azureContainerName}/${encodeURIComponent(itemid.trim())}.jpg`;
        }

        // Función para abrir modal de imagen
        function openImageModal(imageSrc) {
            $('#modalImage').attr('src', imageSrc);
            $('#imageModal').addClass('active');
        }

        // Cerrar modal
        $('#closeModal, #imageModal').on('click', function(e) {
            if (e.target.id === 'closeModal' || e.target.id === 'imageModal') {
                $('#imageModal').removeClass('active');
            }
        });

        // Función de búsqueda
        function buscar(valor){
            if (valor.length < 2) {
                $("#tablaResultados").fadeOut();
                $("#cardsContainer").fadeOut();
                $("#mensaje").fadeOut();
                return;
            }

            $.ajax({
                url: "../../Logica/api_search.php",
                method: "GET",
                data: { type: 'inventario', q: valor },
                dataType: 'json',
                beforeSend: function(){
                    $("#cargando").show();
                    $("#tablaResultados").hide();
                    $("#cardsContainer").hide();
                    $("#mensaje").hide();
                },
                success: function(response){
                    let tbody = $("#tablaResultados tbody");
                    let cardsContainer = $("#cardsContainer");
                    tbody.empty();
                    cardsContainer.empty();

                    if (response.success && response.data && response.data.length > 0) {
                        response.data.forEach(function(item){
                            let promedio = item.promedio_Ventas_3M ? parseFloat(item.promedio_Ventas_3M).toFixed(1) : "0.0";
                            let mi = item.MI ? parseFloat(item.MI).toFixed(1) : "0.0";
                            let inventario = item.Inventario_Listo ? parseFloat(item.Inventario_Listo).toFixed(1) : "0.0";
                            let imageUrl = getImageUrl(item.itemid);
                            let placeholderImg = 'https://via.placeholder.com/280x250.png?text=Sin+Imagen';

                            // Fila de tabla para escritorio
                            const fila = `
                                <tr>
                                    <td>
                                        <img src="${imageUrl}" 
                                             class="product-thumb" 
                                             alt="${item.description}"
                                             onerror="this.onerror=null; this.src='${placeholderImg}';"
                                             onclick="openImageModal('${imageUrl}')">
                                    </td>
                                    <td><strong>${item.itemid}</strong></td>
                                    <td>${item.description}</td>
                                    <td>${item.itembarcode}</td>
                                    <td>${item.unitid}</td>
                                    <td class="text-center"><span class="badge bg-primary">${inventario}</span></td>
                                    <td class="text-end">${promedio}</td>
                                    <td class="text-end">${mi}</td>
                                </tr>
                            `;
                            tbody.append(fila);

                            // Tarjeta para móvil
                            const card = `
                                <div class="product-card">
                                    <img src="${imageUrl}" 
                                         class="product-image" 
                                         alt="${item.description}"
                                         onerror="this.onerror=null; this.src='${placeholderImg}';"
                                         onclick="openImageModal('${imageUrl}')">
                                    <div class="item-header">
                                        <span class="item-id">${item.itemid}</span>
                                        <span class="inventory-badge">
                                            <i class="bi bi-box-seam"></i> ${inventario}
                                        </span>
                                    </div>
                                    <div class="item-description">${item.description}</div>
                                    <div class="info-row">
                                        <span class="label"><i class="bi bi-upc"></i> Código de Barras</span>
                                        <span class="value">${item.itembarcode}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="label"><i class="bi bi-box"></i> Unidad</span>
                                        <span class="value">${item.unitid}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="label"><i class="bi bi-graph-up"></i> Prom. Ventas 3M</span>
                                        <span class="value">${promedio}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="label"><i class="bi bi-calculator"></i> MI</span>
                                        <span class="value">${mi}</span>
                                    </div>
                                </div>
                            `;
                            cardsContainer.append(card);
                        });
                        $("#tablaResultados").fadeIn();
                        $("#cardsContainer").fadeIn();
                    } else if (response.success && response.data.length === 0) {
                        $("#mensaje").html('<i class="bi bi-emoji-frown fs-3"></i><br>No se encontraron resultados para <strong>"' + valor + '"</strong>.').removeClass('alert-danger').addClass('alert-info').fadeIn();
                    } else {
                        const mensajeError = response.message || 'Ocurrió un error desconocido en el servidor.';
                        $("#mensaje").html('<i class="bi bi-exclamation-triangle fs-3"></i><br><strong>Error:</strong> ' + mensajeError).removeClass('alert-info').addClass('alert-danger').fadeIn();
                    }
                },
                error: function(){
                    $("#mensaje").html('<i class="bi bi-wifi-off fs-3"></i><br><strong>Error de Conexión:</strong> No se pudo comunicar con el servidor.').removeClass('alert-info').addClass('alert-danger').fadeIn();
                },
                complete: function(){
                    $("#cargando").hide();
                }
            });
        }

        // Hacer disponible globalmente para los onclick
        window.openImageModal = openImageModal;

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
            $("#cardsContainer").empty();
            $("#tablaResultados").fadeOut();
            $("#cardsContainer").fadeOut();
            $("#mensaje").fadeOut();
            $("#buscador").focus();
        });
    });
    </script>

</body>
</html>
