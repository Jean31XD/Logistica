<?php
require_once __DIR__ . '/../../conexionBD/session_config.php';
verificarAutenticacion([8, 0]); // Solo pantallas 8 y 0

// Mapeo de pantallas a su página principal/inicio
$homePage = [
    0 => 'Admin.php',
    1 => 'Inicio_gestion.php',
    2 => 'facturas.php',
    3 => 'CXC.php',
    4 => 'Reporte.php',
    5 => 'Paneladmin.php',
    6 => 'BI.php',
    8 => 'Listo-etiquetas.php',
    9 => 'dashboard.php'
];

$homeUrl = $homePage[$_SESSION['pantalla'] ?? 0] ?? 'Inicio.php';
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
        :root {
            --primary: #E63946;
            --primary-dark: #D62839;
            --accent: #457B9D;
            --accent-dark: #1D3557;
            --success: #10B981;
        }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--gray-100);
            color: #2D3748;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
            min-height: 100vh;
            position: relative;
        }
            50% { transform: translateY(-30px) translateX(30px); }
        }
        .container {
            background: rgba(255, 255, 255, 0.98);
            padding: 3rem;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 700px;
            position: relative;
            z-index: 10;
            backdrop-filter: blur(10px);
        }
        h1 {
            color: var(--primary);
            text-align: center;
            margin-bottom: 2rem;
            font-weight: 800;
            font-size: 2.5rem;
        }
        label {
            display: block;
            font-weight: 700;
            color: var(--accent-dark);
            margin-bottom: 0.5rem;
            margin-top: 1.5rem;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .ts-control {
            padding: 1.25rem 1.5rem !important;
            font-size: 1.2rem;
            border-radius: 12px;
            border: 2px solid #E2E8F0 !important;
            background: #F7FAFC !important;
            transition: all 0.3s ease;
        }
        .ts-control:focus-within {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 4px rgba(230, 57, 70, 0.1);
        }

        #quantity {
            border-radius: 12px;
            padding: 1.25rem 1.5rem !important;
            font-size: 1.2rem;
            width: 100%;
            margin: 0.5rem 0 1.5rem 0;
            display: block;
            border: 2px solid #E2E8F0;
            background: #F7FAFC;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        #quantity:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(230, 57, 70, 0.1);
        }

        /* Contenedor de la etiqueta */
        #label-container {
            width: 2in;
            height: 4in;
            border: 2px dashed var(--accent);
            margin: 1.5rem auto;
            padding: 10px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            background: #fff;
            visibility: hidden;
            border-radius: 8px;
        }

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
            padding: 1rem 1.5rem;
            background: var(--success);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.125rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 2rem;
            visibility: hidden;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        #print-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }
        #print-button:active {
            transform: translateY(0);
        }

        #label {
            width: 100%;
            max-width: 200px;
            height: auto;
            margin: 0 auto 2rem auto;
            display: block;
        }

        /* Botón de inicio */
        .home-btn {
            background: var(--accent);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(69, 123, 157, 0.3);
        }
        .home-btn:hover {
            background: #5FA3C7;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(69, 123, 157, 0.4);
        }
        .home-btn:active {
            transform: translateY(0);
        }

        /* Campo de búsqueda resaltado */
        #product-search, .ts-control {
            background-color: #F7FAFC !important;
            color: #2D3748 !important;
            border: 2px solid #E2E8F0 !important;
            border-radius: 12px;
            padding: 0.875rem 1rem !important;
            font-size: 1rem;
            width: 100%;
            margin: 0.5rem 0 0 0;
            display: block;
            font-weight: 600;
        }

        .ts-dropdown {
            background-color: #fff !important;
            color: #2D3748 !important;
            border: 2px solid var(--primary) !important;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .ts-dropdown .option {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid #E2E8F0;
        }
        .ts-dropdown .option:hover, .ts-dropdown .option.active {
            background: rgba(230, 57, 70, 0.1);
            color: var(--primary);
        }


        @page {
            size: 2in 4in; 
            margin: 0;
        }

        @media print {
            body > *:not(#label-container) {display: none;}
            .home-btn {display: none;}
            
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
    <img id="label" src="../../IMG/Logo Listo - Negro.png" alt="Logo" onerror="this.style.display='none'">

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h1 style="margin: 0;">🏷️ Generador de Etiquetas</h1>
        <a href="<?php echo htmlspecialchars($homeUrl); ?>" class="home-btn" title="Volver al Menú Principal">
            <i class="fas fa-home"></i> Inicio
        </a>
    </div>

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

fetch('../../Logica/api_search.php?type=productos&q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(response => {
                    // El nuevo endpoint devuelve { success, data }
                    callback(response.data || response);
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

<!-- Footer de Ayuda -->
<footer class="help-footer">
    <div class="help-footer-content">
        <div class="help-section">
            <div class="help-icon">
                <i class="fas fa-life-ring"></i>
            </div>
            <div class="help-text">
                <h4>¿Necesitas Ayuda?</h4>
                <p>Soporte disponible para asistirte</p>
            </div>
        </div>

        <div class="help-section">
            <div class="help-icon">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="help-text">
                <h4>Soporte Técnico</h4>
                <p><a href="mailto:Jean.sencion@corripio.com.do">Jean.sencion@corripio.com.do</a></p>
            </div>
        </div>

        <div class="help-section">
            <div class="help-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="help-text">
                <h4>Horario de Atención</h4>
                <p>Lunes a Viernes: 8:00 AM - 5:00 PM</p>
            </div>
        </div>
    </div>

    <div class="help-footer-bottom">
        <p>&copy; <?= date('Y') ?> MACO - Sistema de Logística</p>
    </div>
</footer>

<style>
    .help-footer {
        background: white;
        border-top: 3px solid var(--primary);
        margin-top: 3rem;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
        width: 100%;
    }

    .help-footer-content {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem 1.5rem;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 2rem;
    }

    .help-section {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }

    .help-icon {
        width: 48px;
        height: 48px;
        background: var(--primary);
        color: white;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .help-text h4 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--accent-dark);
        margin: 0 0 0.5rem 0;
    }

    .help-text p {
        font-size: 0.9rem;
        color: #6B7280;
        margin: 0;
        line-height: 1.5;
    }

    .help-text a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s;
    }

    .help-text a:hover {
        color: var(--primary-dark);
        text-decoration: underline;
    }

    .help-footer-bottom {
        border-top: 1px solid #E5E7EB;
        padding: 1rem;
        text-align: center;
        background: #F9FAFB;
    }

    .help-footer-bottom p {
        margin: 0;
        font-size: 0.85rem;
        color: #6B7280;
    }

    @media (max-width: 768px) {
        .help-footer-content {
            grid-template-columns: 1fr;
            padding: 1.5rem 1rem;
        }
    }

    @media print {
        .help-footer {
            display: none;
        }
    }
</style>

</body>
</html>
