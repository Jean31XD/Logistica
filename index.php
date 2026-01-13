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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
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
            -webkit-tap-highlight-color: transparent;
        }

        :root {
            --primary: #E63946;
            --primary-dark: #D62839;
            --accent: #457B9D;
            --accent-dark: #1D3557;
            --text-dark: #1a202c;
            --text-light: #f7fafc;
            --radius-lg: 32px;
            --radius-md: 16px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--accent-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            position: relative;
            overflow-x: hidden;
            background: radial-gradient(circle at top right, #1d3557, #1a1a2e);
        }

        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1000px;
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            background: #ffffff;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.6);
            animation: slideUp 0.7s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Panel izquierdo - Branding */
        .branding-panel {
            background: var(--primary);
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        /* Efectos de fondo en el branding */
        .branding-panel::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            top: -50px;
            right: -50px;
        }

        .logo-container {
            position: relative;
            z-index: 2;
            margin-bottom: 32px;
        }

        .logo-container img {
            max-width: 280px;
            height: auto;
            filter: drop-shadow(0 10px 20px rgba(0, 0, 0, 0.2));
            transition: transform 0.3s ease;
        }

        .branding-text {
            position: relative;
            z-index: 2;
        }

        .branding-text h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 12px;
            letter-spacing: -1px;
            line-height: 1.1;
        }

        .branding-text p {
            font-size: 1.15rem;
            opacity: 0.9;
            font-weight: 400;
            max-width: 320px;
        }

        /* Panel derecho - Login */
        .login-panel {
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
            position: relative;
        }

        .login-header {
            margin-bottom: 40px;
            text-align: center;
        }

        .login-header h2 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .login-header p {
            color: #64748b;
            font-size: 1rem;
            line-height: 1.6;
            max-width: 380px;
            margin: 0 auto;
        }

        .error-alert {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            border-left: 5px solid #e11d48;
            color: #9f1239;
            padding: 18px;
            border-radius: 16px;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 0.95rem;
            animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
        }

        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }

        .btn-microsoft {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            width: 100%;
            padding: 20px 24px;
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 20px;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-dark);
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            cursor: pointer;
        }

        .btn-microsoft:hover {
            border-color: #0078d4;
            background-color: #f8fafc;
            transform: translateY(-3px);
            box-shadow: 0 15px 30px -5px rgba(0, 120, 212, 0.2);
        }

        .btn-microsoft:active {
            transform: translateY(-1px);
        }

        .btn-microsoft svg {
            width: 26px;
            height: 26px;
            flex-shrink: 0;
        }

        .login-footer {
            margin-top: 48px;
            text-align: center;
            font-size: 0.85rem;
            color: #94a3b8;
            font-weight: 500;
        }

        /* === RESPONSIVE DESIGN === */
        
        /* Tablets y Laptops Pequeñas */
        @media (max-width: 960px) {
            .login-wrapper {
                max-width: 800px;
                grid-template-columns: 1fr 1fr;
            }
            .branding-panel, .login-panel {
                padding: 40px;
            }
            .branding-text h1 { font-size: 2rem; }
        }

        /* Celulares (Layout Vertical) */
        @media (max-width: 680px) {
            body {
                padding: 0;
                align-items: flex-end; /* Efecto hoja que sale de abajo */
            }

            .login-wrapper {
                grid-template-columns: 1fr;
                border-radius: var(--radius-lg) var(--radius-lg) 0 0;
                min-height: 85vh;
                max-width: 100%;
            }

            .branding-panel {
                padding: 50px 30px;
                min-height: 35vh;
            }

            .logo-container {
                margin-bottom: 24px;
            }

            .logo-container img {
                max-width: 200px;
            }

            .branding-text h1 {
                font-size: 2.2rem;
                margin-bottom: 8px;
            }

            .branding-text p {
                font-size: 1rem;
                max-width: 100%;
            }

            .login-panel {
                padding: 40px 24px 60px;
                background: #ffffff;
                border-radius: var(--radius-lg) var(--radius-lg) 0 0;
                margin-top: -30px; /* Superponer ligeramente */
                z-index: 3;
            }

            .login-header h2 {
                font-size: 1.75rem;
            }

            .login-header p {
                font-size: 0.95rem;
            }

            .btn-microsoft {
                padding: 22px 20px; /* Botón más grande para touch */
                font-size: 1.05rem;
                border-radius: 18px;
            }

            .login-footer {
                margin-top: auto; /* Empujar al fondo en móviles */
                padding-top: 30px;
            }
        }

        /* Celulares Pequeños */
        @media (max-width: 380px) {
            .branding-text h1 { font-size: 1.8rem; }
            .login-header h2 { font-size: 1.5rem; }
            .branding-panel { min-height: 30vh; }
        }

        /* Soporte para modo oscuro del sistema (opcional pero premium) */
        @media (prefers-color-scheme: dark) {
            /* Mantenemos el branding rojo, pero el panel de login puede ser darkish */
            /* .login-panel { background: #1a1a2e; color: white; } */
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
            <p>Tu plataforma de gestión logística avanzada para el control total de operaciones.</p>
        </div>
    </div>

    <!-- Panel de Login -->
    <div class="login-panel">
        <div class="login-header">
            <h2>Acceso</h2>
            <p>Bienvenido. Por favor utiliza tu cuenta corporativa para ingresar al sistema.</p>
        </div>

        <?php if (!empty($errorLogin)): ?>
        <div class="error-alert">
            <i class="fas fa-exclamation-circle" style="font-size: 1.4rem;"></i>
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
            <span>Continuar con Microsoft</span>
        </a>

        <div class="login-footer">
            <p>&copy; <?= date('Y') ?> MACO Logística.</p>
            <p style="font-size: 0.75rem; margin-top: 5px; opacity: 0.7;">V 2.5.0 • Seguridad Activa</p>
        </div>
    </div>
</div>

<script>
    // Prevenir caché de página al volver atrás y asegurar refresco
    window.addEventListener("pageshow", function(event) {
        if (event.persisted || (typeof window.performance != "undefined" && window.performance.navigation.type === 2)) {
            window.location.reload(true);
        }
    });

    // Feedback visual al hacer click en el botón (prevención de múltiples clicks)
    const loginBtn = document.querySelector('.btn-microsoft');
    loginBtn.addEventListener('click', function() {
        this.style.opacity = '0.7';
        this.style.pointerEvents = 'none';
        this.querySelector('span').textContent = 'Conectando...';
    });
</script>

</body>
</html>
