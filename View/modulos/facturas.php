<?php
/**
 * Validación de Facturas
 */

// Incluir configuración centralizada de sesión
require_once __DIR__ . '/../../conexionBD/session_config.php';

// Verificar autenticación y permisos (pantallas: 0=Admin, 2=Facturas, 3=CXC, 5=PanelAdmin)
verificarAutenticacion([0, 2, 3, 5]);

// Generar token CSRF
$csrfToken = generarTokenCSRF();
// Incluir conexión a BD
require_once __DIR__ . '/../../conexionBD/conexion.php';

// Cargar transportistas
$query = "SELECT DISTINCT Transportista FROM custinvoicejour WHERE Transportista IS NOT NULL";
$result = sqlsrv_query($conn, $query);
$transportistas = [];
while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
    $transportistas[] = $row['Transportista'];
}

// Cargar usuarios si la pantalla es 0, 2 o 5
$usuarios = [];
if (in_array($_SESSION['pantalla'], [0, 2, 3, 5])) {
    $queryUsuarios = "SELECT DISTINCT Usuario FROM custinvoicejour WHERE Usuario IS NOT NULL";
    $resultUsuarios = sqlsrv_query($conn, $queryUsuarios);
    while ($row = sqlsrv_fetch_array($resultUsuarios, SQLSRV_FETCH_ASSOC)) {
        $usuarios[] = $row['Usuario'];
    }
}

$pageTitle = "Validación de Facturas | MACO";
$containerClass = "maco-container-fluid";
$additionalCSS = <<<'CSS'
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --bi-primary: #E63946;
        --bi-secondary: #1D3557;
        --bi-accent: #457B9D;
        --bi-success: #22C55E;
        --bi-warning: #F59E0B;
        --bi-bg: #F1F5F9;
        --bi-card: #FFFFFF;
        --bi-border: #E2E8F0;
        --bi-text: #1E293B;
        --bi-muted: #64748B;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: var(--bi-bg);
    }

    /* Header del reporte */
    .bi-header {
        background: linear-gradient(135deg, var(--bi-secondary) 0%, var(--bi-accent) 100%);
        color: #fff;
        padding: 1.5rem 2rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .bi-header h1 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .bi-header p {
        margin: 0.25rem 0 0;
        opacity: 0.85;
        font-size: 0.9rem;
    }

    .bi-header-stats {
        display: flex;
        gap: 1.5rem;
    }

    .bi-stat-box {
        text-align: center;
        background: rgba(255,255,255,0.15);
        padding: 0.75rem 1.25rem;
        border-radius: 8px;
    }

    .bi-stat-box .number {
        font-size: 1.5rem;
        font-weight: 800;
        display: block;
    }

    .bi-stat-box .label {
        font-size: 0.7rem;
        text-transform: uppercase;
        opacity: 0.8;
    }

    /* Filtros en topbar */
    .bi-filters {
        background: var(--bi-card);
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 1.5rem;
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: flex-end;
    }

    .bi-filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        min-width: 140px;
    }

    .bi-filter-group label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--bi-muted);
        text-transform: uppercase;
    }

    .bi-filter-group input,
    .bi-filter-group select {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--bi-border);
        border-radius: 6px;
        font-size: 0.85rem;
        background: #fff;
    }

    .bi-filter-group input:focus,
    .bi-filter-group select:focus {
        outline: none;
        border-color: var(--bi-primary);
        box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
    }

    .bi-filter-actions {
        display: flex;
        gap: 0.5rem;
        margin-left: auto;
    }

    .bi-btn {
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s;
    }

    .bi-btn-primary {
        background: var(--bi-primary);
        color: #fff;
    }

    .bi-btn-primary:hover {
        background: #c53030;
    }

    .bi-btn-success {
        background: var(--bi-success);
        color: #fff;
    }

    /* Tabla moderna */
    .bi-table-container {
        background: var(--bi-card);
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .table-facturas {
        width: 100%;
        border-collapse: collapse;
    }

    .table-facturas thead {
        background: var(--bi-secondary);
        color: #fff;
    }

    .table-facturas thead th {
        padding: 1rem;
        font-weight: 600;
        text-align: center;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table-facturas tbody td {
        padding: 0.875rem;
        text-align: center;
        border-bottom: 1px solid var(--bi-border);
        font-size: 0.85rem;
    }

    .table-facturas tbody tr:hover {
        background: #F8FAFC;
    }

    .table-facturas tbody tr.table-success {
        background: rgba(34, 197, 94, 0.1) !important;
        border-left: 4px solid var(--bi-success);
    }

    /* Select2 estilos */
    .select2-container--default .select2-selection--single {
        height: 36px;
        border: 1px solid var(--bi-border);
        border-radius: 6px;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 34px;
        font-size: 0.85rem;
    }

    /* Paginación */
    #paginacion {
        padding: 1rem;
        display: flex;
        justify-content: center;
    }

    /* Input grupo para recibir factura */
    .bi-receive-box {
        background: linear-gradient(135deg, var(--bi-success) 0%, #16A34A 100%);
        padding: 1rem 1.5rem;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .bi-receive-box label {
        color: #fff;
        font-weight: 600;
        white-space: nowrap;
    }

    .bi-receive-box input {
        flex: 1;
        max-width: 200px;
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 6px;
        font-size: 0.9rem;
    }

    .bi-receive-box button {
        background: #fff;
        color: var(--bi-success);
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
    }
</style>
CSS;

include __DIR__ . '/../templates/header.php';
?>

<!-- Header del reporte -->
<div class="bi-header">
    <div>
        <h1><i class="fas fa-check-circle"></i> Validación de Facturas</h1>
        <p>Valida y procesa facturas escaneadas</p>
    </div>
    <div class="bi-header-stats">
        <div class="bi-stat-box">
            <span class="number" id="kpi-total">--</span>
            <span class="label">Total</span>
        </div>
        <div class="bi-stat-box">
            <span class="number" id="kpi-completadas">--</span>
            <span class="label">Completadas</span>
        </div>
        <div class="bi-stat-box">
            <span class="number" id="kpi-pendientes">--</span>
            <span class="label">Pendientes</span>
        </div>
    </div>
</div>

<!-- Caja para recibir facturas -->
<div class="bi-receive-box">
    <label><i class="fas fa-barcode"></i> Recibir Factura:</label>
    <input type="text" id="inputFactura" placeholder="Ingrese 11 dígitos" maxlength="11" />
    <button onclick="validarFactura()"><i class="fas fa-check"></i> Validar</button>
</div>

<!-- Filtros horizontales -->
<div class="bi-filters">
    <div class="bi-filter-group" style="min-width: 200px;">
        <label>Transportista</label>
        <select id="listaTransportistas">
            <option value="">-- Todos --</option>
            <?php foreach ($transportistas as $t): ?>
                <option value="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="bi-filter-group">
        <label>Desde</label>
        <input type="date" id="fechaInicio" />
    </div>
    
    <div class="bi-filter-group">
        <label>Hasta</label>
        <input type="date" id="fechaFin" />
    </div>
    
    <div class="bi-filter-group">
        <label>Fecha Recibido</label>
        <input type="date" id="fechaRecibido" />
    </div>
    
    <div class="bi-filter-group">
        <label>Fecha Recepción</label>
        <input type="date" id="fechaRecepcion" />
    </div>
    
    <div class="bi-filter-group">
        <label>Estatus</label>
        <select id="filtroEstatus">
            <option value="">-- Todos --</option>
            <option value="Completada">Completada</option>
            <option value="RE">RE</option>
        </select>
    </div>
    
    <?php if (in_array($_SESSION['pantalla'], [0, 2, 3, 5])): ?>
    <div class="bi-filter-group">
        <label>Usuario</label>
        <select id="filtroUsuario">
            <option value="">-- Todos --</option>
            <?php foreach ($usuarios as $u): ?>
                <option value="<?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    
    <div class="bi-filter-group">
        <label>Buscar Factura</label>
        <input type="text" id="buscarFactura" placeholder="Ej: 12345678901" maxlength="11" />
    </div>
</div>

<!-- Tabla de resultados -->
<div class="bi-table-container">
    <div id="contenedorFacturas"></div>
    <div id="paginacion"></div>
</div>

<?php
// Inyectar token CSRF como variable JS
echo "<script>const CSRF_TOKEN = '{$csrfToken}';</script>";

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
        window.location.href = "../../Logica/logout.php";
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

    fetch('../../Logica/get_facturas.php', {
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
        
        // Actualizar KPIs
        document.getElementById('kpi-total').textContent = data.kpiTotal || 0;
        document.getElementById('kpi-completadas').textContent = data.kpiCompletadas || 0;
        document.getElementById('kpi-pendientes').textContent = data.kpiPendientes || 0;
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
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('../../Logica/Validar_factura.php', {
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

    fetch('../../Logica/actualizar_estado.php', {
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

include __DIR__ . '/../templates/footer.php';
?>
