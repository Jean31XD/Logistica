<?php
// Script de diagnóstico para verificar rutas
echo "<h2>Diagnóstico de Rutas</h2>";

echo "<p><strong>Directorio actual del script:</strong> " . __DIR__ . "</p>";
echo "<p><strong>Archivo actual:</strong> " . __FILE__ . "</p>";

$logicaPath = '../../Logica';
echo "<p><strong>Ruta relativa a Logica:</strong> " . $logicaPath . "</p>";

$fullPath = __DIR__ . '/../../Logica/procesar_filtros_ajax.php';
echo "<p><strong>Ruta completa calculada:</strong> " . $fullPath . "</p>";

if (file_exists($fullPath)) {
    echo "<p style='color:green;'>✅ El archivo procesar_filtros_ajax.php EXISTE en la ruta calculada</p>";
} else {
    echo "<p style='color:red;'>❌ El archivo procesar_filtros_ajax.php NO EXISTE en la ruta calculada</p>";
}

// Verificar acceso HTTP
echo "<hr>";
echo "<h3>Prueba de acceso HTTP</h3>";
echo "<p>Intenta acceder a estas URLs manualmente:</p>";
echo "<ul>";
echo "<li><a href='../../Logica/procesar_filtros_ajax.php?desde=2026-01-13&hasta=2026-01-13&page=1' target='_blank'>../../Logica/procesar_filtros_ajax.php (Ruta relativa)</a></li>";
echo "</ul>";

echo "<hr>";
echo "<h3>Prueba de JavaScript</h3>";
?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
const LOGICA_PATH = '../../Logica';
console.log('LOGICA_PATH:', LOGICA_PATH);

const fullUrl = LOGICA_PATH + '/procesar_filtros_ajax.php';
console.log('Full URL:', fullUrl);

// Intenta hacer una petición AJAX
$.ajax({
    url: fullUrl + '?desde=2026-01-13&hasta=2026-01-13&page=1',
    type: 'GET',
    success: function(response) {
        console.log('✅ AJAX exitoso!', response);
        $('body').append('<p style="color:green;">✅ Petición AJAX exitosa!</p>');
    },
    error: function(jqXHR, textStatus, errorThrown) {
        console.error('❌ Error en AJAX:', textStatus, errorThrown);
        console.error('Status:', jqXHR.status);
        console.error('Response:', jqXHR.responseText);
        $('body').append('<p style="color:red;">❌ Error en AJAX: ' + textStatus + ' - ' + errorThrown + '</p>');
        $('body').append('<p>Status: ' + jqXHR.status + '</p>');
    }
});
</script>
