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

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: ../View/index.php");
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

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Recepción de Facturas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet" />
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #8B0000, #e31f25);
            background-size: 200% 200%;
            animation: gradientMove 10s ease infinite;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        @keyframes gradientMove {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .main-container {
            display: flex;
            height: 100vh;
            padding: 20px 40px;
            gap: 30px;
        }

        .formulario {
            flex: 1;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
            overflow-y: auto;
        }

        .sidebar {
            height: auto;
            min-height: 700px;
            width: 350px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
        }

        .sidebar img {
            display: block;
            margin: 0 auto 20px auto;
            max-width: 100%;
        }

        .sidebar h2 {
            font-size: 1.4rem;
            color: #fff;
            margin-bottom: 20px;
            text-align: center;
        }

        .btn-danger {
            background-color: #e31f25;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            font-weight: bold;
            border-radius: 8px;
            display: block;
            width: 100%;
            margin-top: 10px;
            transition: background-color 0.3s;
        }

        .btn-danger:hover {
            background-color: #b71c1c;
        }

        .form-label {
            color: #fff;
            font-weight: 600;
        }

        .form-control,
        .form-select {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid #ccc;
            border-radius: 10px;
            margin-bottom: 12px;
        }

        .select2-container--default .select2-selection--single {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            height: 38px;
            padding: 5px;
        }

        .input-group.mb-4 {
            border-radius: 8px;
        }

        .btn-success {
            background-color: #e31f25;
            border: none;
            border-radius: 0 8px 8px 0;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 0.375rem 0.6rem;
        }

        .btn-success:hover {
            background-color: #b71c1c;
        }

        .table {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 0 12px rgba(0,0,0,0.15);
        }

        .table th {
            background-color: #e31f25;
            color: #fff;
            font-weight: bold;
        }

        .table td, .table th {
            text-align: center;
            vertical-align: middle;
        }

        .table-success {
            background-color: #d4edda !important;
        }

        .animate__animated.animate__fadeIn {
            --animate-duration: 0.8s;
        }
    </style>
</head>
<body class="animate__animated animate__fadeIn">
</head>
<body>
<div class="main-container">
    <div class="formulario">
        <div id="contenedorFacturas"></div>
        <div id="paginacion" class="mt-3 d-flex justify-content-center"></div>
    </div>
    <div class="sidebar">
    <img src="../IMG/LOGO MC - Blanco.png" alt="Logo lateral">

    <label for="listaTransportistas" class="form-label">Transportista:</label>
    <select id="listaTransportistas" class="form-select">
        <option value="">-- Todos --</option>
        <?php foreach ($transportistas as $t): ?>
            <option value="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="fechaInicio" class="form-label">Desde:</label>
    <input type="date" id="fechaInicio" class="form-control" />

    <label for="fechaFin" class="form-label">Hasta:</label>
    <input type="date" id="fechaFin" class="form-control" />

    <label for="fechaRecibido" class="form-label">Fecha recibido:</label>
    <input type="date" id="fechaRecibido" class="form-control" />

    <label for="fechaRecepcion" class="form-label">Fecha recepción:</label>
    <input type="date" id="fechaRecepcion" class="form-control" />

    <label for="filtroEstatus" class="form-label">Estatus:</label>
    <select id="filtroEstatus" class="form-select">
        <option value="">-- Todos --</option>
        <option value="Completada">Completada</option>
        <option value="RE">RE</option>
    </select>

    <label for="buscarFactura" class="form-label">Buscar Factura:</label>
    <input type="text" id="buscarFactura" class="form-control" placeholder="Ej: 12345678901" maxlength="11" />

    <?php if (in_array($_SESSION['pantalla'], [0, 2, 5])): ?>
        <label for="filtroUsuario" class="form-label">Usuario:</label>
        <select id="filtroUsuario" class="form-select">
            <option value="">-- Todos --</option>
            <?php foreach ($usuarios as $u): ?>
                <option value="<?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>

    <label for="inputFactura" class="form-label">Nº Factura:</label>
    <div class="input-group mb-4">
        <input type="text" id="inputFactura" class="form-control flex-grow-1" placeholder="11 dígitos" maxlength="11" />
        <button class="btn btn-success" onclick="validarFactura()" title="Recibir factura">
            <i class="bi bi-box-arrow-in-down"></i>
        </button>
    </div>
    <div><a href="../Logica/logout.php" class="btn btn-danger">Cerrar Sesión</a></div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $('#filtroUsuario').on('change', () => cargarFacturas(1));
</script>

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
    console.log("Temporizador reiniciado por actividad");
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
    .then(res => res.text())
    .then(html => {
        document.getElementById('contenedorFacturas').innerHTML = html;
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
</script>
</body>
</html>
