<?php
// PR-Catalogo/src/Auth.php

use TheNetworg\OAuth2\Client\Provider\Azure;

class Auth {
    public $provider; // La hacemos pública para acceder desde el script de login

    public function __construct(array $config) {
        $this->provider = new Azure([
            'clientId'          => $config['clientId'],
            'clientSecret'      => $config['clientSecret'],
            'redirectUri'       => $config['redirectUri'],
            'tenant'            => $config['tenantId'],
            'graphApiVersion'   => 'v1.0',
        ]);
    }

    public function login() {
        // --- INICIO DE LA LÓGICA PKCE ---

        // 1. Generar un "code_verifier" aleatorio y seguro.
        $code_verifier = bin2hex(random_bytes(64));

        // 2. Guardar el verifier en la sesión. Esta es la única variable que necesitamos que persista.
        $_SESSION['pkce_code_verifier'] = $code_verifier;

        // 3. Crear el "code_challenge" a partir del verifier (hash SHA256).
        $code_challenge = rtrim(strtr(base64_encode(hash('sha256', $code_verifier, true)), '+/', '-_'), '=');
        
        // 4. Obtener la URL de autorización añadiendo los parámetros PKCE.
        $authUrl = $this->provider->getAuthorizationUrl([
            'scope' => ['openid', 'profile', 'email', 'user.read'],
            'code_challenge' => $code_challenge,
            'code_challenge_method' => 'S256',
        ]);
        
        // Limpiamos el estado anterior por si acaso
        unset($_SESSION['oauth2state']);

        // --- FIN DE LA LÓGICA PKCE ---
        
        header('Location: ' . $authUrl);
        exit();
    }

    public function handleCallback() {
        // Verificar que la sesión con el verifier no se haya perdido.
        if (empty($_SESSION['pkce_code_verifier'])) {
            throw new Exception('El verificador de código PKCE se ha perdido. El problema de la sesión en el servidor persiste de forma crítica.');
        }

        try {
            // --- AÑADIR EL VERIFIER AL SOLICITAR EL TOKEN ---
            $token = $this->provider->getAccessToken('authorization_code', [
                'code' => $_GET['code'],
                'code_verifier' => $_SESSION['pkce_code_verifier'], // Se envía el verifier
            ]);

            // Limpiar el verifier de la sesión una vez usado
            unset($_SESSION['pkce_code_verifier']);

            $user = $this->provider->getResourceOwner($token);
            $userData = $user->toArray();

            // 1. Intentar con 'displayName'
            $userName = $userData['displayName'] ?? null; 
            
            // 2. Si está vacío, intentar con 'name' (muy común)
            if (empty($userName)) {
                $userName = $userData['name'] ?? null;
            }

            // 3. Si sigue vacío, usar el email como último recurso
            if (empty($userName)) {
                $userName = $userData['mail'] ?? $userData['userPrincipalName'] ?? 'Usuario Desconocido';
            }
            
            $_SESSION['user'] = [
                'name'  => $userName, // Usar la variable que encontramos
                'email' => $userData['mail'] ?? $userData['userPrincipalName'],
                'id'    => $user->getId(),
            ];
            
        } catch (Exception $e) {
            throw new Exception('Error al obtener el token de acceso: ' . $e->getMessage());
        }
    }

    // --- INICIO DE LA MODIFICACIÓN: MÉTODO LOGOUT DEFINITIVO ---

    public function logout() {
        // 1. Asegurarse de que la sesión esté iniciada (bootstrap ya lo hizo)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 2. Borrar todas las variables de la sesión
        $_SESSION = [];

        // 3. Borrar la cookie de sesión del navegador.
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // 4. Destruir la sesión en el servidor.
        session_destroy();

        // 5. Construir la URL de "post-logout"
        // Esta será nuestra página de inicio. Usamos tu .env para construirla
        // de forma fiable.
        $postLogoutRedirectUri = str_replace('public/callback.php', 'index.php', $_ENV['AZURE_REDIRECT_URI']); //

        // 6. Obtener la URL de logout de Azure
        // La librería 'thenetworg/oauth2-azure' nos da esta función
        $logoutUrl = $this->provider->getLogoutUrl($postLogoutRedirectUri); //

        // 7. Redirigir al usuario a Microsoft para cerrar sesión
        header('Location: ' . $logoutUrl);
        exit();
    }
    
    // --- FIN DE LA MODIFICACIÓN ---

    public static function isAuthenticated(): bool {
        return isset($_SESSION['user']);
    }

    public static function getUserName(): ?string {
        return self::isAuthenticated() ? $_SESSION['user']['name'] : null;
    }
}
?>