<?php
/**
 * Panel de Administración - MACO Design System
 */

require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion([5]); // Solo pantalla 5 puede acceder
require_once __DIR__ . '/../conexionBD/conexion.php';

$csrfToken = generarTokenCSRF();

$pageTitle = "Panel de Administración | MACO";
$additionalCSS = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />';
include __DIR__ . '/templates/header.php';
?>

<h1 class="maco-title maco-title-gradient">
    <i class="fas fa-user-shield"></i>
    Panel de Administración
</h1>

<p class="maco-subtitle">
    Acceso rápido a herramientas administrativas del sistema
</p>

<div class="maco-grid maco-grid-2" style="margin-top: 2rem;">
    <div class="maco-card maco-card-hover maco-fade-in">
        <div class="maco-card-icon">
            <i class="fas fa-file-invoice-dollar"></i>
        </div>
        <div class="maco-card-content">
            <h3 class="maco-card-title">Reporte Facturas CXC</h3>
            <p class="maco-card-description">
                Reporte de facturas faltantes y seguimiento CXC
            </p>
            <a href="../View/BI.php" class="maco-btn maco-btn-primary w-100">
                <i class="fas fa-arrow-right me-2"></i>Ingresar
            </a>
        </div>
    </div>

    <div class="maco-card maco-card-hover maco-fade-in" style="animation-delay: 0.1s;">
        <div class="maco-card-icon">
            <i class="fas fa-tachometer-alt"></i>
        </div>
        <div class="maco-card-content">
            <h3 class="maco-card-title">Dashboard</h3>
            <p class="maco-card-description">
                Visión general y estadísticas de gestión
            </p>
            <a href="../View/dashboard.php" class="maco-btn maco-btn-primary w-100">
                <i class="fas fa-arrow-right me-2"></i>Ingresar
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>
