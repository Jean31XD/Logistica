<?php
/**
 * Exportar Códigos de Referencia a Excel
 * Genera un archivo Excel con todos los códigos aplicando filtros
 */

require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion([0, 5, 12]); // Admin, Admin-limitado, Códigos de Referencia
require_once __DIR__ . '/../conexionBD/conexion.php';

try {
    // Obtener filtros
    $searchNombre = isset($_GET['searchNombre']) ? trim($_GET['searchNombre']) : '';
    $searchCodigo = isset($_GET['searchCodigo']) ? trim($_GET['searchCodigo']) : '';
    $filterEstado = isset($_GET['filterEstado']) ? trim($_GET['filterEstado']) : '';

    // Construir condición WHERE
    $whereConditions = "WHERE 1=1";
    $params = array();

    // Filtro por nombre
    if (!empty($searchNombre)) {
        $whereConditions .= " AND Nombre LIKE ?";
        $params[] = '%' . $searchNombre . '%';
    }

    // Filtro por código
    if (!empty($searchCodigo)) {
        $whereConditions .= " AND Codigo_barra LIKE ?";
        $params[] = '%' . $searchCodigo . '%';
    }

    // Filtro por estado
    if ($filterEstado === 'asignado') {
        $whereConditions .= " AND Codigo_barra IS NOT NULL AND Codigo_barra != '' AND LEN(RTRIM(LTRIM(Codigo_barra))) > 0";
    } elseif ($filterEstado === 'sin_asignar') {
        $whereConditions .= " AND (Codigo_barra IS NULL OR Codigo_barra = '' OR LEN(RTRIM(LTRIM(Codigo_barra))) = 0)";
    }

    // Consulta para obtener todos los datos (sin paginación)
    $sql = "SELECT id, Nombre, Codigo_barra, Usuario
            FROM [dbo].[Arti_codigos]
            $whereConditions
            ORDER BY id ASC";

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        throw new Exception("Error en la consulta: " . print_r(sqlsrv_errors(), true));
    }

    // Preparar nombre del archivo
    $fecha = date('Y-m-d_H-i-s');
    $filename = "Codigos_Referencia_" . $fecha . ".xls";

    // Configurar cabeceras para descarga de Excel
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Agregar BOM para UTF-8
    echo "\xEF\xBB\xBF";

    // Crear tabla HTML (Excel puede leer HTML)
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th { background-color: #E63946; color: white; font-weight: bold; padding: 10px; border: 1px solid #ddd; text-align: left; }';
    echo 'td { padding: 8px; border: 1px solid #ddd; }';
    echo 'tr:nth-child(even) { background-color: #f2f2f2; }';
    echo '.asignado { background-color: #d4edda; color: #155724; }';
    echo '.sin-asignar { background-color: #f8d7da; color: #721c24; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';

    echo '<h1>Códigos de Referencia - MACO</h1>';
    echo '<p>Fecha de exportación: ' . date('d/m/Y H:i:s') . '</p>';
    echo '<p>Usuario: ' . htmlspecialchars($_SESSION['usuario']) . '</p>';

    if (!empty($searchNombre) || !empty($searchCodigo) || !empty($filterEstado)) {
        echo '<h3>Filtros aplicados:</h3><ul>';
        if (!empty($searchNombre)) echo '<li>Nombre: ' . htmlspecialchars($searchNombre) . '</li>';
        if (!empty($searchCodigo)) echo '<li>Código: ' . htmlspecialchars($searchCodigo) . '</li>';
        if (!empty($filterEstado)) {
            $estadoText = $filterEstado === 'asignado' ? 'Con código asignado' : 'Sin código asignar';
            echo '<li>Estado: ' . $estadoText . '</li>';
        }
        echo '</ul>';
    }

    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Nombre del Artículo</th>';
    echo '<th>Código de Barras</th>';
    echo '<th>Usuario Asignado</th>';
    echo '<th>Estado</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    $contador = 0;
    $totalAsignados = 0;
    $totalSinAsignar = 0;

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $contador++;
        $tieneCodigoRow = !empty($row['Codigo_barra']) && trim($row['Codigo_barra']) !== '';

        if ($tieneCodigoRow) {
            $totalAsignados++;
        } else {
            $totalSinAsignar++;
        }

        $estadoClass = $tieneCodigoRow ? 'asignado' : 'sin-asignar';
        $estadoText = $tieneCodigoRow ? 'Asignado' : 'Sin asignar';
        $codigoDisplay = $tieneCodigoRow ? htmlspecialchars($row['Codigo_barra']) : '-';
        $usuario = !empty($row['Usuario']) ? htmlspecialchars($row['Usuario']) : '-';

        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['id']) . '</td>';
        echo '<td>' . htmlspecialchars($row['Nombre']) . '</td>';
        echo '<td>' . $codigoDisplay . '</td>';
        echo '<td>' . $usuario . '</td>';
        echo '<td class="' . $estadoClass . '">' . $estadoText . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '<tfoot>';
    echo '<tr style="background-color: #e9ecef; font-weight: bold;">';
    echo '<td colspan="5">';
    echo 'Total de registros: ' . $contador . ' | ';
    echo 'Asignados: ' . $totalAsignados . ' | ';
    echo 'Sin asignar: ' . $totalSinAsignar;
    echo '</td>';
    echo '</tr>';
    echo '</tfoot>';
    echo '</table>';

    echo '</body>';
    echo '</html>';

    sqlsrv_free_stmt($stmt);

    // Log de la exportación
    error_log("Exportación de códigos realizada por usuario: {$_SESSION['usuario']}, Total registros: $contador");

} catch (Exception $e) {
    error_log("Error en exportar_codigos.php: " . $e->getMessage());

    // Limpiar buffer de salida si hay error
    if (ob_get_length()) ob_clean();

    // Redirigir a página de error
    header('Location: ../View/Codigos_referencia.php?error=export_failed');
    exit;
}
?>
