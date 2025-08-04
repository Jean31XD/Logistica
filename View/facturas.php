<?php
// --- INICIO DE LÓGICA PHP ---
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httppnly', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();
date_default_timezone_set('America/Santo_Domingo');

// Cierre por inactividad (200 segundos) - This logic is already handled by the client-side JS and server-side on each request.
// The inactivity limit on the server side is managed by 'ultimo_acceso' in the session.
$inactividadLimite = 200;

if (isset($_SESSION['ultimo_acceso']) && (time() - $_SESSION['ultimo_acceso'] > $inactividadLimite)) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit();
}
$_SESSION['ultimo_acceso'] = time();

if (!isset($_SESSION['usuario'])) {
    header("Location: ../View/index.php");
    exit();
}

session_regenerate_id(true);

include '../conexionBD/conexion.php';
if (!$conn) die("Error de conexión: " . print_r(sqlsrv_errors(), true));

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
$queryTransportistas = "SELECT DISTINCT Transportista FROM custinvoicejour WHERE Transportista IS NOT NULL ORDER BY Transportista";
$resultTransportistas = sqlsrv_query($conn, $queryTransportistas);
$transportistas = [];
while ($row = sqlsrv_fetch_array($resultTransportistas, SQLSRV_FETCH_ASSOC)) {
    $transportistas[] = $row['Transportista'];
}

// Cargar usuarios si la pantalla es 0, 2 o 5
$usuarios = [];
if (in_array($_SESSION['pantalla'], [0, 2, 5])) {
    $queryUsuarios = "SELECT DISTINCT Usuario FROM custinvoicejour WHERE Usuario IS NOT NULL ORDER BY Usuario";
    $resultUsuarios = sqlsrv_query($conn, $queryUsuarios);
    while ($row = sqlsrv_fetch_array($resultUsuarios, SQLSRV_FETCH_ASSOC)) {
        $usuarios[] = $row['Usuario'];
    }
}

// Initialize filter variables from GET parameters
$filtroTransportista = $_GET['transportista'] ?? '';
$fechaInicio = $_GET['fechaInicio'] ?? '';
$fechaFin = $_GET['fechaFin'] ?? '';
$fechaRecibido = $_GET['fechaRecibido'] ?? '';
$fechaRecepcion = $_GET['fechaRecepcion'] ?? '';
$filtroEstatus = $_GET['estatus'] ?? '';
$buscarFactura = $_GET['buscarFactura'] ?? '';
$filtroUsuario = $_GET['usuario'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25; // Adjusted limit for display, can be changed
$offset = ($page - 1) * $limit;

// Build the WHERE clause for facturas query
$where = "WHERE 1=1";
$params = [];

if (!empty($filtroTransportista)) {
    $where .= " AND Transportista = ?";
    $params[] = $filtroTransportista;
}
if (!empty($fechaInicio)) {
    $where .= " AND Fecha >= ?";
    $params[] = $fechaInicio;
}
if (!empty($fechaFin)) {
    $where .= " AND Fecha <= ?";
    $params[] = $fechaFin;
}
if (!empty($fechaRecibido)) {
    $where .= " AND CONVERT(date, Fecha_scanner) = ?";
    $params[] = $fechaRecibido;
}
if (!empty($fechaRecepcion)) {
    $where .= " AND CONVERT(date, recepcion) = ?";
    $params[] = $fechaRecepcion;
}
if (!empty($filtroEstatus)) {
    $where .= " AND Validar = ?";
    $params[] = $filtroEstatus;
}
if (!empty($buscarFactura)) {
    $where .= " AND Factura LIKE ?";
    $params[] = '%' . $buscarFactura . '%';
}
if (in_array($_SESSION['pantalla'], [0, 2, 5]) && !empty($filtroUsuario)) {
    $where .= " AND Usuario = ?";
    $params[] = $filtroUsuario;
}

// Count total rows for pagination
$count_sql = "SELECT COUNT(*) AS total FROM custinvoicejour $where";
$count_stmt = sqlsrv_query($conn, $count_sql, $params);
$total_rows = sqlsrv_fetch_array($count_stmt)['total'] ?? 0;
$total_pages = $total_rows > 0 ? ceil($total_rows / $limit) : 1;

// Fetch facturas with pagination
$sql = "
SELECT
    Factura, Fecha, Validar AS Estado, Transportista, Fecha_scanner AS Recepcion_ALM,
    Usuario AS Usuario_ALM, recepcion AS Recepcion_CC, Usuario_de_recepcion AS Usuario_CC, zona AS Localizacion
FROM custinvoicejour
$where
ORDER BY Fecha DESC
OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY
";
$stmt = sqlsrv_query($conn, $sql, $params);

// Function to format dates
function formatDate($dateValue, $format) {
    if (empty($dateValue)) {
        return '';
    }
    try {
        $dateObj = ($dateValue instanceof DateTime) ? $dateValue : new DateTime($dateValue);
        return $dateObj->format($format);
    } catch (Exception $e) {
        return '';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Recepción de Facturas ✨</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(-45deg, #d32f2f, #b71c1c, #9a1a1a, #7f1818);
            background-size: 400% 400%;
            animation: gradientBG 20s ease infinite;
            color: #fff;
            padding: 1.5rem; /* Increased padding for better spacing */
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1.5rem;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
            padding: 2rem; /* Increased padding */
        }

        .main-container { display: flex; gap: 1.5rem; align-items: flex-start; }
        .main-content { flex: 1; }
        .sidebar { width: 380px; position: sticky; top: 1.5rem; } /* Slightly wider sidebar */

        .table-container { overflow-x: auto; }
        .table {
            color: #fff;
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            table-layout: auto;
        }

        .table thead th {
            background: rgba(0, 0, 0, 0.4);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1rem 0.8rem;
        }
        .table td, .table th {
            vertical-align: middle;
            padding: 0.9rem 0.8rem; /* Adjusted padding for table cells */
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        .table tbody tr:hover { background-color: rgba(255, 255, 255, 0.1); }
        .table-success { background-color: rgba(25, 135, 84, 0.3) !important; } /* Use a translucent green for success */

        .paginacion a { color: #fff; text-decoration: none; }
        .paginacion .page-link { background: transparent; border-color: rgba(255,255,255,0.3); color: #fff; }
        .paginacion .page-item.active .page-link { background-color: #fff; color: #b71c1c; border-color: #fff;} /* Red from the new design */
        .paginacion .page-item.disabled .page-link { background-color: rgba(0,0,0,0.2); border-color: rgba(255,255,255,0.2); color: rgba(255,255,255,0.5);}
        
        .form-label { font-weight: 600; color: #fff; margin-bottom: 0.5rem;}
        .form-control, .form-select {
            background-color: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff !important;
            padding: 0.75rem 1rem; /* Added padding to form controls */
            border-radius: 0.75rem; /* More rounded corners */
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        .form-control:focus, .form-select:focus {
            background-color: rgba(0, 0, 0, 0.3);
            border-color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.25);
            color: #fff;
        }

        .select2-container--bootstrap-5 .select2-selection {
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.3);
            color: #000 !important;
            height: auto;
            border-radius: 0.75rem !important; /* Match form control border-radius */
            padding: 0.375rem 1rem;
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            color: #000 !important;
            white-space: normal;
            word-break: break-all;
            line-height: calc(1.5em + 0.75rem + 2px); /* Align with input height */
        }
        select option { background-color: #343a40; }
        
        .select2-dropdown {
            background-color: #f8f9fa;
            border: 1px solid #6c757d;
            border-radius: 0.5rem;
            z-index: 1056;
        }
        .select2-results__option {
            color: #000;
            white-space: normal;
        }
        .select2-results__option--highlighted { background-color: #e31f25; color: #fff; } /* Changed highlight color to red */
        
        .btn-red { /* Custom button style for primary actions */
            background-color: #e31f25;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 0.75rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .btn-red:hover {
            background-color: #b71c1c;
            transform: translateY(-2px);
            color: white;
        }

        .btn-outline-light-red {
            border: 1px solid #e31f25;
            color: #e31f25;
            background-color: transparent;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 0.75rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        .btn-outline-light-red:hover {
            background-color: #e31f25;
            color: #fff;
        }


        .btn-link { color: #fff; text-decoration: none;}
        .btn-link:hover { color: rgba(255,255,255,0.8); }

        .input-group .btn-red {
            border-radius: 0 0.75rem 0.75rem 0;
            padding: 0.5rem 1rem; /* Adjust padding for button in input group */
        }
        .input-group .form-control:focus {
            z-index: 1; /* Ensure input focus does not overlap button border */
        }
        .input-group > .form-control {
            border-radius: 0.75rem 0 0 0.75rem;
        }

        /* --- ESTILOS RESPONSIVOS --- */
        @media (max-width: 992px) {
            body {
                padding: 1rem;
            }
            .main-container {
                flex-direction: column;
                gap: 1.5rem;
            }
            .sidebar, .main-content {
                width: 100%;
                position: static;
            }
            .sidebar {
                padding-top: 1.5rem; /* Adjust padding for mobile */
            }
            /* Show table and pagination on smaller screens, just make it scrollable */
            .table-container {
                display: block;
            }
            .paginacion {
                display: flex;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
<div class="main-container">
    <aside class="sidebar glass-panel animate__animated animate__fadeInLeft">
        <div class="text-center mb-4">
            <img src="../IMG/LOGO MC - NEGRO.png" alt="Logo" style="max-width: 150px; height: auto; margin-bottom: 1.5rem;">
            <h4 class="mt-3 mb-0">Gestión de Facturas</h4>
        </div>
        <form id="filtroForm" method="get" autocomplete="off">
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <label for="listaTransportistas" class="form-label">Transportista:</label>
                    <select id="listaTransportistas" name="transportista" class="form-select">
                        <option value="">-- Todos --</option>
                        <?php foreach ($transportistas as $t): ?>
                            <option value="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>" <?= $filtroTransportista === $t ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6">
                    <label for="fechaInicio" class="form-label">Desde:</label>
                    <input type="date" id="fechaInicio" name="fechaInicio" class="form-control" value="<?= htmlspecialchars($fechaInicio) ?>" />
                </div>
                <div class="col-6">
                    <label for="fechaFin" class="form-label">Hasta:</label>
                    <input type="date" id="fechaFin" name="fechaFin" class="form-control" value="<?= htmlspecialchars($fechaFin) ?>" />
                </div>

                <div class="col-12">
                    <label for="fechaRecibido" class="form-label">Fecha Recibido:</label>
                    <input type="date" id="fechaRecibido" name="fechaRecibido" class="form-control" value="<?= htmlspecialchars($fechaRecibido) ?>" />
                </div>
                
                <div class="col-12">
                    <label for="fechaRecepcion" class="form-label">Fecha Recepción:</label>
                    <input type="date" id="fechaRecepcion" name="fechaRecepcion" class="form-control" value="<?= htmlspecialchars($fechaRecepcion) ?>" />
                </div>

                <div class="col-12">
                    <label for="filtroEstatus" class="form-label">Estatus:</label>
                    <select id="filtroEstatus" name="estatus" class="form-select">
                        <option value="">-- Todos --</option>
                        <option value="Completada" <?= $filtroEstatus === 'Completada' ? 'selected' : '' ?>>Completada</option>
                        <option value="RE" <?= $filtroEstatus === 'RE' ? 'selected' : '' ?>>RE</option>
                    </select>
                </div>

                <div class="col-12">
                    <label for="buscarFactura" class="form-label">Buscar Factura:</label>
                    <input type="text" id="buscarFactura" name="buscarFactura" class="form-control" placeholder="Ej: 12345678901" maxlength="11" value="<?= htmlspecialchars($buscarFactura) ?>" />
                </div>

                <?php if (in_array($_SESSION['pantalla'], [0, 2, 5])): ?>
                    <div class="col-12">
                        <label for="filtroUsuario" class="form-label">Usuario:</label>
                        <select id="filtroUsuario" name="usuario" class="form-select">
                            <option value="">-- Todos --</option>
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?>" <?= $filtroUsuario === $u ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <div class="d-grid gap-3 mb-4">
                <button type="submit" class="btn btn-red animate__animated animate__pulse animate__infinite"><i class="fas fa-filter"></i> Aplicar Filtros</button>
                <a href="recepcion_facturas.php" class="btn btn-outline-light-red"><i class="fas fa-broom"></i> Limpiar Filtros</a>
            </div>
            
            <input type="hidden" name="page" value="<?= $page ?>">

            <div class="mt-4 text-center">
                <label for="inputFactura" class="form-label">Nº Factura:</label>
                <div class="input-group mb-3">
                    <input type="text" id="inputFactura" class="form-control" placeholder="11 dígitos" maxlength="11" />
                    <button class="btn btn-red" type="button" onclick="validarFactura()" title="Recibir factura">
                        <i class="bi bi-box-arrow-in-down"></i> Recibir
                    </button>
                </div>
                <a href="../Logica/logout.php" class="btn btn-link">Cerrar Sesión <i class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        </form>
    </aside>

    <main class="main-content glass-panel animate__animated animate__fadeInRight">
        <h2 class="mb-4">Facturas Recibidas</h2>
        
        <div id="contenedorFacturas" class="table-container">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Factura</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Transportista</th>
                        <th>Recepción ALM</th>
                        <th>Usuario ALM</th>
                        <th>Recepción CC</th>
                        <th>Usuario CC</th>
                        <th>Localización</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($stmt && $total_rows > 0): while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                    <tr id="fila_<?= htmlspecialchars($row['Factura'] ?? '') ?>" class="<?= ($row['Estado'] === 'Completada') ? 'table-success' : '' ?>">
                        <td><?= htmlspecialchars($row['Factura'] ?? '') ?></td>
                        <td><?= formatDate($row['Fecha'], 'd/m/Y') ?></td>
                        <td>
                            <select class="form-select estado-validar" onchange="actualizarEstado('<?= htmlspecialchars($row['Factura'] ?? '') ?>', this.value)">
                                <option value="Sin Estado" <?= (empty($row['Estado']) || $row['Estado'] === 'Sin Estado') ? 'selected' : '' ?>>Sin Estado</option>
                                <option value="Completada" <?= $row['Estado'] === 'Completada' ? 'selected' : '' ?>>Completada</option>
                                <option value="RE" <?= $row['Estado'] === 'RE' ? 'selected' : '' ?>>RE</option>
                            </select>
                        </td>
                        <td><?= htmlspecialchars($row['Transportista'] ?? '') ?></td>
                        <td class="fecha-scanner"><?= formatDate($row['Recepcion_ALM'], 'Y-m-d H:i') ?></td>
                        <td><?= htmlspecialchars($row['Usuario_ALM'] ?? '') ?></td>
                        <td><?= formatDate($row['Recepcion_CC'], 'Y-m-d H:i') ?></td>
                        <td><?= htmlspecialchars($row['Usuario_CC'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['Localizacion'] ?? '') ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-light-red" onclick="validarFacturaTabla('<?= htmlspecialchars($row['Factura'] ?? '') ?>')">
                                <i class="bi bi-box-arrow-in-down"></i> Recibir
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="10" class="text-center py-4">No se encontraron facturas con los filtros seleccionados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_rows > $limit): ?>
        <nav class="paginacion mt-4 d-flex justify-content-center">
            <ul class="pagination">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo;</a></li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">&raquo;</a></li>
            </ul>
        </nav>
        <?php endif; ?>
    </main>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
let paginaActual = <?= $page ?>;

$(document).ready(function () {
    function initializeSelect2(selector, placeholderText) {
        $(selector).select2({
            placeholder: placeholderText,
            allowClear: true,
            theme: 'bootstrap-5'
        });
    }

    initializeSelect2('#listaTransportistas', 'Buscar transportista...');
    initializeSelect2('#filtroUsuario', 'Buscar usuario...');

    // Event listeners for filter changes to submit the form
    $('#filtroForm select, #filtroForm input[type="date"], #filtroForm input[type="text"]').on('change keyup', function() {
        if ($(this).attr('id') === 'buscarFactura' && event.type === 'keyup') {
            // Only trigger on Enter for search input to avoid excessive requests
            if (event.key === 'Enter') {
                $('input[name="page"]').val(1);
                $('#filtroForm').submit();
            }
        } else if ($(this).attr('id') !== 'buscarFactura') {
            $('input[name="page"]').val(1);
            $('#filtroForm').submit();
        }
    });

    // Handle form submission explicitly for date inputs or on filter button click
    $('#filtroForm').on('submit', function(event) {
        // Prevent default form submission and handle it via JS
        // This is if you want to use AJAX for filtering instead of full page reloads
        // If you prefer full page reloads, you can remove this.
        // For now, the PHP side is set up for full page reloads on filter submit.
    });

    $('#inputFactura').on('input', function () {
        const valor = this.value.trim();
        if (valor.length === 11) {
            // Automatically validate if 11 digits are entered
            validarFactura();
        }
    });

    // Client-side inactivity timer
    const tiempoLimite = 5 * 60 * 1000; // 5 minutes in ms
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
    resetearTemporizador(); // Initialize on page load

    // Function to handle invoice validation
    window.validarFactura = function() {
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
                    if (fechaScanner) fechaScanner.textContent = respuesta.fecha_scanner || new Date().toLocaleString();
                }
                document.getElementById('inputFactura').value = '';
                document.getElementById('inputFactura').focus();
                // Reload the current page to reflect changes and re-apply filters
                window.location.href = window.location.href; // Simple page reload
            } else {
                alert("Factura no encontrada o ya ha sido procesada.");
            }
        })
        .catch(error => {
            console.error("Error al validar factura:", error);
            alert("Error al validar factura.");
        });
    }

    // Function to handle invoice validation from table row button
    window.validarFacturaTabla = function(factura) {
        const transportista = document.getElementById('listaTransportistas').value;

        if (!transportista) {
            alert("Debe seleccionar un transportista antes de validar una factura desde la tabla.");
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
            if (respuesta.success) {
                const fila = document.getElementById('fila_' + factura);
                if (fila) {
                    fila.classList.add('table-success');
                    const select = fila.querySelector('.estado-validar');
                    const fechaScanner = fila.querySelector('.fecha-scanner');
                    if (select) select.value = 'Completada';
                    if (fechaScanner) fechaScanner.textContent = respuesta.fecha_scanner || new Date().toLocaleString();
                }
                alert("Factura " + factura + " validada correctamente.");
                window.location.href = window.location.href; // Reload to reflect changes
            } else {
                alert(respuesta.message || "Factura no encontrada o ya ha sido procesada.");
            }
        })
        .catch(error => {
            console.error("Error al validar factura desde la tabla:", error);
            alert("Error al validar factura.");
        });
    }

    // Function to update invoice status
    window.actualizarEstado = function(factura, nuevoEstado) {
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
                // Reload the current page to reflect changes
                window.location.href = window.location.href;
            } else {
                alert("No se pudo actualizar el estado: " + (respuesta.message || "Error desconocido."));
            }
        })
        .catch(error => {
            console.error("Error:", error);
            alert("Error al actualizar estado.");
        });
    }
});
</script>
</body>
</html>