<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);

session_start();
session_regenerate_id(true);

if (!isset($_SESSION['pantalla']) || !in_array($_SESSION['pantalla'], [8,0])) {
    header("Location: ../index.php");
    exit();
}

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de Etiquetas con Búsqueda</title>
    
    <!-- Tom Select -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    
    <!-- JsBarcode -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            background-color: #f4f4f9; 
            color: #333; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            padding: 20px; 
            margin: 0;
        }
        .container {
            background: #fff; 
            padding: 25px; 
            border-radius: 8px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
            width: 100%; 
            max-width: 500px;
        }
        h1 {color: #1a237e; text-align: center; margin-bottom: 20px;}
        .ts-control { padding: 10px !important; font-size: 16px; border-radius: 4px; border: 1px solid #ccc;}

        #quantity {
              border-radius: 6px; 
    padding: 8px 10px !important; 
    font-size: 14px; 
    width: 80%; 
    margin: 5px auto 10px auto; 
    display: block;
    text-align: left;
        }

        /* Contenedor de la etiqueta */
        #label-container {
            width: 2in; 
            height: 4in; 
            border: 1px dashed #999; 
            margin: 10px auto; 
            padding: 10px; 
            box-sizing: border-box; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            text-align: center; 
            background: #fff; 
            visibility: hidden;
        }

        /* Logo encima del nombre */
        #label-logo {
            max-height: 40px; 
            margin-bottom: 2px;
            object-fit: contain;
        }

        #product-name {
            font-weight: bold; 
            font-size: 10px; 
            margin: 5px 0; 
            line-height: 1.2; 
            flex-grow: 1; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            text-align: center;
        }

        #unit-quantity {
            font-size: 14px; 
            font-weight: bold; 
            margin: 5px 0;
        }

        #barcode-image {max-width: 60%; height: auto;}
        
        #print-button {
            display: block; 
            width: 100%; 
            padding: 12px; 
            background-color: #28a745; 
            color: white; 
            border: none; 
            border-radius: 5px; 
            font-size: 18px; 
            cursor: pointer; 
            margin-top: 20px; 
            visibility: hidden;
        }
        #print-button:hover {background-color: #218838;}

        #label {
            width: 100%;
            max-width: 500px;
            height: auto;
            margin: 0 auto;
            display: block;
        }
/* Campo de búsqueda resaltado */
#product-search, .ts-control {
    background-color: #fff !important;  /* Fondo blanco */
    color: #000 !important;             /* Letras negras */
    border: 1px solid #000;             /* Borde negro */
    border-radius: 6px; 
    padding: 8px 10px !important; 
    font-size: 14px; 
    width: 80%; 
    margin: 5px auto 10px auto; 
    display: block;
    text-align: left;
}

.ts-dropdown {
    background-color: #fff !important;  /* Fondo de la lista desplegable */
    color: #000 !important;             /* Texto negro */
    border: 1px solid #000; 
}


        @page {
            size: 2in 4in; 
            margin: 0;
        }

        @media print {
            body > *:not(#label-container) {display: none;}
            
            html, body {
                margin: 0 !important; 
                padding: 0 !important;
                height: 4in;
                display: block;
            }

            #label-container {
                visibility: visible; 
                position: absolute; 
                bottom: 0; 
                left: 0;
                width: 100%;
                height: auto;
                margin: 0;
                padding: 0; 
                border: none;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <img id="label" src="../IMG/Logo Listo - Negro.png" alt="Logo" onerror="this.style.display='none'">

    <label for="product-search">Busca un Producto:</label>
    <select id="product-search" placeholder="Escribe para buscar..."></select>

    <label for="quantity">Cantidad:</label>
    <input type="number" id="quantity" min="1" value="1">

    <button id="print-button" onclick="window.print()">Imprimir Etiqueta</button>
</div>

<!-- Etiqueta generada -->
<div id="label-container">
    <div id="product-name"></div>
    <div id="unit-quantity"></div>
    <canvas id="barcode-canvas" style="display:none;"></canvas>
    <img id="barcode-image" alt="Código de barras">
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const labelContainer = document.getElementById('label-container');
    const printButton = document.getElementById('print-button');
    const labelProductName = document.getElementById('product-name');
    const labelUnitQuantity = document.getElementById('unit-quantity');
    const barcodeImage = document.getElementById('barcode-image');
    const barcodeCanvas = document.getElementById('barcode-canvas');
    const quantityInput = document.getElementById('quantity');

    new TomSelect('#product-search', {
        valueField: 'itemid', 
        labelField: 'ProductName', 
        searchField: 'ProductName', 
        
        load: function(query, callback) {
            if (!query.length) return callback(); 

fetch('/Logica/search.php?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(json => {
                    callback(json);
                }).catch(()=>{ callback(); });
        },
        
        onChange: function(value) {
            if (!value) { 
                labelContainer.style.visibility = 'hidden';
                printButton.style.visibility = 'hidden';
                return;
            }
            
            const selectedItem = this.options[value];
            const itemId = selectedItem.itemid;
            const productName = selectedItem.ProductName;
            const unitid = selectedItem.unitid;

            labelProductName.textContent = productName;

            const qty = quantityInput.value || 1;
            labelUnitQuantity.textContent = qty + " " + unitid;

            try {
                JsBarcode(barcodeCanvas, itemId, {
                    format: "CODE128", 
                    width: 2.5, 
                    height: 60, 
                    displayValue: true, 
                    fontSize: 16
                });
                barcodeImage.src = barcodeCanvas.toDataURL("image/png");
                
                labelContainer.style.visibility = 'visible';
                printButton.style.visibility = 'visible';
            } catch (e) {
                console.error("Error al generar el código de barras:", e);
                alert("No se pudo generar el código de barras para este producto.");
            }
        }
    });

    quantityInput.addEventListener('input', function() {
        const tomSelect = document.querySelector('#product-search').tomselect;
        const value = tomSelect.getValue();
        if (value) {
            const selectedItem = tomSelect.options[value];
            const unitid = selectedItem.unitid;
            labelUnitQuantity.textContent = (this.value || 1) + " " + unitid;
        }
    });
});
</script>

</body>
</html>
