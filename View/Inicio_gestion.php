<?php
/**
 * Panel de Despacho - MACO Design System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Santo_Domingo');

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Verificar autenticación y permisos
if (!isset($_SESSION['usuario'], $_SESSION['pantalla']) || $_SESSION['pantalla'] != 1) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../conexionBD/conexion.php';

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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
