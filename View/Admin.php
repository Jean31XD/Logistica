<?php
/**
 * Panel de Administración - MACO Design System
 */

// Incluir configuración centralizada de sesión y conexión a BD
require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion([0]); // Solo pantalla 0 (Admin) puede acceder
require_once __DIR__ . '/../conexionBD/conexion.php';

$pageTitle = "Panel de Administración | MACO";
$additionalCSS = <<<'CSS'
<style>
    .admin-container {
        max-width: 1600px;
        margin: 0 auto;
    }

    .admin-hero {
        background: var(--primary);
        padding: 4rem 2rem;
        border-radius: var(--radius-xl);
        margin-bottom: 3rem;
        color: white;
        text-align: center;
        box-shadow: var(--shadow-xl);
        position: relative;
        overflow: hidden;
    }

    .admin-hero::before {
        content: '';
        position: absolute;
        width: 500px;
        height: 500px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        top: -200px;
        right: -200px;
    }

    .admin-hero::after {
        content: '';
        position: absolute;
        width: 300px;
        height: 300px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
        bottom: -150px;
        left: -150px;
    }

    .admin-hero-content {
        position: relative;
        z-index: 2;
    }

    .admin-hero-icon {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        animation: float 3s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-20px); }
    }

    .admin-hero h1 {
        font-size: 3rem;
        font-weight: 800;
        margin-bottom: 1rem;
        text-shadow: 0 2px 20px rgba(0, 0, 0, 0.2);
    }

    .admin-hero p {
        font-size: 1.25rem;
        opacity: 0.95;
        max-width: 600px;
        margin: 0 auto;
    }

    .section-title {
        text-align: center;
        margin: 4rem 0 3rem 0;
    }

    .section-title h2 {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .section-title p {
        font-size: 1.125rem;
        color: var(--text-secondary);
    }

    .module-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 2rem;
        margin-bottom: 4rem;
        max-width: 100%;
        width: 100%;
    }

    .module-card {
        background: white;
        border-radius: var(--radius-xl);
        padding: 2.5rem;
        box-shadow: var(--shadow-lg);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        border: 2px solid transparent;
        position: relative;
        overflow: hidden;
    }

    .module-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: var(--primary);
        transform: scaleX(0);
        transition: transform 0.4s ease;
    }

    .module-card:hover::before {
        transform: scaleX(1);
    }

    .module-card:hover {
        transform: translateY(-10px);
        box-shadow: var(--shadow-xl);
        border-color: var(--primary);
    }

    .module-card-icon {
        width: 80px;
        height: 80px;
        border-radius: var(--radius-xl);
        background: var(--primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 8px 24px rgba(230, 57, 70, 0.3);
        transition: all 0.3s ease;
    }

    .module-card:hover .module-card-icon {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 12px 32px rgba(230, 57, 70, 0.4);
    }

    .module-card h3 {
        font-size: 1.375rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.75rem;
    }

    .module-card p {
        font-size: 0.95rem;
        color: var(--text-secondary);
        margin-bottom: 1.5rem;
        line-height: 1.6;
    }

    .module-card-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 1.25rem;
        border-top: 2px solid var(--gray-100);
    }

    .module-badge {
        padding: 0.375rem 0.875rem;
        background: rgba(230, 57, 70, 0.1);
        color: var(--primary);
        border-radius: var(--radius-full);
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }


    @media (max-width: 768px) {
        .admin-hero h1 {
            font-size: 2rem;
        }

        .admin-hero p {
            font-size: 1rem;
        }

        .module-grid {
            grid-template-columns: 1fr;
        }

        .section-title h2 {
            font-size: 2rem;
        }
    }
</style>
CSS;
include __DIR__ . '/templates/header.php';
?>

<div class="admin-container">
    <!-- Hero Section -->
    <div class="admin-hero">
        <div class="admin-hero-content">
            <div class="admin-hero-icon">
                <i class="fas fa-user-shield"></i>
            </div>
            <h1>Panel de Administración</h1>
            <p>Centro de control total del sistema MACO Logística. Accede a todos los módulos desde aquí.</p>
        </div>
    </div>


    <!-- Módulos del Sistema -->
    <section>
        <div class="section-title">
            <h2><i class="fas fa-th-large me-3" style="color: var(--primary);"></i>Módulos del Sistema</h2>
            <p>Accede rápidamente a cualquier módulo del sistema</p>
        </div>

        <div class="module-grid">
            <?php
                $modules = [
                    [
                        'title' => 'Despacho de Factura',
                        'desc' => 'Gestiona envíos y entregas en tiempo real. Control completo de tickets y asignaciones.',
                        'link' => '../View/Inicio.php',
                        'icon' => 'fa-truck-fast',
                        'badge' => 'Operativo'
                    ],
                    [
                        'title' => 'Validación de Facturas',
                        'desc' => 'Valida y procesa facturas escaneadas. Sistema de verificación automática.',
                        'link' => '../View/facturas.php',
                        'icon' => 'fa-check-double',
                        'badge' => 'Activo'
                    ],
                    [
                        'title' => 'Recepción de Documentos',
                        'desc' => 'Control de recepción de documentos. Registro y seguimiento completo.',
                        'link' => '../View/facturas-recepcion.php',
                        'icon' => 'fa-inbox',
                        'badge' => 'Disponible'
                    ],
                    [
                        'title' => 'Reportes por Transportista',
                        'desc' => 'Reportes detallados y análisis por transportista. Exportación a Excel disponible.',
                        'link' => '../View/Reporte.php',
                        'icon' => 'fa-chart-pie',
                        'badge' => 'Analytics'
                    ],
                    [
                        'title' => 'Business Intelligence',
                        'desc' => 'Dashboard de métricas y KPIs. Análisis avanzado de facturas y operaciones.',
                        'link' => '../View/BI.php',
                        'icon' => 'fa-file-invoice-dollar',
                        'badge' => 'BI'
                    ],
                    [
                        'title' => 'Sistema de Etiquetado',
                        'desc' => 'Gestión completa de etiquetas. Crea, modifica y elimina etiquetas del sistema.',
                        'link' => '../View/Listo-etiquetas.php',
                        'icon' => 'fa-tags',
                        'badge' => 'Gestión'
                    ],
                    [
                        'title' => 'Gestión de Usuarios',
                        'desc' => 'Administración completa de usuarios. Crea, modifica permisos y roles.',
                        'link' => '../View/Gestion_de_usuario.php',
                        'icon' => 'fa-users-cog',
                        'badge' => 'Admin'
                    ],
                    [
                        'title' => 'Dashboard General',
                        'desc' => 'Visión general del sistema. Métricas consolidadas y estadísticas globales.',
                        'link' => '../View/dashboard.php',
                        'icon' => 'fa-tachometer-alt',
                        'badge' => 'Overview'
                    ],
                    [
                        'title' => 'Listo de Inventario',
                        'desc' => 'Inventario de Listo ferreteria.',
                        'link' => '../View/Listo_inventario.php',
                        'icon' => 'fa-warehouse',
                        'badge' => 'Overview'
                    ],
                    [
                        'title' => 'Códigos de Barras',
                        'desc' => 'Escaneo y asignación de códigos de barras a artículos. Control de inventario.',
                        'link' => '../View/Codigos_de_barras.php',
                        'icon' => 'fa-barcode',
                        'badge' => 'Inventario'
                    ],
                    [
                        'title' => 'Códigos de Referencia',
                        'desc' => 'Visualización completa de códigos de barras asignados. Exportación a Excel disponible.',
                        'link' => '../View/Codigos_referencia.php',
                        'icon' => 'fa-list-alt',
                        'badge' => 'Reportes'
                    ],
                    [
                        'title' => 'Gestión de Imágenes',
                        'desc' => 'Administra imágenes de productos en Azure Blob Storage. Sube, visualiza y elimina imágenes.',
                        'link' => '../View/Gestion_imagenes.php',
                        'icon' => 'fa-images',
                        'badge' => 'Azure'
                    ]
                ];

                $delay = 0;
                foreach ($modules as $module):
            ?>
            <div class="module-card">
                <div class="module-card-icon">
                    <i class="fas <?= $module['icon'] ?>"></i>
                </div>
                <h3><?= $module['title'] ?></h3>
                <p><?= $module['desc'] ?></p>
                <div class="module-card-footer">
                    <span class="module-badge"><?= $module['badge'] ?></span>
                    <a href="<?= $module['link'] ?>" class="maco-btn maco-btn-primary maco-btn-sm">
                        Acceder <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
            <?php $delay += 0.05; endforeach; ?>
        </div>
    </section>

   
<?php include __DIR__ . '/templates/footer.php'; ?>
