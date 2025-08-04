<?php
// Seguridad de sesión
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();
date_default_timezone_set('America/Santo_Domingo');

// Cierre por inactividad (300 segundos = 5 minutos)
$inactividadLimite = 300; 

if (isset($_SESSION['ultimo_acceso']) && (time() - $_SESSION['ultimo_acceso'] > $inactividadLimite)) {
    session_unset();
    session_destroy();
    header("Location: ../index.php?status=inactivity");
    exit();
}
$_SESSION['ultimo_acceso'] = time();

if (!isset($_SESSION['usuario'])) {
    header("Location: ../View/index.php");
    exit();
}

session_regenerate_id(true);

include '../conexionBD/conexion.php';

header("Cache-Control: no-cache, no-store, must-revalidate");

if (!isset($_SESSION['pantalla']) || !in_array($_SESSION['pantalla'], [0, 2, 3, 5])) {
    header("Location: ../index.php");
    exit();
}

// Cargar transportistas
$query = "SELECT DISTINCT Transportista FROM custinvoicejour WHERE Transportista IS NOT NULL AND Transportista <> '' ORDER BY Transportista ASC";
$result = sqlsrv_query($conn, $query);
$transportistas = [];
if ($result) {
    while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
        $transportistas[] = $row['Transportista'];
    }
}

// Cargar usuarios
$usuarios = [];
if (in_array($_SESSION['pantalla'], [0, 2, 5])) {
    $queryUsuarios = "SELECT DISTINCT Usuario FROM custinvoicejour WHERE Usuario IS NOT NULL AND Usuario <> '' ORDER BY Usuario ASC";
    $resultUsuarios = sqlsrv_query($conn, $queryUsuarios);
    if ($resultUsuarios) {
        while ($row = sqlsrv_fetch_array($resultUsuarios, SQLSRV_FETCH_ASSOC)) {
            $usuarios[] = $row['Usuario'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Recepción de Facturas ✨</title>
    <link rel="icon" href="../IMG/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        :root { --theme-red: #e31f25; --theme-red-dark: #b71c1c; }
        html, body { height: 100%; margin: 0; padding: 0; background: linear-gradient(to bottom, #f8f9fa, #e9ecef); font-family: 'Poppins', sans-serif; }
        .main-container { display: flex; height: 100vh; padding: 20px; gap: 20px; }
        .formulario { flex: 1; display: flex; flex-direction: column; background: #ffffff; padding: 25px; border-radius: 16px; box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        #contenedorFacturas { flex-grow: 1; overflow-y: auto; }
        .sidebar { width: 320px; flex-shrink: 0; background-color: #fff; border-radius: 16px; padding: 25px; box-shadow: 0 8px 25px rgba(0,0,0,0.1); display: flex; flex-direction: column; }
        .sidebar .logo-container { text-align: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .sidebar .logo-container img { height: 60px; }
        .form-label { font-weight: 600; color: #555; margin-bottom: .3rem; font-size: .9rem; }
        .form-control, .form-select, .select2-container .select2-selection--single { margin-bottom: 12px !important; border-radius: 8px !important; }
        .btn-danger { background-color: var(--theme-red); border-color: var(--theme-red); }
        .btn-danger:hover { background-color: var(--theme-red-dark); border-color: var(--theme-red-dark); }
        .btn-success { background-color: var(--theme-red); border-color: var(--theme-red); border-radius: 0 8px 8px 0 !important; }
        .btn-success:hover { background-color: var(--theme-red-dark); border-color: var(--theme-red-dark); }
        .table-container { margin-top: 1rem; }
        .table { font-size: 0.9rem; }
        .table th { background-color: var(--theme-red); color: white; }
        .table td, .table th { vertical-align: middle; }
        .table-success { --bs-table-bg: #d1e7dd; --bs-table-border-color: #a3cfbb; }
        #loading-spinner { display: none; text-align: center; padding: 40px; }
        .sidebar-footer { margin-top: auto; }
    </style>
</head>
<body>
<div class="main-container">
    <div class="sidebar">
        <div class="logo-container"><img src="../IMG/LOGO MC - NEGRO.png" alt="Logo"></div>
        
        <label for="listaTransportistas" class="form-label">Transportista:</label>
        <select id="listaTransportistas" class="form-select">
            <option value="">-- Todos --</option>
            <?php foreach ($transportistas as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="fechaInicio" class="form-label">Desde:</label>
        <input type="date" id="fechaInicio" class="form-control" />

        <label for="fechaFin" class="form-label">Hasta:</label>
        <input type="date" id="fechaFin" class="form-control" />

        <label for="filtroEstatus" class="form-label">Estatus:</label>
        <select id="filtroEstatus" class="form-select">
            <option value="">-- Todos --</option>
            <option value="Completada">Completada</option>
            <option value="RE">RE</option>
            <option value="Pendiente">Pendiente</option>
        </select>

        <label for="buscarFactura" class="form-label">Buscar Factura:</label>
        <input type="text" id="buscarFactura" class="form-control" placeholder="Nº de factura" maxlength="11" />

        <?php if (in_array($_SESSION['pantalla'], [0, 2, 5])): ?>
            <label for="filtroUsuario" class="form-label">Usuario:</label>
            <select id="filtroUsuario" class="form-select">
                <option value="">-- Todos --</option>
                <?php foreach ($usuarios as $u): ?>
                    <option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <div class="sidebar-footer">
            <label for="inputFactura" class="form-label">Recepción Rápida:</label>
            <div class="input-group">
                <input type="text" id="inputFactura" class="form-control" placeholder="Nº Factura de 11 dígitos" maxlength="11" />
                <button id="btnValidarFactura" class="btn btn-success" title="Recibir factura">
                    <i class="bi bi-box-arrow-in-down"></i>
                </button>
            </div>
            <a href="../Logica/logout.php" class="btn btn-danger w-100 mt-3">Cerrar Sesión</a>
        </div>
    </div>

    <div class="formulario">
        <div id="loading-spinner">
            <div class="spinner-border text-danger" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
        <div id="contenedorFacturas"></div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function () {
    let paginaActual = 1;
    let temporizadorInactividad;
    const LIMITE_INACTIVIDAD_MS = 300 * 1000; // 5 minutos

    // --- FUNCIONES AUXILIARES ---
    function debounce(fn, delay = 400) {
        let timeoutId;
        return (...args) => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    function resetearTemporizador() {
        clearTimeout(temporizadorInactividad);
        temporizadorInactividad = setTimeout(() => {
            alert("Su sesión ha expirado por inactividad.");
            window.location.href = "../Logica/logout.php";
        }, LIMITE_INACTIVIDAD_MS);
    }

    // --- LÓGICA PRINCIPAL ---
    
    function cargarFacturas(pagina = 1) {
        paginaActual = pagina;
        const formData = new FormData();
        formData.append('transportista', $('#listaTransportistas').val());
        formData.append('desde', $('#fechaInicio').val());
        formData.append('hasta', $('#fechaFin').val());
        formData.append('estatus', $('#filtroEstatus').val());
        formData.append('usuario', $('#filtroUsuario').val() || '');
        formData.append('buscarFactura', $('#buscarFactura').val().trim());
        formData.append('pagina', pagina);

        $('#loading-spinner').show();
        $('#contenedorFacturas').css('opacity', 0.5);

        fetch('../Logica/get_facturas.php', { method: 'POST', body: formData })
            .then(res => res.text())
            .then(html => {
                $('#contenedorFacturas').html(html);
            })
            .catch(error => console.error("Error al cargar facturas:", error))
            .finally(() => {
                $('#loading-spinner').hide();
                $('#contenedorFacturas').css('opacity', 1);
            });
    }

    function validarFactura() {
        const factura = $('#inputFactura').val().trim();
        const transportista = $('#listaTransportistas').val();
        if (factura.length !== 11) return alert("El número de factura debe tener 11 dígitos.");
        if (!transportista) return alert("Debe seleccionar un transportista.");

        const formData = new FormData();
        formData.append('factura', factura);
        formData.append('transportista', transportista);

        fetch('../Logica/Validar_factura.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(respuesta => {
                if (respuesta && respuesta.encontrada) {
                    const fila = $('#fila_' + factura);
                    if (fila.length) {
                        fila.addClass('table-success');
                        fila.find('.estado-validar').val('Completada');
                        fila.find('.fecha-scanner').text(respuesta.fecha_scanner || 'Ahora');
                    } else {
                        // Si la factura no estaba en la vista actual, recargamos para que aparezca
                        cargarFacturas(1);
                    }
                    $('#inputFactura').val('').focus();
                } else {
                    alert("Factura no encontrada para el transportista seleccionado.");
                }
            })
            .catch(error => console.error("Error en validación:", error));
    }

    // --- INICIALIZACIÓN Y MANEJADORES DE EVENTOS ---

    $('#listaTransportistas, #filtroUsuario').select2({
        placeholder: "Seleccionar...",
        allowClear: true,
        width: '100%'
    });

    const filtros = '#listaTransportistas, #fechaInicio, #fechaFin, #filtroEstatus, #filtroUsuario';
    $(filtros).on('change', () => cargarFacturas(1));

    $('#buscarFactura').on('input', debounce(() => cargarFacturas(1), 500));

    $('#inputFactura').on('keypress', function(e) {
        if (e.which === 13) { // Tecla Enter
            e.preventDefault();
            validarFactura();
        }
    });

    $('#btnValidarFactura').on('click', validarFactura);

    // Delegación de eventos para elementos dinámicos
    $(document).on('change', '.estado-validar', function() {
        const factura = $(this).data('factura');
        const nuevoEstado = $(this).val();
        
        const formData = new FormData();
        formData.append('factura', factura);
        formData.append('nuevoEstado', nuevoEstado);
        
        fetch('../Logica/actualizar_estado.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(respuesta => {
                if (respuesta.success) {
                    const fila = $('#fila_' + factura);
                    if (fila.length) {
                        fila.css({ transition: 'background-color 0.2s ease', backgroundColor: '#d1e7dd' });
                        setTimeout(() => fila.css('backgroundColor', ''), 1200);
                    }
                } else {
                    alert("No se pudo actualizar el estado.");
                }
            })
            .catch(error => console.error("Error al actualizar estado:", error));
    });

    $(document).on('click', '.page-link', function(e){
        e.preventDefault();
        const page = $(this).data('page');
        if(page) cargarFacturas(page);
    });

    // Iniciar temporizador de inactividad
    ['click', 'mousemove', 'keydown', 'scroll'].forEach(evt => document.addEventListener(evt, resetearTemporizador, false));
    resetearTemporizador();

    // Carga inicial
    cargarFacturas(1);
});
</script>
</body>
</html>