<?php
/**
 * Login - MACO Logística
 * Autenticación exclusiva con Microsoft
 */
session_start();
// --- CONFIGURACIÓN DE SESIÓN Y CABECERAS ---
require_once __DIR__ . '/conexionBD/session_config.php';

// --- CONEXIÓN A LA BASE DE DATOS ---
require_once __DIR__ . '/conexionBD/conexion.php';
$errorLogin = "";

// Redirigir si ya está logueado
if (isset($_SESSION['usuario'])) {
    header("Location: View/pantallas/Portal.php");
    exit();
}

// Capturar error de autenticación Microsoft
if (isset($_GET['error']) && $_GET['error'] === 'auth') {
    $errorLogin = $_SESSION['auth_error'] ?? 'Error de autenticación con Microsoft';
    unset($_SESSION['auth_error']);
}

// Opcional: Redirección automática si se desea (descomentar si se prefiere no ver el botón y pasar directo a Microsoft)
// header("Location: Logica/auth_microsoft.php"); 
// exit();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Iniciar Sesión | MACO Logística</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #E63946;
            --primary-dark: #D62839;
            --accent: #457B9D;
            --accent-dark: #1D3557;
            --text-dark: #1a202c;
            --text-light: #f7fafc;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--accent-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1000px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 50px 100px -20px rgba(0, 0, 0, 0.5),
                        0 30px 60px -30px rgba(0, 0, 0, 0.4);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Panel izquierdo - Branding */
        .branding-panel {
            background: var(--primary);
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .logo-container {
            position: relative;
            z-index: 2;
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-container img {
            max-width: 250px;
            filter: drop-shadow(0 10px 30px rgba(0, 0, 0, 0.3));
        }

        .branding-text {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .branding-text h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .branding-text p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Panel derecho - Formulario */
        .login-panel {
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }

        .login-header {
            margin-bottom: 40px;
        }

        .login-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 12px;
        }

        .login-header p {
            color: #64748b;
            font-size: 1rem;
            line-height: 1.5;
        }

        .error-alert {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            border-left: 4px solid #dc2626;
            color: #991b1b;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            text-align: left;
        }

        .btn-microsoft {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            width: 100%;
            padding: 18px 24px;
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-dark);
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-microsoft:hover {
            background: #f8fafc;
            border-color: #0078d4;
            box-shadow: 0 10px 15px -3px rgba(0, 120, 212, 0.2);
            transform: translateY(-2px);
        }

        .btn-microsoft svg {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
        }

        /* Footer info */
        .login-footer {
            margin-top: 40px;
            font-size: 0.85rem;
            color: #94a3b8;
        }

        /* Responsive */
        @media (max-width: 850px) {
            .login-wrapper {
                grid-template-columns: 1fr;
                max-width: 450px;
            }
            .branding-panel {
                padding: 40px;
            }
            .login-panel {
                padding: 40px;
            }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <!-- Panel de Branding -->
    <div class="branding-panel">
        <div class="logo-container">
            <img src="IMG/LOGO MC - BLANCO.png" alt="MACO Logo">
        </div>

        <div class="branding-text">
            <h1>MACO Logística</h1>
            <p>Tu plataforma de gestión logística avanzada</p>
        </div>
    </div>

    <!-- Panel de Login -->
    <div class="login-panel">
        <div class="login-header">
            <h2>Acceso al Sistema</h2>
            <p>Utiliza tu cuenta corporativa para acceder a la plataforma.</p>
        </div>

        <?php if (!empty($errorLogin)): ?>
        <div class="error-alert">
            <i class="fas fa-exclamation-circle" style="font-size: 1.25rem;"></i>
            <span><?= htmlspecialchars($errorLogin) ?></span>
        </div>
        <?php endif; ?>

        <a href="Logica/auth_microsoft.php" class="btn-microsoft">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 21 21">
                <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
                <rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
                <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>
                <rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
            </svg>
            <span>Iniciar Sesión con Microsoft</span>
        </a>

        <div class="login-footer">
            <p>&copy; <?= date('Y') ?> MACO Logística. Todos los derechos reservados.</p>
        </div>
    </div>
</div>

<script>
    // Prevenir caché de página al volver atrás
    window.addEventListener("pageshow", function(event) {
        if (event.persisted || (typeof window.performance != "undefined" && window.performance.navigation.type === 2)) {
            window.location.reload(true);
        }
    });
</script>

</body>
</html>
