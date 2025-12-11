<?php
/**
 * Panel de Despacho - MACO Design System
 */

require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion([1]); // Solo pantalla 1 puede acceder
require_once __DIR__ . '/../conexionBD/conexion.php';

$csrfToken = generarTokenCSRF();

$pageTitle = "Panel de Despacho | MACO";
$additionalCSS = '<link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet" />';
include __DIR__ . '/templates/header.php';
?>

<h1 class="maco-title maco-title-gradient">
    <i class="fas fa-tachometer-alt"></i>
    Panel de Despacho
</h1>

<p class="maco-subtitle">
    Acceso rápido a las funcionalidades principales del sistema
</p>

<div class="maco-grid maco-grid-2" style="margin-top: 2rem;">
    <div class="maco-card maco-card-hover maco-fade-in">
        <div class="maco-card-icon">
            <i class="fas fa-inbox"></i>
        </div>
        <div class="maco-card-content">
            <h3 class="maco-card-title">Despacho</h3>
            <p class="maco-card-description">
                Control y gestión de despacho de clientes en tiempo real
            </p>
            <a href="../View/Inicio.php" class="maco-btn maco-btn-primary w-100">
                <i class="fas fa-arrow-right me-2"></i>Ingresar
            </a>
        </div>
    </div>

    <div class="maco-card maco-card-hover maco-fade-in" style="animation-delay: 0.1s;">
        <div class="maco-card-icon">
            <i class="fas fa-truck"></i>
        </div>
        <div class="maco-card-content">
            <h3 class="maco-card-title">Creación de Choferes</h3>
            <p class="maco-card-description">
                Registro y gestión de nuevos choferes en el sistema
            </p>
            <a href="../View/Gestion_de_camiones.php" class="maco-btn maco-btn-primary w-100">
                <i class="fas fa-arrow-right me-2"></i>Ingresar
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>
