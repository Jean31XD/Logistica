<?php
/**
 * Portal de Módulos - MACO v3.0
 * Muestra solo los módulos asignados al usuario actual.
 */

require_once __DIR__ . '/../../conexionBD/session_config.php';
verificarAutenticacion();
require_once __DIR__ . '/../../conexionBD/conexion.php';

$usuario  = $_SESSION['usuario'] ?? '';
$pantalla = $_SESSION['pantalla'] ?? -1;

// Obtener módulos asignados al usuario
$modulosAsignados = [];
$sql  = "SELECT modulo FROM usuario_modulos WHERE usuario = ? AND activo = 1";
$stmt = sqlsrv_query($conn, $sql, [$usuario]);
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $modulosAsignados[] = $row['modulo'];
    }
    sqlsrv_free_stmt($stmt);
}

// Cargar configuración de módulos
$config      = require __DIR__ . '/../../config/app.php';
$todosModulos = $config['modulos'] ?? [];

// Colores diferenciados por categoría de módulo
$modulosData = [
    'despacho_factura'      => ['link' => '../modulos/Despacho_factura.php',    'icon' => 'fa-truck-fast',    'color' => '#E63946'],
    'validacion_facturas'   => ['link' => '../modulos/facturas.php',            'icon' => 'fa-check-double',  'color' => '#2563EB'],
    'recepcion_documentos'  => ['link' => '../modulos/facturas-recepcion.php',  'icon' => 'fa-inbox',         'color' => '#059669'],
    'business_intelligence' => ['link' => '../modulos/BI.php',                  'icon' => 'fa-chart-pie',     'color' => '#7C3AED'],
    'sistema_etiquetado'    => ['link' => '../modulos/Listo-etiquetas.php',     'icon' => 'fa-tags',          'color' => '#D97706'],
    'gestion_usuarios'      => ['link' => '../modulos/Gestion_de_usuario.php',  'icon' => 'fa-users-cog',     'color' => '#1D3557'],
    'dashboard_general'     => ['link' => '../modulos/dashboard.php',           'icon' => 'fa-tachometer-alt','color' => '#0891B2'],
    'listo_inventario'      => ['link' => '../modulos/Listo_inventario.php',    'icon' => 'fa-warehouse',     'color' => '#65A30D'],
    'codigos_barras'        => ['link' => '../modulos/Codigos_de_barras.php',   'icon' => 'fa-barcode',       'color' => '#374151'],
    'codigos_referencia'    => ['link' => '../modulos/Codigos_referencia.php',  'icon' => 'fa-list-alt',      'color' => '#457B9D'],
    'gestion_imagenes'      => ['link' => '../modulos/Gestion_imagenes.php',    'icon' => 'fa-images',        'color' => '#DB2777'],
    'gestion_transportistas'=> ['link' => '../modulos/Gestion_transportistas.php','icon'=> 'fa-truck',        'color' => '#C2410C'],
    'reporte_despacho'      => ['link' => '../modulos/Reporte_despacho.php',    'icon' => 'fa-chart-line',    'color' => '#0D9488'],
];

// Filtrar solo los módulos asignados
$modulosFiltrados = [];
foreach ($modulosAsignados as $key) {
    if (isset($todosModulos[$key])) {
        $modulosFiltrados[$key] = array_merge($todosModulos[$key], $modulosData[$key] ?? []);
    }
}

$nombreVisible = htmlspecialchars($_SESSION['nombre'] ?? $usuario);
$pageTitle     = "Mi Portal | MACO";

$additionalCSS = <<<'CSS'
<style>
    /* ===== PORTAL HERO ===== */
    .portal-hero {
        background: linear-gradient(135deg, var(--accent-dark) 0%, #16213e 100%);
        padding: 2.5rem 2rem;
        border-radius: var(--radius-xl);
        margin-bottom: 2rem;
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        flex-wrap: wrap;
        position: relative;
        overflow: hidden;
    }

    .portal-hero::before {
        content: '';
        position: absolute;
        right: -60px;
        top: -60px;
        width: 220px;
        height: 220px;
        background: rgba(230,57,70,0.12);
        border-radius: 50%;
        pointer-events: none;
    }

    .portal-hero::after {
        content: '';
        position: absolute;
        right: 80px;
        bottom: -80px;
        width: 160px;
        height: 160px;
        background: rgba(255,255,255,0.04);
        border-radius: 50%;
        pointer-events: none;
    }

    .portal-hero-text {
        position: relative;
        z-index: 1;
    }

    .portal-hero-text h1 {
        font-family: 'Poppins', 'Inter', sans-serif;
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 0.35rem;
        letter-spacing: -0.4px;
        line-height: 1.2;
    }

    .portal-hero-text p {
        opacity: 0.75;
        font-size: 0.95rem;
        margin: 0;
    }

    .portal-hero-badge {
        position: relative;
        z-index: 1;
        background: rgba(230,57,70,0.25);
        border: 1px solid rgba(230,57,70,0.4);
        color: white;
        padding: 0.5rem 1.25rem;
        border-radius: 2rem;
        font-size: 0.8rem;
        font-weight: 600;
        white-space: nowrap;
        letter-spacing: 0.3px;
    }

    /* ===== MÓDULOS HEADER ===== */
    .modules-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.25rem;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .modules-header h2 {
        font-family: 'Poppins', 'Inter', sans-serif;
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 0;
    }

    .modules-count {
        font-size: 0.8rem;
        color: var(--text-secondary);
        background: var(--gray-100);
        padding: 0.2rem 0.75rem;
        border-radius: 2rem;
        font-weight: 500;
    }

    /* ===== GRID DE MÓDULOS ===== */
    .module-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.25rem;
    }

    /* ===== TARJETA DE MÓDULO ===== */
    .module-card {
        background: white;
        border-radius: var(--radius-xl);
        padding: 1.75rem;
        box-shadow: var(--shadow);
        border: 1px solid var(--border-color);
        transition: box-shadow 0.25s ease, transform 0.25s ease, border-color 0.25s ease;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        text-decoration: none;
        color: inherit;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .module-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--module-color, var(--primary));
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.25s ease;
    }

    .module-card:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-4px);
        border-color: var(--module-color, var(--primary));
    }

    .module-card:hover::before {
        transform: scaleX(1);
    }

    .module-card:focus-visible {
        outline: 3px solid var(--module-color, var(--primary));
        outline-offset: 2px;
    }

    .module-card-icon {
        width: 56px;
        height: 56px;
        border-radius: var(--radius-lg);
        background: var(--module-color, var(--primary));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .module-card-body {
        flex: 1;
    }

    .module-card-body h3 {
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: 0.35rem;
        color: var(--text-primary);
        line-height: 1.3;
    }

    .module-card-body p {
        color: var(--text-secondary);
        font-size: 0.85rem;
        margin: 0;
        line-height: 1.5;
    }

    .module-card-arrow {
        align-self: flex-end;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--gray-100);
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
        flex-shrink: 0;
    }

    .module-card:hover .module-card-arrow {
        background: var(--module-color, var(--primary));
        color: white;
        transform: translateX(3px);
    }

    /* ===== ESTADO VACÍO ===== */
    .empty-state {
        text-align: center;
        padding: 5rem 2rem;
        background: white;
        border-radius: var(--radius-xl);
        border: 1px solid var(--border-color);
    }

    .empty-state-icon {
        width: 80px;
        height: 80px;
        background: var(--gray-100);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2rem;
        color: var(--gray-400);
    }

    .empty-state h2 {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .empty-state p {
        color: var(--text-secondary);
        font-size: 0.9rem;
        max-width: 360px;
        margin: 0 auto;
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 640px) {
        .portal-hero {
            padding: 1.75rem 1.5rem;
            flex-direction: column;
            align-items: flex-start;
        }

        .portal-hero-text h1 {
            font-size: 1.4rem;
        }

        .module-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
CSS;

include __DIR__ . '/../templates/header.php';
?>

<div style="max-width:1400px; margin:0 auto;">

    <!-- Hero de bienvenida -->
    <div class="portal-hero" role="banner">
        <div class="portal-hero-text">
            <h1>Bienvenido, <?= $nombreVisible ?></h1>
            <p>Selecciona un módulo para comenzar a trabajar</p>
        </div>
        <?php if (!empty($modulosFiltrados)): ?>
        <span class="portal-hero-badge">
            <i class="fas fa-th-large me-1" aria-hidden="true"></i>
            <?= count($modulosFiltrados) ?> módulo<?= count($modulosFiltrados) !== 1 ? 's' : '' ?> disponible<?= count($modulosFiltrados) !== 1 ? 's' : '' ?>
        </span>
        <?php endif; ?>
    </div>

    <?php if (empty($modulosFiltrados)): ?>

    <!-- Estado vacío -->
    <div class="empty-state" role="status">
        <div class="empty-state-icon" aria-hidden="true">
            <i class="fas fa-lock"></i>
        </div>
        <h2>Sin módulos asignados</h2>
        <p>Contacta a un administrador para que te asigne acceso a los módulos del sistema.</p>
    </div>

    <?php else: ?>

    <!-- Encabezado del grid -->
    <div class="modules-header">
        <h2>Mis Módulos</h2>
        <span class="modules-count"><?= count($modulosFiltrados) ?> en total</span>
    </div>

    <!-- Grid de módulos -->
    <nav class="module-grid" aria-label="Módulos disponibles">
        <?php foreach ($modulosFiltrados as $key => $modulo):
            $color = htmlspecialchars($modulo['color'] ?? '#E63946');
            $link  = htmlspecialchars($modulo['link'] ?? '#');
            $icon  = htmlspecialchars($modulo['icon'] ?? 'fa-cube');
            $name  = htmlspecialchars($modulo['name'] ?? $key);
            $desc  = htmlspecialchars($modulo['description'] ?? '');
        ?>
        <a href="<?= $link ?>"
           class="module-card"
           style="--module-color: <?= $color ?>;"
           aria-label="Acceder a <?= $name ?>">

            <div class="module-card-icon" aria-hidden="true">
                <i class="fas <?= $icon ?>"></i>
            </div>

            <div class="module-card-body">
                <h3><?= $name ?></h3>
                <p><?= $desc ?></p>
            </div>

            <div class="module-card-arrow" aria-hidden="true">
                <i class="fas fa-arrow-right"></i>
            </div>

        </a>
        <?php endforeach; ?>
    </nav>

    <?php endif; ?>

</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
