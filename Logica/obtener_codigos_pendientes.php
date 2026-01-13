<?php
/**
 * Endpoint para obtener códigos de verificación pendientes para el usuario actual
 * Este endpoint es consultado por polling desde el frontend
 */

require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();

header('Content-Type: application/json');

require_once __DIR__ . '/../conexionBD/conexion.php';

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión.']);
    exit();
}

$usuarioActual = $_SESSION['usuario'];

// Buscar códigos pendientes para el usuario actual (no usados y no expirados)
$sql = "SELECT codigo, ticket, expira, creado 
        FROM codigos_verificacion 
        WHERE usuario = ? 
        AND usado = 0 
        AND expira > GETDATE()
        ORDER BY creado DESC";

$stmt = sqlsrv_query($conn, $sql, [$usuarioActual]);

$codigosPendientes = [];

if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Calcular tiempo restante correctamente
        $expira = $row['expira'];
        $ahora = new DateTime();
        
        // Calcular diferencia en segundos
        $segundosRestantes = $expira->getTimestamp() - $ahora->getTimestamp();
        
        // Solo incluir códigos que aún no han expirado
        if ($segundosRestantes > 0) {
            $codigosPendientes[] = [
                'codigo' => $row['codigo'],
                'ticket' => $row['ticket'],
                'expira' => $expira->format('H:i:s'),
                'segundos_restantes' => $segundosRestantes,
                'creado' => $row['creado']->format('H:i:s')
            ];
        }
    }
}

// Limpiar códigos expirados del usuario
$sqlLimpiar = "DELETE FROM codigos_verificacion WHERE usuario = ? AND (expira < GETDATE() OR usado = 1)";
sqlsrv_query($conn, $sqlLimpiar, [$usuarioActual]);

sqlsrv_close($conn);

echo json_encode([
    'success' => true,
    'codigos' => $codigosPendientes,
    'tiene_pendientes' => count($codigosPendientes) > 0
]);
?>
