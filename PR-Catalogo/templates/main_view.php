<?php
// templates/main_view.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo Interactivo de Productos</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <header class="header">
        <img src="public/img/LOGO MC - COLOR.png" alt="Logo de la Empresa" class="header-logo">
        <form class="search-form" id="search-form" onsubmit="return false;">
            <input type="text" name="q" id="search-box" placeholder="Buscar por Nombre o SKU..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" autocomplete="off">
        </form>
    </header>

    <div class="page-container">
        <aside class="filters-sidebar">
            <form id="filter-form">
                <h2>Filtros</h2>
                <div class="filter-group">
                    <label for="categoria">Categoría</label>
                    <select name="categoria" id="categoria" autocomplete="off">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= (($selected_categoria ?? '') == $cat) ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="marca">Marca</label>
                    <select name="marca" id="marca" autocomplete="off">
                        <option value="">Todas</option>
                        <?php foreach ($marcas as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>" <?= (($selected_marca ?? '') == $m) ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="checkbox-label" for="tiene_imagen">
                        <input type="checkbox" name="tiene_imagen" id="tiene_imagen" value="con" <?= (($_GET['tiene_imagen'] ?? '') == 'con') ? 'checked' : '' ?>>
                        <span>Solo mostrar con imagen</span>
                    </label>
                </div>
            </form>
            
            <div class="stats-box" id="stats-box">
                <div id="stats-content">
                    <h3>Resumen de Inventario</h3>
                    <p><span>Artículos Totales:</span> <strong><?= number_format($global_stats['total_db']) ?></strong></p>
                    <p><span>Con Imagen:</span> <strong><?= number_format($global_stats['total_images']) ?></strong></p>
                    <p><span>Sin Imagen:</span> <strong><?= number_format($global_stats['missing_images']) ?></strong></p>
                </div>
            </div>

            <div class="top-products-box">
                <h3>⭐ Top 10 Productos</h3>
                <ol>
                    <?php if (!empty($top_10_products)): ?>
                        <?php foreach ($top_10_products as $product): ?>
                            <li>
                                <span class="product-name"><?= htmlspecialchars($product['ProductName']) ?></span>
                                <span class="product-sku"><?= htmlspecialchars($product['itemid']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No se pudo cargar el top.</li>
                    <?php endif; ?>
                </ol>
            </div>
            
        </aside>

        <main id="main-content">
            <?php echo render_product_area($product_area_data); ?>
        </main>
    </div>

    <div id="lightbox" class="lightbox">
        <span class="close">&times;</span>
        <img class="lightbox-content" id="lightbox-img">
    </div>

    <script src="public/js/main.js"></script>
</body>
</html>