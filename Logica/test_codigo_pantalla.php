<?php
/**
 * Script de prueba para el sistema de códigos en pantalla
 * Genera un código de prueba para el usuario actual
 */

require_once __DIR__ . '/../conexionBD/session_config.php';
verificarAutenticacion();

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../conexionBD/conexion.php';

$usuarioActual = $_SESSION['usuario'];

echo "<h1>🧪 Prueba del Sistema de Códigos en Pantalla</h1>";
echo "<p>Usuario actual: <strong>$usuarioActual</strong></p>";
echo "<hr>";

// Generar un código de prueba para el usuario actual
$codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$ticket = 'TEST-' . rand(1000, 9999);
$expira = new DateTime();
$expira->add(new DateInterval('PT5M'));

echo "<h2>1️⃣ Generando código de prueba...</h2>";

$sqlInsert = "INSERT INTO codigos_verificacion (codigo, usuario, ticket, expira, ip_solicitud) VALUES (?, ?, ?, ?, ?)";
$paramsInsert = [
    $codigo,
    $usuarioActual,
    $ticket,
    $expira->format('Y-m-d H:i:s'),
    $_SERVER['REMOTE_ADDR'] ?? 'test'
];

$stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);

if ($stmtInsert === false) {
    $errors = sqlsrv_errors();
    echo "<p style='color:red'>❌ Error al insertar: " . print_r($errors, true) . "</p>";
} else {
    echo "<p style='color:green'>✅ Código insertado correctamente</p>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><td>Código</td><td><strong style='font-size:2em;letter-spacing:5px;'>$codigo</strong></td></tr>";
    echo "<tr><td>Usuario</td><td>$usuarioActual</td></tr>";
    echo "<tr><td>Ticket</td><td>$ticket</td></tr>";
    echo "<tr><td>Expira</td><td>" . $expira->format('H:i:s') . "</td></tr>";
    echo "</table>";
}

echo "<h2>2️⃣ Verificando códigos pendientes...</h2>";

$sql = "SELECT codigo, ticket, expira, creado, usado
        FROM codigos_verificacion 
        WHERE usuario = ? 
        ORDER BY creado DESC";

$stmt = sqlsrv_query($conn, $sql, [$usuarioActual]);

if ($stmt === false) {
    $errors = sqlsrv_errors();
    echo "<p style='color:red'>❌ Error en consulta: " . print_r($errors, true) . "</p>";
} else {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Código</th><th>Ticket</th><th>Expira</th><th>Creado</th><th>Usado</th><th>Estado</th></tr>";
    
    $encontrados = 0;
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $encontrados++;
        $expiraRow = $row['expira'];
        $ahora = new DateTime();
        $expirado = $expiraRow < $ahora;
        $usado = $row['usado'] == 1;
        
        $estado = '';
        if ($usado) {
            $estado = '❌ Usado';
        } elseif ($expirado) {
            $estado = '⏰ Expirado';
        } else {
            $estado = '✅ Activo';
        }
        
        echo "<tr>";
        echo "<td><strong>" . $row['codigo'] . "</strong></td>";
        echo "<td>" . $row['ticket'] . "</td>";
        echo "<td>" . $expiraRow->format('H:i:s') . "</td>";
        echo "<td>" . $row['creado']->format('H:i:s') . "</td>";
        echo "<td>" . ($row['usado'] ? 'Sí' : 'No') . "</td>";
        echo "<td>$estado</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if ($encontrados == 0) {
        echo "<p style='color:orange'>⚠️ No hay códigos en la base de datos para este usuario</p>";
    } else {
        echo "<p>Total de códigos: $encontrados</p>";
    }
}

echo "<h2>3️⃣ Probando endpoint de polling...</h2>";
echo "<p>Ahora ve a la página de <a href='../View/modulos/Despacho_factura.php' target='_blank'>Despacho de Tickets</a></p>";
echo "<p>Deberías ver un toast de notificación en la esquina superior derecha con el código: <strong style='font-size:1.5em;'>$codigo</strong></p>";

sqlsrv_close($conn);
?>

<hr>
<h2>📋 Información de Depuración</h2>
<p>Si el toast no aparece, revisa la consola del navegador (F12) para ver si hay errores JavaScript.</p>
<p>El polling consulta <code>obtener_codigos_pendientes.php</code> cada 3 segundos.</p>
