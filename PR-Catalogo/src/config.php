<?php
// PR-Catalogo/src/config.php

require_once __DIR__ . '/../vendor/autoload.php';

// Cargar variables de entorno desde el archivo .env en la raíz del proyecto
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    die('Error: No se pudo encontrar el archivo .env. Por favor, crea uno a partir de .env.example y añade tus credenciales de Azure.');
}

// Configuración de la aplicación
$config = [
    'azure' => [
        'clientId'                => $_ENV['AZURE_CLIENT_ID'],
        'clientSecret'            => $_ENV['AZURE_CLIENT_SECRET'],
        'redirectUri'             => $_ENV['AZURE_REDIRECT_URI'],
        'tenantId'                => $_ENV['AZURE_TENANT_ID'],
        'urlAuthorize'            => 'https://login.microsoftonline.com/' . $_ENV['AZURE_TENANT_ID'] . '/oauth2/v2.0/authorize',
        'urlAccessToken'          => 'https://login.microsoftonline.com/' . $_ENV['AZURE_TENANT_ID'] . '/oauth2/v2.0/token',
        'urlResourceOwnerDetails' => 'https://graph.microsoft.com/v1.0/me',
    ],
    // Podrías añadir más configuraciones aquí en el futuro
];

?>