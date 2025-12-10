<?php
/**
 * Recepción de Facturas - MACO Design System
 */

// Seguridad de sesión
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

date_default_timezone_set('America/Santo_Domingo');

// Cierre por inactividad (200 segundos)
$inactividadLimite = 200;

if (isset($_SESSION['ultimo_acceso'])) {
    $tiempoInactivo = time() - $_SESSION['ultimo_acceso'];
    if ($tiempoInactivo > $inactividadLimite) {
        session_unset();
        session_destroy();
        header("Location: ../index.php");
        exit();
    }
}
$_SESSION['ultimo_acceso'] = time();

if (!isset($_SESSION['usuario'])) {
    header("Location: ../index.php");
    exit();
}

session_regenerate_id(true);

include '../conexionBD/conexion.php';

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit();
}

if (!isset($_SESSION['pantalla']) || !in_array($_SESSION['pantalla'], [0, 2, 3, 5])) {
    header("Location: ../index.php");
    exit();
}

// Cargar transportistas
$query = "SELECT DISTINCT Transportista FROM custinvoicejour WHERE Transportista IS NOT NULL";
$result = sqlsrv_query($conn, $query);
$transportistas = [];
while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
    $transportistas[] = $row['Transportista'];
}

// Cargar usuarios si la pantalla es 0, 2 o 5
$usuarios = [];
if (in_array($_SESSION['pantalla'], [0, 2, 5])) {
    $queryUsuarios = "SELECT DISTINCT Usuario FROM custinvoicejour WHERE Usuario IS NOT NULL";
    $resultUsuarios = sqlsrv_query($conn, $queryUsuarios);
    while ($row = sqlsrv_fetch_array($resultUsuarios, SQLSRV_FETCH_ASSOC)) {
        $usuarios[] = $row['Usuario'];
    }
}

$pageTitle = "Recepción de Facturas | MACO";
$containerClass = "maco-container-fluid"; // Contenedor fluido para este layout especial
$additionalCSS = <<<'CSS'
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Layout especial para pantalla de facturas */
    .facturas-layout {
        display: flex;
        gap: 2rem;
        margin-top: 1rem;
    }

    .facturas-main {
        flex: 1;
        min-width: 0; /* Permite que flex funcione correctamente */
    }

    .facturas-sidebar {
        width: 350px;
        flex-shrink: 0;
    }

    .sidebar-card {
        background: white;
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        box-shadow: var(--shadow);
        position: sticky;
        top: 1rem;
    }

    .sidebar-logo {
        text-align: center;
        margin-bottom: 1.5rem;
    }

    .sidebar-logo img {
        max-width: 180px;
        height: auto;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }

    .form-control, .form-select {
        width: 100%;
        padding: 0.625rem 0.875rem;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        font-size: 0.875rem;
        transition: all 0.2s ease;
    }

    .form-control:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(255, 0, 0, 0.1);
    }

    .input-group {
        display: flex;
        gap: 0.5rem;
    }

    .input-group .form-control {
        flex: 1;
    }

    .input-group .btn {
        flex-shrink: 0;
    }

    .select2-container--default .select2-selection--single {
        height: 38px;
        border: 1px solid var(--border);
        border-radius: var(--radius);
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px;
        padding-left: 12px;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }

    .table-facturas {
        background: white;
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow);
    }

    .table-facturas thead {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
    }

    .table-facturas thead th {
        padding: 1rem;
        font-weight: 600;
        text-align: center;
        border: none;
    }

    .table-facturas tbody td {
        padding: 0.875rem;
        vertical-align: middle;
        text-align: center;
        border-bottom: 1px solid var(--border);
    }

    .table-facturas tbody tr:hover {
        background-color: var(--bg-hover);
    }

    .table-facturas tbody tr.table-success {
        background-color: rgba(25, 135, 84, 0.1) !important;
        border-left: 4px solid var(--success);
    }

    .estado-validar {
        padding: 0.5rem;
        border-radius: var(--radius);
        border: 1px solid var(--border);
        font-size: 0.875rem;
        min-width: 120px;
    }

    @media (max-width: 992px) {
        .facturas-layout {
            flex-direction: column;
        }

        .facturas-sidebar {
            width: 100%;
            order: -1; /* Sidebar primero en móvil */
        }

        .sidebar-card {
            position: static;
        }
    }
</style>
CSS;

include __DIR__ . '/templates/header.php';
?>

<h1 class="maco-title maco-title-gradient">
    <i class="fas fa-file-invoice"></i>
    Recepción de Facturas
</h1>

<p class="maco-subtitle">
    Validación y gestión de facturas recibidas
</p>

<div class="facturas-layout">
    <div class="facturas-main">
        <div id="contenedorFacturas"></div>
        <div id="paginacion" class="mt-3 d-flex justify-content-center"></div>
    </div>

    <aside class="facturas-sidebar">
        <div class="sidebar-card">
            <div class="sidebar-logo">
                <img src="../IMG/LOGO MC - NEGRO.png" alt="Logo MACO">
            </div>

            <div class="form-group">
                <label for="listaTransportistas">Transportista:</label>
                <select id="listaTransportistas" class="form-select">
                    <option value="">-- Todos --</option>
                    <?php foreach ($transportistas as $t): ?>
                        <option value="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="fechaInicio">Desde:</label>
                <input type="date" id="fechaInicio" class="form-control" />
            </div>

            <div class="form-group">
                <label for="fechaFin">Hasta:</label>
                <input type="date" id="fechaFin" class="form-control" />
            </div>

            <div class="form-group">
                <label for="fechaRecibido">Fecha recibido:</label>
                <input type="date" id="fechaRecibido" class="form-control" />
            </div>

            <div class="form-group">
                <label for="fechaRecepcion">Fecha recepción:</label>
                <input type="date" id="fechaRecepcion" class="form-control" />
            </div>

            <div class="form-group">
                <label for="filtroEstatus">Estatus:</label>
                <select id="filtroEstatus" class="form-select">
                    <option value="">-- Todos --</option>
                    <option value="Completada">Completada</option>
                    <option value="RE">RE</option>
                </select>
            </div>

            <div class="form-group">
                <label for="buscarFactura">Buscar Factura:</label>
                <input type="text" id="buscarFactura" class="form-control" placeholder="Ej: 12345678901" maxlength="11" />
            </div>

            <?php if (in_array($_SESSION['pantalla'], [0, 2, 5])): ?>
                <div class="form-group">
                    <label for="filtroUsuario">Usuario:</label>
                    <select id="filtroUsuario" class="form-select">
                        <option value="">-- Todos --</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="inputFactura">Recibir Factura:</label>
                <div class="input-group">
                    <input type="text" id="inputFactura" class="form-control" placeholder="11 dígitos" maxlength="11" />
                    <button class="maco-btn maco-btn-success" onclick="validarFactura()" title="Recibir factura">
                        <i class="fas fa-check"></i>
                    </button>
                </div>
            </div>
        </div>
    </aside>
</div>

<?php
$additionalJS = <<<'JS'
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
let paginaActual = 1;

$(document).ready(function () {
    $('#listaTransportistas').select2({
        placeholder: "Buscar transportista",
        allowClear: true,
        width: '100%'
    });

    $('#listaTransportistas, #fechaInicio, #fechaFin, #fechaRecibido, #fechaRecepcion, #filtroEstatus, #filtroUsuario')
        .on('change', () => cargarFacturas(1));
    $('#buscarFactura').on('input', () => cargarFacturas(1));

    $('#inputFactura').on('input', function () {
        const valor = this.value.trim();
        if (valor.length === 11) {
            validarFactura();
        }
    });

    cargarFacturas();
});

// Temporizador inactividad (cliente)
const tiempoLimite = 5 * 60 * 1000; // 5 minutos en ms
let temporizador;

function resetearTemporizador() {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => {
        alert("Su sesión ha expirado por inactividad. Será redirigido al login.");
        window.location.href = "../Logica/logout.php";
    }, tiempoLimite);
}

['click', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
    document.addEventListener(evt, resetearTemporizador, false);
});
resetearTemporizador();

function cargarFacturas(pagina = 1) {
    paginaActual = pagina;
    const formData = new FormData();
    formData.append('transportista', document.getElementById('listaTransportistas').value);
    formData.append('desde', document.getElementById('fechaInicio').value);
    formData.append('hasta', document.getElementById('fechaFin').value);
    formData.append('fechaRecibido', document.getElementById('fechaRecibido').value);
    formData.append('fechaRecepcion', document.getElementById('fechaRecepcion').value);
    formData.append('estatus', document.getElementById('filtroEstatus').value);
    formData.append('usuario', document.getElementById('filtroUsuario') ? document.getElementById('filtroUsuario').value : '');
    formData.append('buscarFactura', document.getElementById('buscarFactura').value.trim());
    formData.append('pagina', pagina);

    fetch('../Logica/get_facturas.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            document.getElementById('contenedorFacturas').innerHTML =
                `<div class="alert alert-danger text-center">${data.error}</div>`;
            return;
        }
        document.getElementById('contenedorFacturas').innerHTML = data.html;
        document.getElementById('paginacion').innerHTML = data.paginacion;
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('contenedorFacturas').innerHTML =
            '<div class="alert alert-danger text-center">Error al cargar las facturas</div>';
    });
}

function validarFactura() {
    const factura = document.getElementById('inputFactura').value.trim();
    const transportista = document.getElementById('listaTransportistas').value;
    if (!factura || !transportista) {
        alert("Debe seleccionar un transportista e ingresar una factura.");
        return;
    }

    const formData = new FormData();
    formData.append('factura', factura);
    formData.append('transportista', transportista);

    fetch('../Logica/Validar_factura.php', {
        method: 'POST',
        body: formData
    })
    .then(res => {
        if (res.status === 401) {
            alert("Sesión expirada. Por favor, inicie sesión nuevamente.");
            window.location.href = "../View/index.php";
            return;
        }
        return res.json();
    })
    .then(respuesta => {
        if (!respuesta) return;
        if (respuesta.encontrada) {
            const fila = document.getElementById('fila_' + factura);
            if (fila) {
                fila.classList.add('table-success');
                const select = fila.querySelector('.estado-validar');
                const fechaScanner = fila.querySelector('.fecha-scanner');
                if (select) select.value = 'Completada';
                if (fechaScanner) fechaScanner.textContent = respuesta.fecha_scanner || 'Ahora';
            }
            document.getElementById('inputFactura').value = '';
            document.getElementById('inputFactura').focus();
            cargarFacturas(paginaActual);
        } else {
            alert("Factura no encontrada.");
        }
    });
}

function actualizarEstado(factura, nuevoEstado) {
    const formData = new FormData();
    formData.append('factura', factura);
    formData.append('nuevoEstado', nuevoEstado);

    fetch('../Logica/actualizar_estado.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(respuesta => {
        if (respuesta.success) {
            cargarFacturas(paginaActual);
        } else {
            alert("No se pudo actualizar el estado.");
        }
    })
    .catch(error => {
        console.error("Error:", error);
        alert("Error al actualizar estado.");
    });
}

$('#filtroUsuario').on('change', () => cargarFacturas(1));
</script>
JS;

include __DIR__ . '/templates/footer.php';
?>
