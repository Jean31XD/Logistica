<?php
/**
 * Exportar Códigos de Referencia a Excel
 * Genera un archivo Excel con todos los códigos aplicando filtros
 * 
 * IMPORTANTE: Este script maneja su propia salida y NO debe tener output previo
 */

// Iniciar buffer de salida ANTES de cualquier cosa
ob_start();

// Incluir dependencias
require_once __DIR__ . '/../conexionBD/conexion.php';
require_once __DIR__ . '/../conexionBD/helpers.php';

// Validación manual de sesión (sin los headers de session_config)
session_start();
if (!isset($_SESSION['usuario'])) {
    ob_end_clean();
    header("Location: " . getLoginUrl());
    exit();
}

// Verificar permisos usando usuario_modulos
require_once __DIR__ . '/../conexionBD/conexion.php';
$tienePermiso = tieneModulo('codigos_referencia', $conn) || tieneModulo('codigos_barras', $conn);
if (!$tienePermiso) {
    ob_end_clean();
    header("Location: " . getLoginUrl());
    exit();
}

try {
    // Obtener filtros
    $searchNombre = isset($_GET['searchNombre']) ? trim($_GET['searchNombre']) : '';
    $searchCodigo = isset($_GET['searchCodigo']) ? trim($_GET['searchCodigo']) : '';
    $filterEstado = isset($_GET['filterEstado']) ? trim($_GET['filterEstado']) : '';

    // Construir condición WHERE
    $whereConditions = "WHERE 1=1";
    $params = array();

    if (!empty($searchNombre)) {
        $whereConditions .= " AND Nombre LIKE ?";
        $params[] = '%' . $searchNombre . '%';
    }

    if (!empty($searchCodigo)) {
        $whereConditions .= " AND Codigo_barra LIKE ?";
        $params[] = '%' . $searchCodigo . '%';
    }

    if ($filterEstado === 'asignado') {
        $whereConditions .= " AND Codigo_barra IS NOT NULL AND Codigo_barra != '' AND LEN(RTRIM(LTRIM(Codigo_barra))) > 0";
    } elseif ($filterEstado === 'sin_asignar') {
        $whereConditions .= " AND (Codigo_barra IS NULL OR Codigo_barra = '' OR LEN(RTRIM(LTRIM(Codigo_barra))) = 0)";
    }

    // Consulta
    $sql = "SELECT id, Nombre, Codigo_barra, Usuario
            FROM [dbo].[Arti_codigos]
            $whereConditions
            ORDER BY id ASC";

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        throw new Exception("Error en la consulta: " . print_r(sqlsrv_errors(), true));
    }

    // Construir contenido del Excel en memoria primero
    $excelContent = '';
    
    // BOM UTF-8
    $excelContent .= "\xEF\xBB\xBF";
    
    // HTML para Excel
    $excelContent .= '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    $excelContent .= '<style>';
    $excelContent .= 'table { border-collapse: collapse; width: 100%; }';
    $excelContent .= 'th { background-color: #E63946; color: white; font-weight: bold; padding: 10px; border: 1px solid #ddd; }';
    $excelContent .= 'td { padding: 8px; border: 1px solid #ddd; }';
    $excelContent .= 'tr:nth-child(even) { background-color: #f2f2f2; }';
    $excelContent .= '.asignado { background-color: #d4edda; color: #155724; }';
    $excelContent .= '.sin-asignar { background-color: #f8d7da; color: #721c24; }';
    $excelContent .= '</style></head><body>';
    
    $excelContent .= '<h1>Códigos de Referencia - MACO</h1>';
    $excelContent .= '<p>Fecha: ' . date('d/m/Y H:i:s') . '</p>';
    $excelContent .= '<p>Usuario: ' . htmlspecialchars($_SESSION['usuario']) . '</p>';
    
    // Filtros aplicados
    if (!empty($searchNombre) || !empty($searchCodigo) || !empty($filterEstado)) {
        $excelContent .= '<h3>Filtros:</h3><ul>';
        if (!empty($searchNombre)) $excelContent .= '<li>Nombre: ' . htmlspecialchars($searchNombre) . '</li>';
        if (!empty($searchCodigo)) $excelContent .= '<li>Código: ' . htmlspecialchars($searchCodigo) . '</li>';
        if (!empty($filterEstado)) {
            $estadoText = $filterEstado === 'asignado' ? 'Con código' : 'Sin código';
            $excelContent .= '<li>Estado: ' . $estadoText . '</li>';
        }
        $excelContent .= '</ul>';
    }
    
    // Tabla
    $excelContent .= '<table>';
    $excelContent .= '<thead><tr><th>ID</th><th>Nombre</th><th>Código de Barras</th><th>Usuario</th><th>Estado</th></tr></thead>';
    $excelContent .= '<tbody>';
    
    $contador = 0;
    $totalAsignados = 0;
    $totalSinAsignar = 0;
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $contador++;
        $tieneCodigoRow = !empty($row['Codigo_barra']) && trim($row['Codigo_barra']) !== '';
        
        if ($tieneCodigoRow) {
            $totalAsignados++;
            $estadoClass = 'asignado';
            $estadoText = 'Asignado';
            $codigoDisplay = htmlspecialchars($row['Codigo_barra']);
        } else {
            $totalSinAsignar++;
            $estadoClass = 'sin-asignar';
            $estadoText = 'Sin asignar';
            $codigoDisplay = '-';
        }
        
        $usuario = !empty($row['Usuario']) ? htmlspecialchars($row['Usuario']) : '-';
        
        $excelContent .= '<tr>';
        $excelContent .= '<td>' . htmlspecialchars($row['id']) . '</td>';
        $excelContent .= '<td>' . htmlspecialchars($row['Nombre']) . '</td>';
        $excelContent .= '<td>' . $codigoDisplay . '</td>';
        $excelContent .= '<td>' . $usuario . '</td>';
        $excelContent .= '<td class="' . $estadoClass . '">' . $estadoText . '</td>';
        $excelContent .= '</tr>';
    }
    
    $excelContent .= '</tbody>';
    $excelContent .= '<tfoot><tr style="background-color: #e9ecef; font-weight: bold;">';
    $excelContent .= '<td colspan="5">Total: ' . $contador . ' | Asignados: ' . $totalAsignados . ' | Sin asignar: ' . $totalSinAsignar . '</td>';
    $excelContent .= '</tr></tfoot>';
    $excelContent .= '</table></body></html>';
    
    sqlsrv_free_stmt($stmt);
    
    // AHORA limpiar TODO el buffer y enviar headers limpios
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Nombre del archivo
    $fecha = date('Y-m-d_H-i-s');
    $filename = "Codigos_Referencia_" . $fecha . ".xls";
    
    // Headers para descarga
    header_remove(); // Eliminar TODOS los headers previos
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    header("Content-Length: " . strlen($excelContent));
    header("Cache-Control: private");
    header("Pragma: private");
    
    // Enviar contenido
    echo $excelContent;
    
    // Log
    error_log("Export códigos exitoso: {$_SESSION['usuario']}, Total: $contador");
    
} catch (Exception $e) {
    // Limpiar buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    error_log("Error export: " . $e->getMessage());
    
    header("Content-Type: text/html; charset=UTF-8");
    echo "<h1>Error al exportar</h1><p>Por favor intente nuevamente.</p>";
}

exit();
?>
