<?php
/**
 * Panel de Administración - MACO
 * Versión rediseñada con nuevo sistema de diseño
 */

session_start();
date_default_timezone_set('America/Santo_Domingo');

// Verificar autenticación y permisos
if (!isset($_SESSION['usuario']) || $_SESSION['pantalla'] != 0) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../conexionBD/conexion.php';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verificarCSRF($tokenEnviado, $tokenSesion) {
    return is_string($tokenEnviado) && is_string($tokenSesion) && hash_equals($tokenSesion, $tokenEnviado);
}

// Variables de página
$pageTitle = "Panel de Administración | MACO";
$containerClass = "maco-container";

// Lógica de procesamiento (mantener la original pero mejorada)
$mensajes = ['crear' => '', 'eliminar' => '', 'modificar' => ''];
$alertas = ['crear' => '', 'eliminar' => '', 'modificar' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verificarCSRF($csrf, $_SESSION['csrf_token'])) {
        die("<div class='maco-alert maco-alert-danger'>Error: Token CSRF inválido.</div>");
    }

    switch ($accion) {
        case 'crear':
            $usuario = trim($_POST['usuario'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $pantalla = filter_var($_POST['pantalla'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 0, "max_range" => 9]]);

            if (!$usuario || !$password || $pantalla === false) {
                $mensajes['crear'] = "Todos los campos son obligatorios y válidos.";
                $alertas['crear'] = "warning";
                break;
            }

            if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $usuario)) {
                $mensajes['crear'] = "Usuario inválido (letras, números, guiones bajos, 3-20 caracteres).";
                $alertas['crear'] = "danger";
                break;
            }

            $stmtCheck = sqlsrv_prepare($conn, "SELECT usuario FROM usuarios WHERE usuario = ?", [$usuario]);
            if (!$stmtCheck || !sqlsrv_execute($stmtCheck)) {
                $mensajes['crear'] = "Error al verificar usuario.";
                $alertas['crear'] = "danger";
            } elseif (sqlsrv_fetch($stmtCheck)) {
                $mensajes['crear'] = "El usuario ya existe.";
                $alertas['crear'] = "danger";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmtInsert = sqlsrv_prepare($conn, "INSERT INTO usuarios (usuario, password, pantalla) VALUES (?, ?, ?)", [$usuario, $hash, $pantalla]);
                if ($stmtInsert && sqlsrv_execute($stmtInsert)) {
                    $mensajes['crear'] = "Usuario <strong>$usuario</strong> creado exitosamente.";
                    $alertas['crear'] = "success";
                } else {
                    $mensajes['crear'] = "Error al crear usuario.";
                    $alertas['crear'] = "danger";
                }
            }
            break;

        case 'eliminar':
            $usuarioEliminar = trim($_POST['usuario_eliminar'] ?? '');
            if ($usuarioEliminar === $_SESSION['usuario']) {
                $mensajes['eliminar'] = "No puede eliminar su propio usuario.";
                $alertas['eliminar'] = "danger";
            } elseif (!$usuarioEliminar) {
                $mensajes['eliminar'] = "Especifique el usuario a eliminar.";
                $alertas['eliminar'] = "warning";
            } else {
                $stmtDelete = sqlsrv_prepare($conn, "DELETE FROM usuarios WHERE usuario = ?", [$usuarioEliminar]);
                if ($stmtDelete && sqlsrv_execute($stmtDelete) && sqlsrv_rows_affected($stmtDelete) > 0) {
                    $mensajes['eliminar'] = "Usuario <strong>$usuarioEliminar</strong> eliminado.";
                    $alertas['eliminar'] = "success";
                } else {
                    $mensajes['eliminar'] = "Error al eliminar o el usuario no existe.";
                    $alertas['eliminar'] = "danger";
                }
            }
            break;

        case 'modificar':
            $usuarioMod = trim($_POST['usuario_modificar'] ?? '');
            $nuevaClave = trim($_POST['password_nuevo'] ?? '');
            $pantallaNuevaInput = $_POST['pantalla_nuevo'] ?? '-1';
            $pantallaNueva = ($pantallaNuevaInput !== '-1') ? filter_var($pantallaNuevaInput, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0, "max_range" => 9]]) : false;

            if (!$usuarioMod) {
                $mensajes['modificar'] = "Especifique el usuario a modificar.";
                $alertas['modificar'] = "warning";
                break;
            }

            $updates = [];
            $params = [];

            if ($nuevaClave) {
                $updates[] = "password = ?";
                $params[] = password_hash($nuevaClave, PASSWORD_DEFAULT);
            }

            if ($pantallaNueva !== false) {
                $updates[] = "pantalla = ?";
                $params[] = $pantallaNueva;
            }

            if (!$updates) {
                $mensajes['modificar'] = "No se ingresaron cambios para modificar.";
                $alertas['modificar'] = "warning";
                break;
            }

            $params[] = $usuarioMod;
            $sql = "UPDATE usuarios SET " . implode(", ", $updates) . " WHERE usuario = ?";
            $stmtUpdate = sqlsrv_prepare($conn, $sql, $params);

            if ($stmtUpdate && sqlsrv_execute($stmtUpdate)) {
                if(sqlsrv_rows_affected($stmtUpdate) > 0) {
                    $mensajes['modificar'] = "Usuario <strong>$usuarioMod</strong> modificado.";
                    $alertas['modificar'] = "success";
                } else {
                    $mensajes['modificar'] = "El usuario no existe o no se aplicaron cambios.";
                    $alertas['modificar'] = "info";
                }
            } else {
                $mensajes['modificar'] = "Error al modificar usuario.";
                $alertas['modificar'] = "danger";
            }
            break;
    }
}

// Accesos rápidos
$accesos = [
    ['title' => 'Despacho de Factura', 'desc' => 'Gestiona los envíos y entregas', 'link' => 'Inicio.php', 'icon' => 'fa-truck-fast', 'color' => 'primary'],
    ['title' => 'Validación', 'desc' => 'Valida facturas escaneadas', 'link' => 'facturas.php', 'icon' => 'fa-check-double', 'color' => 'success'],
    ['title' => 'Recepción', 'desc' => 'Control de recepción de documentos', 'link' => 'facturas-recepcion.php', 'icon' => 'fa-inbox', 'color' => 'info'],
    ['title' => 'Reporte de Facturas', 'desc' => 'Reporte por Transportista', 'link' => 'Reporte.php', 'icon' => 'fa-chart-pie', 'color' => 'warning'],
    ['title' => 'Reporte CXC', 'desc' => 'Facturas faltantes', 'link' => 'BI.php', 'icon' => 'fa-file-invoice-dollar', 'color' => 'danger'],
    ['title' => 'Etiquetado', 'desc' => 'Gestión de etiquetas', 'link' => 'Listo-etiquetas.php', 'icon' => 'fa-tags', 'color' => 'secondary'],
    ['title' => 'Gestión de Usuarios', 'desc' => 'Administrar usuarios del sistema', 'link' => 'Gestion_de_usuario.php', 'icon' => 'fa-users-cog', 'color' => 'primary'],
    ['title' => 'Dashboard', 'desc' => 'Visión general del sistema', 'link' => 'dashboard.php', 'icon' => 'fa-tachometer-alt', 'color' => 'info']
];

// Incluir header
include __DIR__ . '/templates/header.php';
?>

<!-- Título Principal -->
<h1 class="maco-title maco-title-gradient maco-fade-in">
    <i class="fas fa-cog me-3"></i>Panel de Administración
</h1>

<!-- Sección: Accesos Rápidos -->
<section class="mb-5">
    <h2 class="maco-subtitle mb-4">
        <i class="fas fa-bolt me-2"></i>Accesos Rápidos
    </h2>

    <div class="maco-grid maco-grid-4">
        <?php foreach ($accesos as $index => $acceso): ?>
        <a href="<?= $acceso['link'] ?>" class="text-decoration-none">
            <div class="maco-card" style="animation-delay: <?= $index * 0.05 ?>s;">
                <div class="text-center">
                    <div class="mb-3" style="font-size: 2.5rem; color: var(--<?= $acceso['color'] ?>);">
                        <i class="fas <?= $acceso['icon'] ?>"></i>
                    </div>
                    <h3 class="h6 fw-bold mb-2"><?= $acceso['title'] ?></h3>
                    <p class="text-muted small mb-3"><?= $acceso['desc'] ?></p>
                    <span class="maco-btn maco-btn-sm maco-btn-primary w-100">
                        <i class="fas fa-arrow-right"></i> Ingresar
                    </span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Sección: Gestión de Usuarios -->
<section>
    <h2 class="maco-subtitle mb-4">
        <i class="fas fa-users me-2"></i>Gestión de Usuarios
    </h2>

    <div class="maco-grid maco-grid-3">
        <!-- Card: Crear Usuario -->
        <div class="maco-card">
            <div class="maco-card-header">
                <h3 class="maco-card-title">
                    <i class="fas fa-user-plus text-success"></i>
                    Crear Usuario
                </h3>
            </div>

            <?php if ($mensajes['crear']): ?>
            <div class="maco-alert maco-alert-<?= $alertas['crear'] ?>">
                <i class="fas fa-<?= $alertas['crear'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                <div><?= $mensajes['crear'] ?></div>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="accion" value="crear">

                <div class="maco-form-group">
                    <label class="maco-label">Usuario</label>
                    <input type="text" name="usuario" class="maco-input" placeholder="Ej: jdoe" required maxlength="20" pattern="[a-zA-Z0-9_]{3,20}">
                    <small class="text-muted">3-20 caracteres (letras, números, guiones bajos)</small>
                </div>

                <div class="maco-form-group">
                    <label class="maco-label">Contraseña</label>
                    <input type="password" name="password" class="maco-input" placeholder="Mínimo 8 caracteres" required>
                </div>

                <div class="maco-form-group">
                    <label class="maco-label">Rol/Pantalla</label>
                    <select name="pantalla" class="maco-select" required>
                        <option value="">-- Seleccionar --</option>
                        <option value="0">0 - Administrador</option>
                        <option value="1">1 - Gestión</option>
                        <option value="2">2 - Facturas</option>
                        <option value="3">3 - CXC</option>
                        <option value="4">4 - Reportes</option>
                        <option value="5">5 - Panel Admin</option>
                        <option value="6">6 - BI</option>
                        <option value="8">8 - Etiquetas</option>
                        <option value="9">9 - Dashboard</option>
                    </select>
                </div>

                <button type="submit" class="maco-btn maco-btn-primary w-100">
                    <i class="fas fa-plus"></i> Crear Usuario
                </button>
            </form>
        </div>

        <!-- Card: Eliminar Usuario -->
        <div class="maco-card">
            <div class="maco-card-header">
                <h3 class="maco-card-title">
                    <i class="fas fa-user-minus text-danger"></i>
                    Eliminar Usuario
                </h3>
            </div>

            <?php if ($mensajes['eliminar']): ?>
            <div class="maco-alert maco-alert-<?= $alertas['eliminar'] ?>">
                <i class="fas fa-<?= $alertas['eliminar'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                <div><?= $mensajes['eliminar'] ?></div>
            </div>
            <?php endif; ?>

            <form method="POST" action="" onsubmit="return confirm('¿Está seguro de eliminar este usuario?');">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="accion" value="eliminar">

                <div class="maco-form-group">
                    <label class="maco-label">Usuario a eliminar</label>
                    <input type="text" name="usuario_eliminar" class="maco-input" placeholder="Nombre de usuario" required>
                    <small class="text-danger">
                        <i class="fas fa-exclamation-triangle"></i> Esta acción no se puede deshacer
                    </small>
                </div>

                <button type="submit" class="maco-btn maco-btn-outline w-100" style="border-color: var(--danger); color: var(--danger);">
                    <i class="fas fa-trash"></i> Eliminar Usuario
                </button>
            </form>
        </div>

        <!-- Card: Modificar Usuario -->
        <div class="maco-card">
            <div class="maco-card-header">
                <h3 class="maco-card-title">
                    <i class="fas fa-user-edit text-warning"></i>
                    Modificar Usuario
                </h3>
            </div>

            <?php if ($mensajes['modificar']): ?>
            <div class="maco-alert maco-alert-<?= $alertas['modificar'] ?>">
                <i class="fas fa-<?= $alertas['modificar'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                <div><?= $mensajes['modificar'] ?></div>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="accion" value="modificar">

                <div class="maco-form-group">
                    <label class="maco-label">Usuario a modificar</label>
                    <input type="text" name="usuario_modificar" class="maco-input" placeholder="Nombre de usuario" required>
                </div>

                <div class="maco-form-group">
                    <label class="maco-label">Nueva contraseña (opcional)</label>
                    <input type="password" name="password_nuevo" class="maco-input" placeholder="Dejar vacío si no cambia">
                </div>

                <div class="maco-form-group">
                    <label class="maco-label">Nuevo rol (opcional)</label>
                    <select name="pantalla_nuevo" class="maco-select">
                        <option value="-1">-- Sin cambio --</option>
                        <option value="0">0 - Administrador</option>
                        <option value="1">1 - Gestión</option>
                        <option value="2">2 - Facturas</option>
                        <option value="3">3 - CXC</option>
                        <option value="4">4 - Reportes</option>
                        <option value="5">5 - Panel Admin</option>
                        <option value="6">6 - BI</option>
                        <option value="8">8 - Etiquetas</option>
                        <option value="9">9 - Dashboard</option>
                    </select>
                </div>

                <button type="submit" class="maco-btn maco-btn-primary w-100">
                    <i class="fas fa-save"></i> Modificar Usuario
                </button>
            </form>
        </div>
    </div>
</section>

<?php
// Scripts adicionales
$additionalJS = <<<'JS'
<script>
    // Auto-cerrar alertas después de 5 segundos
    document.querySelectorAll('.maco-alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // Validación en tiempo real del campo usuario
    const usuarioInput = document.querySelector('input[name="usuario"]');
    if (usuarioInput) {
        usuarioInput.addEventListener('input', function() {
            const valid = /^[a-zA-Z0-9_]{3,20}$/.test(this.value);
            this.style.borderColor = valid || this.value.length === 0 ? 'var(--gray-200)' : 'var(--danger)';
        });
    }
</script>
JS;

// Incluir footer
include __DIR__ . '/templates/footer.php';
?>
