<?php
/**
 * Headers de Seguridad HTTP
 * Protege contra ataques comunes: XSS, Clickjacking, MIME Sniffing, etc.
 */

function aplicarHeadersSeguridad() {
    // Prevenir MIME type sniffing
    header("X-Content-Type-Options: nosniff");

    // Prevenir clickjacking - no permitir que el sitio sea embebido en iframes
    header("X-Frame-Options: DENY");

    // Activar protección XSS del navegador
    header("X-XSS-Protection: 1; mode=block");

    // Política de referrer - no enviar información sensible en el referrer
    header("Referrer-Policy: strict-origin-when-cross-origin");

    // Política de permisos - restringir acceso a APIs del navegador
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

    // Content Security Policy - prevenir XSS y otros ataques de inyección
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com",
        "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com",
        "img-src 'self' data: https: blob:",
        "connect-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fonts.gstatic.com https://catalogodeimagenes.blob.core.windows.net",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'"
    ]);
    header("Content-Security-Policy: " . $csp);

    // HSTS - Forzar HTTPS (solo si estamos en HTTPS)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    }

    // Prevenir que el navegador guarde en caché páginas sensibles
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");
}

// Aplicar headers automáticamente cuando se incluye este archivo
aplicarHeadersSeguridad();
?>
