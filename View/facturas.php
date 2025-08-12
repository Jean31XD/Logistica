<?php 
// Seguridad de sesión
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();
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
    header("Location: ../View/index.php");
    exit();
}
session_regenerate_id(true);

include '../conexionBD/conexion.php';

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['pantalla']) || !in_array($_SESSION['pantalla'], [0, 2, 3, 5])) {
    header("Location: ../index.php");
    exit();
}

// Cargar datos para los filtros
$transportistas = [];
$t_result = sqlsrv_query($conn, "SELECT DISTINCT Transportista FROM custinvoicejour WHERE Transportista IS NOT NULL ORDER BY Transportista");
while ($row = sqlsrv_fetch_array($t_result, SQLSRV_FETCH_ASSOC)) $transportistas[] = $row['Transportista'];

$usuarios = [];
if (in_array($_SESSION['pantalla'], [0, 2, 5])) {
    $u_result = sqlsrv_query($conn, "SELECT DISTINCT Usuario FROM custinvoicejour WHERE Usuario IS NOT NULL ORDER BY Usuario");
    while ($row = sqlsrv_fetch_array($u_result, SQLSRV_FETCH_ASSOC)) $usuarios[] = $row['Usuario'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibir Facturas ✨</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet" />
    <style>
        :root {
            --brand-color: #e31f25;
            --brand-dark: #8B0000;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --text-light: #f8f9fa;
            --text-dark: #343a40;
        }

        body {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand-color));
            background-attachment: fixed;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-light);
            padding-top: 80px; /* Space for fixed header */
        }
        
        .page-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 2rem;
            background: rgba(0,0,0,0.3);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--glass-border);
        }
        .page-header img { height: 40px; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-welcome { font-weight: 500; }

        .main-panel {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
        }

        .form-label { font-weight: 600; }
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid #ccc;
            border-radius: 10px;
            color: var(--text-dark);
        }
        .form-control::placeholder { color: #6c757d; }

        .select2-container--default .select2-selection--single {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            height: 38px;
            padding: 5px;
            border: 1px solid #ccc;
        }
        .select2-dropdown { border-radius: 10px; }

        .btn-action {
            background-color: var(--brand-color);
            border-color: var(--brand-color);
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .btn-action:hover {
            background-color: #b71c1c;
            border-color: #b71c1c;
        }

        .accordion-item {
            background-color: transparent;
            border: none;
        }
        .accordion-button {
            background-color: rgba(0,0,0,0.2);
            color: var(--text-light);
            font-weight: 600;
            border-radius: 0.5rem !important;
        }
        .accordion-button:not(.collapsed) {
            background-color: rgba(0,0,0,0.3);
        }
        .accordion-button:focus { box-shadow: 0 0 0 0.25rem rgba(227, 31, 37, 0.5); }
        .accordion-button::after {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        .accordion-body { padding-top: 1.5rem; }

        .table-container {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 0 12px rgba(0,0,0,0.15);
        }
        .table {
            background: rgba(255, 255, 255, 0.95);
            color: var(--text-dark);
        }
        .table th {
            background-color: var(--brand-color);
            color: #fff;
            font-weight: bold;
        }
        .table td, .table th {
            text-align: center;
            vertical-align: middle;
        }
        .table-success { background-color: #d1e7dd !important; font-weight: 500; }
        
    </style>
</head>
<body>

<header class="page-header">
    <img src="../IMG/LOGO MC - BLANCO.png" alt="Logo">
    <div class="user-info">
        <span class="user-welcome">Bienvenido, <?= htmlspecialchars($_SESSION['usuario']) ?></span>
        <a href="../Logica/logout.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a>
    </div>
</header>

<main class="container-fluid mt-4">
    <section class="main-panel">
        <h5 class="mb-3">Recibir Factura</h5>
        <div class="row g-3 align-items-end mb-4">
            <div class="col-md-5">
                <label for="listaTransportistas" class="form-label">1. Seleccione Transportista:</label>
                <select id="listaTransportistas" class="form-control">
                    <option value="" disabled selected>-- Elija una opción --</option>
                    <?php foreach ($transportistas as $t): ?>
                        <option value="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label for="inputFactura" class="form-label">2. Escanee o digite Nº Factura (11 dígitos):</label>
                <input type="text" id="inputFactura" class="form-control" placeholder="Número de factura" maxlength="11" />
            </div>
            <div class="col-md-2">
                <button class="btn btn-action w-100" onclick="validarFactura()" title="Recibir factura">
                    <i class="bi bi-box-arrow-in-down me-2"></i>Recibir
                </button>
            </div>
        </div>

        <div class="accordion" id="accordionFilters">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFilters">
                        <i class="bi bi-funnel-fill me-2"></i> Filtros de Búsqueda Avanzada
                    </button>
                </h2>
                <div id="collapseFilters" class="accordion-collapse collapse" data-bs-parent="#accordionFilters">
                    <div class="accordion-body">
                        <div class="row g-3">
                            <div class="col-md-4 col-lg-3"><label for="buscarFactura" class="form-label">Buscar Factura:</label><input type="text" id="buscarFactura" class="form-control" placeholder="Ej: 12345678901" maxlength="11" /></div>
                            <div class="col-md-4 col-lg-3"><label for="fechaInicio" class="form-label">Desde:</label><input type="date" id="fechaInicio" class="form-control" /></div>
                            <div class="col-md-4 col-lg-3"><label for="fechaFin" class="form-label">Hasta:</label><input type="date" id="fechaFin" class="form-control" /></div>
                            <div class="col-md-4 col-lg-3"><label for="filtroEstatus" class="form-label">Estatus:</label><select id="filtroEstatus" class="form-select"><option value="">-- Todos --</option><option value="Completada">Completada</option><option value="RE">RE</option></select></div>
                            <div class="col-md-4 col-lg-3"><label for="fechaRecibido" class="form-label">Fecha Recibido:</label><input type="date" id="fechaRecibido" class="form-control" /></div>
                            <div class="col-md-4 col-lg-3"><label for="fechaRecepcion" class="form-label">Fecha Recepción:</label><input type="date" id="fechaRecepcion" class="form-control" /></div>
                            <?php if (in_array($_SESSION['pantalla'], [0, 2, 5])): ?>
                            <div class="col-md-4 col-lg-3"><label for="filtroUsuario" class="form-label">Usuario:</label><select id="filtroUsuario" class="form-select"><option value="">-- Todos --</option><?php foreach ($usuarios as $u): ?><option value="<?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="main-panel">
        <div id="contenedorFacturas">
            </div>
    </section>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
let paginaActual = 1;
let inactivityTimer;

// --- INICIALIZACIÓN Y EVENTOS ---
$(document).ready(function () {
    $('#listaTransportistas').select2({
        placeholder: "-- Elija una opción --",
        allowClear: false,
        width: '100%'
    });
    
    // Un solo manejador para todos los filtros
    const filterInputs = '#listaTransportistas, #fechaInicio, #fechaFin, #fechaRecibido, #fechaRecepcion, #filtroEstatus, #filtroUsuario, #buscarFactura';
    $(document).on('change input', filterInputs, () => cargarFacturas(1));

    $('#inputFactura').on('input', function () {
        if (this.value.trim().length === 11) {
            validarFactura();
        }
    });

    cargarFacturas();
    setupInactivityTimer();
});


// --- LÓGICA DE LA APLICACIÓN ---
function cargarFacturas(pagina = 1) {
    paginaActual = pagina;
    const formData = new FormData();
    const filters = {
        transportista: $('#listaTransportistas').val(),
        desde: $('#fechaInicio').val(),
        hasta: $('#fechaFin').val(),
        fechaRecibido: $('#fechaRecibido').val(),
        fechaRecepcion: $('#fechaRecepcion').val(),
        estatus: $('#filtroEstatus').val(),
        usuario: $('#filtroUsuario').length ? $('#filtroUsuario').val() : '',
        buscarFactura: $('#buscarFactura').val().trim(),
        pagina: pagina
    };
    for(const key in filters) {
        formData.append(key, filters[key]);
    }

    fetch('../Logica/get_facturas.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.text())
    .then(html => {
        $('#contenedorFacturas').html(html);
    })
    .catch(err => {
        console.error("Error al cargar facturas:", err);
        $('#contenedorFacturas').html("<div class='alert alert-danger'>No se pudieron cargar los datos. Verifique la conexión.</div>");
    });
}

function validarFactura() {
    const factura = $('#inputFactura').val().trim();
    const transportista = $('#listaTransportistas').val();
    if (!factura || !transportista) {
        alert("Debe seleccionar un transportista e ingresar una factura de 11 dígitos.");
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
        if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
        return res.json();
    })
    .then(respuesta => {
        if (respuesta.encontrada) {
            $('#inputFactura').val('').focus();
            cargarFacturas(paginaActual); // Recargar la tabla para mostrar el cambio
        } else {
            alert("Factura no encontrada para el transportista seleccionado.");
        }
    })
    .catch(error => {
        console.error("Error en validación:", error);
        alert("Ocurrió un error al validar la factura.");
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
        if (!respuesta.success) {
            alert("No se pudo actualizar el estado.");
            cargarFacturas(paginaActual); // Revertir visualmente
        }
    })
    .catch(error => {
        console.error("Error al actualizar:", error);
        alert("Error de conexión al actualizar estado.");
    });
}

// --- MANEJO DE INACTIVIDAD ---
function setupInactivityTimer() {
    const timeLimit = 200 * 1000; // 200 segundos
    const resetTimer = () => {
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(() => {
            alert("Su sesión ha expirado por inactividad.");
            window.location.href = "../Logica/logout.php";
        }, timeLimit);
    };
    window.onload = resetTimer;
    document.onmousemove = resetTimer;
    document.onkeydown = resetTimer;
    document.onclick = resetTimer;
    document.onscroll = resetTimer;
}

</script>
</body>
</html>