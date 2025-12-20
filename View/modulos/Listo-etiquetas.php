<?php
require_once __DIR__ . '/../../conexionBD/session_config.php';
require_once __DIR__ . '/../../conexionBD/conexion.php';

// Verificar autenticación básica
if (!isset($_SESSION['usuario'])) {
    header("Location: " . getLoginUrl());
    exit();
}

// Verificar permiso usando usuario_modulos
if (!tieneModulo('sistema_etiquetado', $conn)) {
    header("Location: " . getBaseUrl() . "/View/pantallas/Portal.php?error=permisos");
    exit();
}

// URL de home siempre al Portal
$homeUrl = '../pantallas/Portal.php';
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
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --et-primary: #E63946;
            --et-secondary: #1D3557;
            --et-accent: #457B9D;
            --et-success: #22C55E;
            --et-bg: linear-gradient(135deg, #F7FAFC 0%, #EDF2F7 100%);
            --et-card: #FFFFFF;
            --et-border: #E2E8F0;
            --et-text: #2D3748;
            --et-muted: #718096;
            --et-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--et-bg);
            color: var(--et-text);
            min-height: 100vh;
            margin: 0;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Header estilo dashboard */
        .et-header {
            background: linear-gradient(135deg, var(--et-secondary) 0%, var(--et-accent) 100%);
            padding: 1.5rem 2rem;
            border-radius: 16px;
            color: #fff;
            width: 100%;
            max-width: 700px;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .et-header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .et-header p {
            margin: 0.25rem 0 0;
            opacity: 0.85;
            font-size: 0.9rem;
        }

        .et-home-btn {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 0.6rem 1.25rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            backdrop-filter: blur(10px);
        }

        .et-home-btn:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }

        /* Card principal */
        .et-card {
            background: linear-gradient(135deg, #fff 0%, #F7FAFC 100%);
            border-radius: 16px;
            box-shadow: var(--et-shadow-lg);
            width: 100%;
            max-width: 700px;
            overflow: hidden;
            border-left: 6px solid var(--et-primary);
        }

        .et-card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--et-border);
            background: rgba(247, 250, 252, 0.5);
        }

        .et-card-header i { color: var(--et-primary); font-size: 1.1rem; }
        .et-card-header h3 { font-size: 1rem; font-weight: 700; margin: 0; color: var(--et-text); }

        .et-card-body {
            padding: 1.5rem;
        }

        /* Formulario */
        .et-form-group {
            margin-bottom: 1.25rem;
        }

        .et-form-group label {
            display: block;
            font-weight: 600;
            color: var(--et-text);
            margin-bottom: 0.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .et-form-group input,
        .et-form-group select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--et-border);
            border-radius: 8px;
            font-size: 1rem;
            background: #fff;
            transition: all 0.2s;
            font-weight: 500;
        }

        .et-form-group input:focus,
        .et-form-group select:focus {
            outline: none;
            border-color: var(--et-primary);
            box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
        }

        /* Tom Select override */
        .ts-control {
            padding: 0.75rem 1rem !important;
            font-size: 1rem !important;
            border-radius: 8px !important;
            border: 2px solid var(--et-border) !important;
            background: #fff !important;
        }

        .ts-control:focus-within {
            border-color: var(--et-primary) !important;
            box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1) !important;
        }

        .ts-dropdown {
            background: #fff !important;
            border: 2px solid var(--et-primary) !important;
            border-radius: 8px !important;
            box-shadow: var(--et-shadow-lg) !important;
        }

        .ts-dropdown .option {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--et-border);
        }

        .ts-dropdown .option:hover,
        .ts-dropdown .option.active {
            background: rgba(230, 57, 70, 0.1);
            color: var(--et-primary);
        }

        /* Contenedor de etiqueta (preview) */
        #label-container {
            width: 2in;
            height: 4in;
            border: 2px dashed var(--et-accent);
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

        #label-logo { max-height: 40px; margin-bottom: 2px; object-fit: contain; }
        #product-name { font-weight: bold; font-size: 10px; margin: 5px 0; line-height: 1.2; flex-grow: 1; display: flex; align-items: center; justify-content: center; }
        #unit-quantity { font-size: 14px; font-weight: bold; margin: 5px 0; }
        #barcode-image { max-width: 60%; height: auto; }

        /* Botón imprimir */
        .et-btn-print {
            display: block;
            width: 100%;
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, var(--et-success), #16a34a);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 1.5rem;
            visibility: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }

        .et-btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(34, 197, 94, 0.4);
        }

        /* Logo */
        .et-logo {
            width: 100%;
            max-width: 180px;
            height: auto;
            margin: 0 auto 1rem auto;
            display: block;
        }

        @page { size: 2in 4in; margin: 0; }

        @media print {
            body > *:not(#label-container) { display: none; }
            .et-header, .et-card, .et-home-btn { display: none !important; }
            html, body { margin: 0 !important; padding: 0 !important; height: 4in; display: block; }
            #label-container { visibility: visible; position: absolute; bottom: 0; left: 0; width: 100%; height: auto; margin: 0; padding: 0; border: none; box-shadow: none; }
        }

        @media (max-width: 768px) {
            .et-header { flex-direction: column; text-align: center; gap: 1rem; }
            body { padding: 1rem; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<div class="et-header">
    <div>
        <h1><i class="fas fa-tags"></i> Sistema de Etiquetado</h1>
        <p>Generador de etiquetas con código de barras</p>
    </div>
    <a href="<?php echo htmlspecialchars($homeUrl); ?>" class="et-home-btn">
        <i class="fas fa-home"></i> Inicio
    </a>
</div>

<!-- CARD PRINCIPAL -->
<div class="et-card">
    <div class="et-card-header">
        <i class="fas fa-barcode"></i>
        <h3>Generar Etiqueta</h3>
    </div>
    <div class="et-card-body">
        <img class="et-logo" src="../../IMG/Logo Listo - Negro.png" alt="Logo" onerror="this.style.display='none'">

        <div class="et-form-group">
            <label for="product-search">Buscar Producto</label>
            <select id="product-search" placeholder="Escribe para buscar..."></select>
        </div>

        <div class="et-form-group">
            <label for="quantity">Cantidad</label>
            <input type="number" id="quantity" min="1" value="1">
        </div>

        <button id="print-button" class="et-btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimir Etiqueta
        </button>
    </div>
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
