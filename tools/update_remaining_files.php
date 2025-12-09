<?php
/**
 * Script de Ayuda para Actualizar Archivos Restantes
 * Este script te ayuda a identificar qué archivos necesitan ser actualizados
 */

// Archivos que ya fueron actualizados
$archivos_actualizados = [
    'index.php',
    'conexionBD/conexion.php',
    'Logica/buscar.php',
    'Logica/actualizar_estado.php',
    'Logica/actualizar_validar.php',
    'Logica/actualizar_estatus.php',
    'Logica/asignar_ticket.php',
];

// Buscar todos los archivos PHP en Logica/
$archivos_logica = glob(__DIR__ . '/../Logica/*.php');
$archivos_view = glob(__DIR__ . '/../View/*.php');

echo "=== ANÁLISIS DE ARCHIVOS PENDIENTES ===\n\n";

// Archivos en Logica/ que necesitan actualización
echo "📁 ARCHIVOS EN Logica/ POR ACTUALIZAR:\n";
echo str_repeat("-", 60) . "\n";

foreach ($archivos_logica as $archivo) {
    $nombre_relativo = 'Logica/' . basename($archivo);

    if (!in_array($nombre_relativo, $archivos_actualizados)) {
        $contenido = file_get_contents($archivo);

        // Detectar si tiene verificación de sesión
        $tiene_session_check = (
            strpos($contenido, 'verificarAutenticacion') !== false ||
            (strpos($contenido, 'session_start') !== false &&
             strpos($contenido, '$_SESSION[\'usuario\']') !== false)
        );

        // Detectar si usa POST
        $usa_post = strpos($contenido, '$_POST') !== false;

        // Detectar si tiene CSRF
        $tiene_csrf = (
            strpos($contenido, 'csrf_token') !== false ||
            strpos($contenido, 'verificarTokenCSRF') !== false
        );

        echo "\n📄 " . basename($archivo) . "\n";
        echo "   └─ Verificación de sesión: " . ($tiene_session_check ? "✅" : "❌ FALTA") . "\n";

        if ($usa_post) {
            echo "   └─ Usa POST: ✅\n";
            echo "   └─ Protección CSRF: " . ($tiene_csrf ? "✅" : "❌ FALTA") . "\n";
        }
    }
}

echo "\n\n📁 ARCHIVOS EN View/ POR REVISAR:\n";
echo str_repeat("-", 60) . "\n";

foreach ($archivos_view as $archivo) {
    $contenido = file_get_contents($archivo);

    // Detectar si tiene verificación de sesión
    $tiene_session_check = (
        strpos($contenido, 'verificarAutenticacion') !== false ||
        (strpos($contenido, 'session_start') !== false &&
         strpos($contenido, '$_SESSION[\'usuario\']') !== false)
    );

    // Detectar si tiene formularios POST
    $tiene_form_post = (
        strpos($contenido, 'method="POST"') !== false ||
        strpos($contenido, "method='POST'") !== false
    );

    // Detectar si tiene CSRF
    $tiene_csrf = (
        strpos($contenido, 'csrf_token') !== false ||
        strpos($contenido, 'campoTokenCSRF') !== false
    );

    echo "\n📄 " . basename($archivo) . "\n";
    echo "   └─ Verificación de sesión: " . ($tiene_session_check ? "✅" : "❌ REVISAR") . "\n";

    if ($tiene_form_post) {
        echo "   └─ Tiene formularios POST: ✅\n";
        echo "   └─ Protección CSRF: " . ($tiene_csrf ? "✅" : "❌ FALTA") . "\n";
    }
}

echo "\n\n=== PLANTILLA PARA ACTUALIZAR ARCHIVOS ===\n\n";

echo "Para archivos en Logica/ (endpoints AJAX/POST):\n";
echo str_repeat("-", 60) . "\n";
echo <<<'PHP'
<?php
require_once __DIR__ . '/../config/auth_middleware.php';
require_once __DIR__ . '/../config/csrf_helper.php';
require_once __DIR__ . '/../config/security_headers.php';
require_once __DIR__ . '/../conexionBD/conexion.php';

session_start();

// Verificar autenticación (ajustar pantallas según sea necesario)
verificarAutenticacion([], true); // true = es AJAX

// Si es POST, verificar método y CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarMetodoPOST();
    validarCSRF(true);
}

// Sanitizar entradas
$param1 = sanitizarEntrada($_POST['param1'] ?? null, 'string');
$param2 = sanitizarEntrada($_POST['param2'] ?? null, 'int');

// ... resto del código ...
?>
PHP;

echo "\n\nPara archivos en View/ (páginas HTML):\n";
echo str_repeat("-", 60) . "\n";
echo <<<'PHP'
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security_headers.php';
require_once __DIR__ . '/../config/auth_middleware.php';
require_once __DIR__ . '/../config/csrf_helper.php';

session_start();

// Verificar autenticación (ajustar pantallas según permisos requeridos)
verificarAutenticacion([0, 1, 5]); // Ejemplo: solo pantallas 0, 1 y 5

// ... resto del código ...
?>

<!DOCTYPE html>
<html>
<head>...</head>
<body>

<form method="POST" action="procesar.php">
    <?php echo campoTokenCSRF(); ?>
    <!-- resto del formulario -->
</form>

<!-- Para AJAX, incluir token en el HTML y enviarlo con fetch -->
<script>
const csrfToken = '<?php echo generarTokenCSRF(); ?>';

fetch('endpoint.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
        csrf_token: csrfToken,
        dato1: valor1
    })
});
</script>

</body>
</html>
PHP;

echo "\n\n✅ ANÁLISIS COMPLETO\n";
echo "Revisa la salida anterior para identificar archivos que necesitan actualización.\n";
?>
