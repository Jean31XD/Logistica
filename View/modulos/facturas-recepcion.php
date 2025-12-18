<?php
require_once __DIR__ . '/../../conexionBD/session_config.php';
verificarAutenticacion([0, 3, 5]); // Solo pantallas 0, 3, 5
require_once __DIR__ . '/../../conexionBD/conexion.php';

$csrfToken = generarTokenCSRF();

// Consultar transportistas
$query = "SELECT DISTINCT Transportista FROM custinvoicejour WHERE Transportista IS NOT NULL";
$result = sqlsrv_query($conn, $query);
$transportistas = [];
while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
    $transportistas[] = $row['Transportista'];
}

$pageTitle = "Recepción de Documentos | MACO";
$containerClass = "maco-container-fluid";
$additionalCSS = <<<'CSS'
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Layout especial para pantalla de recepción */
    .facturas-layout {
        display: flex;
        gap: 2rem;
        margin-top: 1rem;
    }

    .facturas-main {
        flex: 1;
        min-width: 0;
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
        border: 1px solid var(--border);
    }

    .sidebar-logo {
        text-align: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid var(--gray-200);
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
        box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
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
        border: 1px solid var(--border);
    }

    .table-facturas thead {
        background: var(--primary);
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
        background-color: rgba(16, 185, 129, 0.1) !important;
        border-left: 4px solid var(--success);
    }

    @media (max-width: 992px) {
        .facturas-layout {
            flex-direction: column;
        }

        .facturas-sidebar {
            width: 100%;
            order: -1;
        }

        .sidebar-card {
            position: static;
        }
    }
</style>
CSS;

include __DIR__ . '/../templates/header.php';
?>

<h1 class="maco-title">
    <i class="fas fa-inbox"></i>
    Recepción de Documentos
</h1>

<p class="maco-subtitle">
    Control de recepción y validación de documentos
</p>

<div class="facturas-layout">
    <div class="facturas-main">
        <div id="contenedorFacturas"></div>
        <div id="paginacion" class="mt-3 d-flex justify-content-center"></div>
    </div>

    <aside class="facturas-sidebar">
        <div class="sidebar-card">
            <div class="sidebar-logo">
                <img src="../../IMG/LOGO MC - NEGRO.png" alt="Logo MACO">
            </div>

            <div class="form-group">
                <label for="listaTransportistas"><i class="fas fa-truck me-2"></i>Transportista:</label>
                <select id="listaTransportistas" class="form-select">
                    <option value="">-- Todos --</option>
                    <?php foreach ($transportistas as $t): ?>
                        <option value="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="fechaInicio"><i class="fas fa-calendar me-2"></i>Desde:</label>
                <input type="date" id="fechaInicio" class="form-control" />
            </div>

            <div class="form-group">
                <label for="fechaFin"><i class="fas fa-calendar me-2"></i>Hasta:</label>
                <input type="date" id="fechaFin" class="form-control" />
            </div>

            <div class="form-group">
                <label for="fechaRecibido"><i class="fas fa-calendar-check me-2"></i>Fecha recibido:</label>
                <input type="date" id="fechaRecibido" class="form-control" />
            </div>

            <div class="form-group">
                <label for="fechaRecepcion"><i class="fas fa-calendar-check me-2"></i>Fecha recepción:</label>
                <input type="date" id="fechaRecepcion" class="form-control" />
            </div>

            <div class="form-group">
                <label for="filtroEstatus"><i class="fas fa-info-circle me-2"></i>Estatus:</label>
                <select id="filtroEstatus" class="form-select">
                    <option value="">-- Todos --</option>
                    <option value="Completada">Completada</option>
                    <option value="RE">RE</option>
                </select>
            </div>

            <div class="form-group">
                <label for="buscarFactura"><i class="fas fa-search me-2"></i>Buscar Factura:</label>
                <input type="text" id="buscarFactura" class="form-control" placeholder="Ej: 12345678901" maxlength="11" />
            </div>

            <div class="form-group">
                <label for="inputFactura"><i class="fas fa-file-invoice me-2"></i>Nº Factura:</label>
                <div class="input-group">
                    <input type="text" id="inputFactura" class="form-control" placeholder="11 dígitos" maxlength="11" />
                    <button class="btn btn-success" onclick="validarFactura()" title="Recibir factura">
                        <i class="bi bi-box-arrow-in-down"></i>
                    </button>
                </div>
            </div>

            <div class="maco-divider"></div>

            <a href="../../Logica/logout.php" class="btn btn-danger w-100">
                <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
            </a>
        </div>
    </aside>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
let paginaActual = 1;
let temporizador;
const csrfToken = '<?= $csrfToken ?>';

$(document).ready(function () {
    $('#listaTransportistas').select2({
        placeholder: "Buscar transportista",
        allowClear: true,
        width: '100%'
    });

    $('#listaTransportistas, #fechaInicio, #fechaFin, #fechaRecibido, #fechaRecepcion, #filtroEstatus').on('change', () => cargarFacturas(1));
    $('#buscarFactura').on('input', () => cargarFacturas(1));

    $('#inputFactura').on('input', function () {
        const valor = this.value.trim();
        if (valor.length === 11) {
            validarFactura();
        }
    });

    cargarFacturas();
    iniciarInactividad();
});

function cargarFacturas(pagina = 1) {
    paginaActual = pagina;
    const formData = new FormData();
    formData.append('transportista', document.getElementById('listaTransportistas').value);
    formData.append('desde', document.getElementById('fechaInicio').value);
    formData.append('hasta', document.getElementById('fechaFin').value);
    formData.append('fechaRecibido', document.getElementById('fechaRecibido').value);
    formData.append('fechaRecepcion', document.getElementById('fechaRecepcion').value);
    formData.append('estatus', document.getElementById('filtroEstatus').value);
    formData.append('buscarFactura', document.getElementById('buscarFactura').value.trim());
    formData.append('pagina', pagina);

    fetch('../../Logica/get_facturas.php', {
        method: 'POST',
        body: formData,
        cache: 'no-cache'
    })
    .then(res => {
        if (!res.ok) {
            if (res.status === 401) {
                alert('Tu sesión ha expirado. Serás redirigido al inicio de sesión.');
                window.location.href = '../index.php';
                return;
            }
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        return res.json();
    })
    .then(data => {
        if (!data) return; // En caso de redirección

        const container = document.getElementById('contenedorFacturas');
        const paginacionDiv = document.getElementById('paginacion');

        if (data.error) {
            if (data.redirect) {
                alert(data.error);
                window.location.href = '../index.php';
                return;
            }
            container.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
            paginacionDiv.innerHTML = '';
            return;
        }

        container.innerHTML = data.html;
        paginacionDiv.innerHTML = data.paginacion;
    })
    .catch(err => {
        console.error('Error:', err);
        document.getElementById('contenedorFacturas').innerHTML =
            '<div class="alert alert-danger">Error al cargar facturas. Por favor, recarga la página.</div>';
    });
}

function validarFactura() {
    const numeroFactura = document.getElementById('inputFactura').value.trim();

    if (numeroFactura.length !== 11) {
        alert('El número de factura debe tener exactamente 11 dígitos.');
        return;
    }

    const formData = new URLSearchParams();
    formData.append('numeroFactura', numeroFactura);
    formData.append('csrf_token', csrfToken);

    fetch('../../Logica/validar_factura_recepcion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: formData,
        cache: 'no-cache'
    })
    .then(res => {
        if (!res.ok) {
            if (res.status === 401) {
                alert('Tu sesión ha expirado. Serás redirigido al inicio de sesión.');
                window.location.href = '../index.php';
                return;
            }
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        return res.json();
    })
    .then(data => {
        if (!data) return; // En caso de redirección

        if (data.redirect) {
            alert(data.message || 'Tu sesión ha expirado.');
            window.location.href = '../index.php';
            return;
        }

        if (data.success) {
            document.getElementById('inputFactura').value = '';
            cargarFacturas(paginaActual);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error al procesar la factura.');
    });
}

function iniciarInactividad() {
    let tiempoInactividad;
    const TIEMPO_LIMITE = 200000;

    function resetearTemporizador() {
        clearTimeout(tiempoInactividad);
        tiempoInactividad = setTimeout(() => {
            alert('Tu sesión ha expirado por inactividad.');
            window.location.href = '../../Logica/logout.php';
        }, TIEMPO_LIMITE);
    }

    ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
        document.addEventListener(event, resetearTemporizador, true);
    });

    resetearTemporizador();
}
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
