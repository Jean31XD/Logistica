<?php
/**
 * Panel de Validación CXC - MACO Design System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Santo_Domingo');

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Verificar autenticación y permisos
if (!isset($_SESSION['usuario'], $_SESSION['pantalla']) || $_SESSION['pantalla'] != 3) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../conexionBD/conexion.php';

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = "Panel de Validación CXC | MACO";
$additionalCSS = '<link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet" />';
include __DIR__ . '/templates/header.php';
?>

<h1 class="maco-title maco-title-gradient">
    <i class="fas fa-file-invoice-dollar"></i>
    Panel de Validación CXC
</h1>

<p class="maco-subtitle">
    Acceso a módulos de validación y gestión CXC
</p>

<div class="maco-grid maco-grid-3" style="margin-top: 2rem;">
    <div class="maco-card maco-card-hover maco-fade-in">
        <div class="maco-card-icon">
            <i class="fas fa-file-invoice"></i>
        </div>
        <div class="maco-card-content">
            <h3 class="maco-card-title">Validación de Facturas</h3>
            <p class="maco-card-description">
                Validar y gestionar facturas pendientes
            </p>
            <a href="../View/facturas.php" class="maco-btn maco-btn-primary w-100">
                <i class="fas fa-arrow-right me-2"></i>Ingresar
            </a>
        </div>
    </div>

    <div class="maco-card maco-card-hover maco-fade-in" style="animation-delay: 0.1s;">
        <div class="maco-card-icon">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="maco-card-content">
            <h3 class="maco-card-title">Reportes</h3>
            <p class="maco-card-description">
                Consultar reportes y estadísticas CXC
            </p>
            <a href="../View/Reporte.php" class="maco-btn maco-btn-primary w-100">
                <i class="fas fa-arrow-right me-2"></i>Ingresar
            </a>
        </div>
    </div>

    <div class="maco-card maco-card-hover maco-fade-in" style="animation-delay: 0.2s;">
        <div class="maco-card-icon">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="maco-card-content">
            <h3 class="maco-card-title">Business Intelligence</h3>
            <p class="maco-card-description">
                Análisis avanzado de datos y métricas
            </p>
            <a href="../View/BI.php" class="maco-btn maco-btn-primary w-100">
                <i class="fas fa-arrow-right me-2"></i>Ingresar
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>
