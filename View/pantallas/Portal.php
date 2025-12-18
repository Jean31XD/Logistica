<?php
/**
 * Portal de Módulos - MACO
 * 
 * Muestra solo los módulos asignados al usuario actual.
 * Si el usuario no tiene módulos asignados, muestra un mensaje.
 * TODOS los usuarios usan este portal, incluyendo administradores.
 */

require_once __DIR__ . '/../../conexionBD/session_config.php';
verificarAutenticacion();
require_once __DIR__ . '/../../conexionBD/conexion.php';

$usuario = $_SESSION['usuario'] ?? '';
$pantalla = $_SESSION['pantalla'] ?? -1;

// Obtener módulos asignados al usuario
$modulosAsignados = [];
$sql = "SELECT modulo FROM usuario_modulos WHERE usuario = ? AND activo = 1";
$stmt = sqlsrv_query($conn, $sql, [$usuario]);

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $modulosAsignados[] = $row['modulo'];
    }
    sqlsrv_free_stmt($stmt);
}

// Cargar configuración de módulos
$config = require __DIR__ . '/../../config/app.php';
$todosModulos = $config['modulos'] ?? [];

// Mapeo de keys a datos del módulo con links
$modulosData = [
    'despacho_factura' => ['link' => '../modulos/Despacho_factura.php', 'icon' => 'fa-truck-fast'],
    'validacion_facturas' => ['link' => '../modulos/facturas.php', 'icon' => 'fa-check-double'],
    'recepcion_documentos' => ['link' => '../modulos/facturas-recepcion.php', 'icon' => 'fa-inbox'],
    'business_intelligence' => ['link' => '../modulos/BI.php', 'icon' => 'fa-chart-pie'],
    'sistema_etiquetado' => ['link' => '../modulos/Listo-etiquetas.php', 'icon' => 'fa-tags'],
    'gestion_usuarios' => ['link' => '../modulos/Gestion_de_usuario.php', 'icon' => 'fa-users-cog'],
    'dashboard_general' => ['link' => '../modulos/dashboard.php', 'icon' => 'fa-tachometer-alt'],
    'listo_inventario' => ['link' => '../modulos/Listo_inventario.php', 'icon' => 'fa-warehouse'],
    'codigos_barras' => ['link' => '../modulos/Codigos_de_barras.php', 'icon' => 'fa-barcode'],
    'codigos_referencia' => ['link' => '../modulos/Codigos_referencia.php', 'icon' => 'fa-list-alt'],
    'gestion_imagenes' => ['link' => '../modulos/Gestion_imagenes.php', 'icon' => 'fa-images'],
    'gestion_transportistas' => ['link' => '../modulos/Gestion_transportistas.php', 'icon' => 'fa-truck'],
    'reporte_despacho' => ['link' => '../modulos/Reporte_despacho.php', 'icon' => 'fa-chart-line'],
];

// Filtrar solo los módulos asignados
$modulosFiltrados = [];
foreach ($modulosAsignados as $key) {
    if (isset($todosModulos[$key])) {
        $modulosFiltrados[$key] = array_merge($todosModulos[$key], $modulosData[$key] ?? []);
    }
}

$pageTitle = "Mi Portal | MACO";
$additionalCSS = <<<'CSS'
<style>
    .portal-container {
        max-width: 1400px;
        margin: 0 auto;
    }

    .portal-hero {
        background: linear-gradient(135deg, var(--primary) 0%, #c1121f 100%);
        padding: 3rem 2rem;
        border-radius: var(--radius-xl);
        margin-bottom: 2rem;
        color: white;
        text-align: center;
    }

    .portal-hero h1 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .portal-hero p {
        opacity: 0.9;
        font-size: 1.1rem;
    }

    .module-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 2rem;
    }

    .module-card {
        background: white;
        border-radius: var(--radius-xl);
        padding: 2rem;
        box-shadow: var(--shadow-lg);
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .module-card:hover {
        transform: translateY(-8px);
        border-color: var(--primary);
    }

    .module-card-icon {
        width: 70px;
        height: 70px;
        border-radius: var(--radius-lg);
        background: var(--primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin-bottom: 1.5rem;
    }

    .module-card h3 {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--text-primary);
    }

    .module-card p {
        color: var(--text-secondary);
        font-size: 0.9rem;
        margin-bottom: 1.5rem;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: var(--radius-xl);
    }

    .empty-state i {
        font-size: 5rem;
        color: var(--gray-300);
        margin-bottom: 1.5rem;
    }

    .empty-state h2 {
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .empty-state p {
        color: var(--text-secondary);
    }
</style>
CSS;

include __DIR__ . '/../templates/header.php';
?>

<div class="portal-container">
    <div class="portal-hero">
        <h1><i class="fas fa-user-circle me-3"></i>Bienvenido, <?= htmlspecialchars($_SESSION['nombre'] ?? $usuario) ?></h1>
        <p>Accede a los módulos que tienes asignados</p>
    </div>

    <?php if (empty($modulosFiltrados)): ?>
    <!-- Estado vacío: sin módulos asignados -->
    <div class="empty-state">
        <i class="fas fa-lock"></i>
        <h2>No tienes módulos asignados</h2>
        <p>Contacta a un administrador para que te asigne acceso a los módulos del sistema.</p>
    </div>

    <?php else: ?>
    <!-- Grid de módulos asignados -->
    <div class="module-grid">
        <?php foreach ($modulosFiltrados as $key => $modulo): ?>
        <div class="module-card">
            <div class="module-card-icon">
                <i class="fas <?= $modulo['icon'] ?? 'fa-cube' ?>"></i>
            </div>
            <h3><?= htmlspecialchars($modulo['name']) ?></h3>
            <p><?= htmlspecialchars($modulo['description']) ?></p>
            <a href="<?= $modulo['link'] ?>" class="maco-btn maco-btn-primary w-100">
                <i class="fas fa-arrow-right me-2"></i>Acceder
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/../templates/footer.php';
?>
