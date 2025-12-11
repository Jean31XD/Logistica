<?php
/**
 * Reporte por Transportista - MACO Design System
 */

require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion([0, 4, 5]); // Solo pantallas 0, 4, 5
require_once __DIR__ . '/../conexionBD/conexion.php';

$filtro = $_GET['transportista'] ?? '';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$porPagina = 10;
$offset = ($pagina - 1) * $porPagina;

$hoy = date('Y-m-d');
if (!$desde) $desde = $hoy;
if (!$hasta) $hasta = $hoy;

$desdeSQL = $desde;
$hastaSQL = $hasta;

// TOTAL
$sqlTotal = "SELECT COUNT(DISTINCT Transportista) AS total
             FROM custinvoicejour
             WHERE Transportista IS NOT NULL
               AND Validar = 'Completada'
               AND Fecha_Scanner BETWEEN ? AND ?
               AND Factura NOT LIKE 'NC%'";
$paramsTotal = [$desdeSQL, $hastaSQL];

if ($filtro) {
    $sqlTotal .= " AND Transportista LIKE ?";
    $paramsTotal[] = "%$filtro%";
}

$stmtTotal = sqlsrv_query($conn, $sqlTotal, $paramsTotal);
$totalRow = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC);
$totalPaginas = ceil($totalRow['total'] / $porPagina);

// PRINCIPAL
$sql = "SELECT Transportista, COUNT(*) AS Cantidad, MIN(zona) AS zona
        FROM custinvoicejour
        WHERE Transportista IS NOT NULL
          AND Validar = 'Completada'
          AND Fecha_Scanner BETWEEN ? AND ?
          AND Factura NOT LIKE 'NC%'";
$params = [$desdeSQL, $hastaSQL];

if ($filtro) {
    $sql .= " AND Transportista LIKE ?";
    $params[] = "%$filtro%";
}

$sql .= " GROUP BY Transportista ORDER BY Cantidad DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$params[] = $offset;
$params[] = $porPagina;

$stmt = sqlsrv_query($conn, $sql, $params);
if (!$stmt) {
    die("Error al consultar: " . print_r(sqlsrv_errors(), true));
}

$pageTitle = "Reporte por Transportista | MACO";
$containerClass = "maco-container-fluid";
$additionalCSS = <<<'CSS'
<style>
    /* Layout especial para Reporte */
    .reporte-layout {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 2rem;
        margin-top: 1rem;
    }

    .reporte-main {
        min-width: 0;
    }

    .reporte-sidebar {
        background: white;
        border-radius: var(--radius-lg);
        padding: 2rem;
        box-shadow: var(--shadow-lg);
        height: fit-content;
        position: sticky;
        top: 80px;
    }

    .reporte-sidebar-logo {
        text-align: center;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid var(--gray-200);
    }

    .reporte-sidebar-logo img {
        max-width: 100%;
        height: auto;
        max-height: 80px;
    }

    .filter-group {
        margin-bottom: 1.5rem;
    }

    .filter-label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-primary);
        font-size: 0.9rem;
    }

    .filter-input {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius);
        font-size: 0.95rem;
        transition: all 0.2s ease;
    }

    .filter-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(255, 0, 0, 0.1);
    }

    .report-card {
        background: white;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        overflow: hidden;
    }

    .report-header {
        background: var(--primary);
        color: white;
        padding: 2rem;
        text-align: center;
    }

    .report-header h1 {
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0;
    }

    .report-body {
        padding: 2rem;
    }

    .report-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .report-table thead th {
        background: var(--primary);
        color: white;
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .report-table thead th:first-child {
        border-top-left-radius: var(--radius);
    }

    .report-table thead th:last-child {
        border-top-right-radius: var(--radius);
    }

    .report-table tbody td {
        padding: 1rem;
        border-bottom: 1px solid var(--gray-200);
        vertical-align: middle;
    }

    .report-table tbody tr:hover {
        background-color: var(--gray-50);
    }

    .report-table tbody tr:last-child td:first-child {
        border-bottom-left-radius: var(--radius);
    }

    .report-table tbody tr:last-child td:last-child {
        border-bottom-right-radius: var(--radius);
    }

    .dropdown-button {
        background: transparent;
        border: 2px solid var(--primary);
        color: var(--primary);
        padding: 0.5rem 1rem;
        border-radius: var(--radius);
        font-weight: 500;
        transition: all 0.2s ease;
        cursor: pointer;
        font-size: 0.875rem;
    }

    .dropdown-button:hover {
        background: var(--primary);
        color: white;
    }

    .dropdown-menu {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        box-shadow: var(--shadow-lg);
        max-height: 400px;
        overflow-y: auto;
    }

    .dropdown-item {
        padding: 0.75rem 1rem;
        color: var(--text-primary);
        font-size: 0.9rem;
        border-bottom: 1px solid var(--gray-100);
    }

    .dropdown-item:last-child {
        border-bottom: none;
    }

    .dropdown-item:hover {
        background: var(--gray-50);
    }

    .pagination-custom {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
        flex-wrap: wrap;
    }

    .page-link-custom {
        padding: 0.5rem 1rem;
        border: 2px solid var(--gray-200);
        background: white;
        color: var(--text-primary);
        border-radius: var(--radius);
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .page-link-custom:hover {
        background: var(--gray-100);
        border-color: var(--gray-300);
    }

    .page-link-custom.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    @media (max-width: 992px) {
        .reporte-layout {
            grid-template-columns: 1fr;
        }

        .reporte-sidebar {
            position: static;
            order: -1;
        }
    }
</style>
CSS;
include __DIR__ . '/templates/header.php';
?>

<div class="reporte-layout">
    <!-- Contenido Principal -->
    <div class="reporte-main">
        <div class="report-card animate__animated animate__fadeIn">
            <div class="report-header">
                <i class="fas fa-chart-bar" style="font-size: 2.5rem; margin-bottom: 0.5rem;"></i>
                <h1>Reporte de Facturas por Transportista</h1>
            </div>
            <div class="report-body">
                <div class="table-responsive">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-truck me-2"></i>Transportista</th>
                                <th><i class="fas fa-file-invoice me-2"></i>Total de Facturas</th>
                                <th><i class="fas fa-info-circle me-2"></i>Detalles</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['Transportista']) ?></strong></td>
                                <td>
                                    <span class="maco-badge maco-badge-info">
                                        <?= $row['Cantidad'] ?> facturas
                                    </span>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="dropdown-button dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-eye me-2"></i>Ver Facturas — Zona: <?= htmlspecialchars($row['zona']) ?>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <?php
                                                $transportista = $row['Transportista'];
                                                $sqlFacturas = "SELECT Factura, Clientes, zona
                                                                FROM custinvoicejour
                                                                WHERE Validar = 'Completada'
                                                                  AND Transportista = ?
                                                                  AND Fecha_Scanner BETWEEN ? AND ?
                                                                  AND Factura NOT LIKE 'NC%'";
                                                $paramsFacturas = [$transportista, $desdeSQL, $hastaSQL];
                                                $stmtFacturas = sqlsrv_query($conn, $sqlFacturas, $paramsFacturas);
                                                while ($fact = sqlsrv_fetch_array($stmtFacturas, SQLSRV_FETCH_ASSOC)): ?>
                                                <li>
                                                    <span class="dropdown-item">
                                                        <strong><?= htmlspecialchars($fact['Factura']) ?></strong> —
                                                        <?= htmlspecialchars($fact['Clientes']) ?> —
                                                        <span class="maco-badge maco-badge-info"><?= htmlspecialchars($fact['zona']) ?></span>
                                                    </span>
                                                </li>
                                            <?php endwhile; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPaginas > 1): ?>
                <div class="pagination-custom">
                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                    <a class="page-link-custom <?= $i == $pagina ? 'active' : '' ?>"
                       href="?transportista=<?= urlencode($filtro) ?>&desde=<?= $desde ?>&hasta=<?= $hasta ?>&pagina=<?= $i ?>">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar de Filtros -->
    <aside class="reporte-sidebar animate__animated animate__fadeInRight">
        <div class="reporte-sidebar-logo">
            <img src="../IMG/LOGO MC - NEGRO.png" alt="MACO Logo">
        </div>

        <form method="get" id="formFiltros">
            <div class="filter-group">
                <label for="inputTransportista" class="filter-label">
                    <i class="fas fa-truck me-2"></i>Transportista
                </label>
                <input type="text"
                       name="transportista"
                       id="inputTransportista"
                       class="filter-input"
                       placeholder="Buscar transportista..."
                       value="<?= htmlspecialchars($filtro) ?>">
            </div>

            <div class="filter-group">
                <label for="desde" class="filter-label">
                    <i class="fas fa-calendar-alt me-2"></i>Desde
                </label>
                <input type="date"
                       name="desde"
                       id="desde"
                       class="filter-input"
                       value="<?= htmlspecialchars($desde) ?>">
            </div>

            <div class="filter-group">
                <label for="hasta" class="filter-label">
                    <i class="fas fa-calendar-alt me-2"></i>Hasta
                </label>
                <input type="date"
                       name="hasta"
                       id="hasta"
                       class="filter-input"
                       value="<?= htmlspecialchars($hasta) ?>">
            </div>
        </form>

        <div class="maco-divider"></div>

        <form method="get" action="../Logica/exportar_csv.php" style="margin-bottom: 1rem;">
            <input type="hidden" name="transportista" value="<?= htmlspecialchars($filtro) ?>">
            <input type="hidden" name="desde" value="<?= htmlspecialchars($desde) ?>">
            <input type="hidden" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
            <button type="submit" class="maco-btn maco-btn-success w-100">
                <i class="fas fa-file-excel me-2"></i>Exportar a Excel
            </button>
        </form>
    </aside>
</div>

<script>
  const form = document.getElementById('formFiltros');
  document.getElementById('inputTransportista').addEventListener('input', function () {
      clearTimeout(this._delay);
      this._delay = setTimeout(() => form.submit(), 500);
  });
  document.getElementById('desde').addEventListener('change', () => form.submit());
  document.getElementById('hasta').addEventListener('change', () => form.submit());
</script>

<?php include __DIR__ . '/templates/footer.php'; ?>
