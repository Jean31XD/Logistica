<?php
/**
 * Helper para enviar correos usando Microsoft Graph API
 * Requiere: AZURE_CLIENT_ID, AZURE_CLIENT_SECRET, AZURE_TENANT_ID en .env
 */

/**
 * Obtener token de acceso para Microsoft Graph
 * @return string|null Access token o null si falla
 */
function getGraphAccessToken() {
    $tenantId = getenv('AZURE_TENANT_ID');
    $clientId = getenv('AZURE_CLIENT_ID');
    $clientSecret = getenv('AZURE_CLIENT_SECRET');

    if (empty($tenantId) || empty($clientId) || empty($clientSecret)) {
        error_log("Email Helper: Credenciales de Azure no configuradas");
        return null;
    }

    $tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";

    $postData = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'scope' => 'https://graph.microsoft.com/.default',
        'grant_type' => 'client_credentials'
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        error_log("Email Helper: Error obteniendo token - HTTP $httpCode");
        return null;
    }

    $tokenData = json_decode($response, true);
    return $tokenData['access_token'] ?? null;
}

/**
 * Enviar correo usando Microsoft Graph API
 * @param string $to Dirección de correo destino
 * @param string $subject Asunto del correo
 * @param string $bodyHtml Cuerpo del correo en HTML
 * @param string $fromEmail Email del remitente (debe tener permisos en Azure)
 * @return bool True si se envió correctamente, False si falló
 */
function enviarCorreoGraph($to, $subject, $bodyHtml, $fromEmail = null) {
    // Si no se especifica remitente, usar el email del usuario de sesión o un email por defecto
    if (empty($fromEmail)) {
        // Intentar usar el email del usuario actual de sesión
        if (isset($_SESSION['email']) && !empty($_SESSION['email'])) {
            $fromEmail = $_SESSION['email'];
        } else {
            // Fallback: usar un email que SÍ exista en el tenant de Azure
            // IMPORTANTE: Cambia esto por un email válido de tu organización
            $fromEmail = 'jean.sencion@corripio.com.do';
        }
    }

    $accessToken = getGraphAccessToken();

    if (!$accessToken) {
        error_log("Email Helper: No se pudo obtener token de acceso");
        return false;
    }

    // Construir el mensaje
    $message = [
        'message' => [
            'subject' => $subject,
            'body' => [
                'contentType' => 'HTML',
                'content' => $bodyHtml
            ],
            'toRecipients' => [
                [
                    'emailAddress' => [
                        'address' => $to
                    ]
                ]
            ]
        ],
        'saveToSentItems' => 'false'
    ];

    $graphUrl = "https://graph.microsoft.com/v1.0/users/{$fromEmail}/sendMail";

    $ch = curl_init($graphUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($message),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 202) {
        // 202 Accepted = correo en cola para envío
        return true;
    } else {
        error_log("Email Helper: Error enviando correo - HTTP $httpCode - Response: $response");
        return false;
    }
}

/**
 * Enviar código de verificación por correo
 * @param string $to Email destino
 * @param string $usuario Nombre del usuario
 * @param string $codigo Código de 6 dígitos
 * @param string $ticket Número de ticket (opcional)
 * @return bool
 */
function enviarCodigoVerificacion($to, $usuario, $codigo, $ticket = null) {
    $subject = "Código de verificación - MACO Logística";

    $ticketInfo = $ticket ? " para el ticket <strong>#{$ticket}</strong>" : "";

    $bodyHtml = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #1D3557 0%, #457B9D 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-top: none; }
            .code-box { background: #f5f5f5; border: 2px dashed #E63946; border-radius: 8px; padding: 20px; text-align: center; margin: 25px 0; }
            .code { font-size: 36px; font-weight: bold; color: #E63946; letter-spacing: 8px; font-family: 'Courier New', monospace; }
            .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin:0;'>🔐 Código de Verificación</h1>
                <p style='margin:5px 0 0;opacity:0.9;'>MACO Logística</p>
            </div>
            <div class='content'>
                <p>Hola <strong>{$usuario}</strong>,</p>

                <p>Se ha solicitado un código de verificación{$ticketInfo}.</p>

                <div class='code-box'>
                    <p style='margin:0 0 10px;font-size:14px;color:#666;'>Tu código de verificación es:</p>
                    <div class='code'>{$codigo}</div>
                </div>

                <div class='warning'>
                    <strong>⚠️ Importante:</strong>
                    <ul style='margin:10px 0 0;padding-left:20px;'>
                        <li>Este código expira en <strong>5 minutos</strong></li>
                        <li>Solo puede usarse una vez</li>
                        <li>No compartas este código con nadie</li>
                    </ul>
                </div>

                <p>Si no solicitaste este código, ignora este mensaje. Tu cuenta permanece segura.</p>
            </div>
            <div class='footer'>
                <p><strong>MACO Logística</strong> - Sistema de Gestión</p>
                <p>Este es un correo automático, por favor no responder.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return enviarCorreoGraph($to, $subject, $bodyHtml);
}
?>
