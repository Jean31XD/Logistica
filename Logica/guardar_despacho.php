<?php
require_once '../conexionBD/conexion.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = $_POST["id"] ?? null;
    $fueSinDespachar = $_POST["fue_sin_despachar"] ?? 0;

    if ($id && $fueSinDespachar == 1) {
        $stmt = $conn->prepare("UPDATE tickets SET estado = 'Sin despachar', fecha_despacho = GETDATE() WHERE id = ?");
        $stmt->execute([$id]);
        echo "OK";
    } else {
        echo "Datos incompletos";
    }
}
