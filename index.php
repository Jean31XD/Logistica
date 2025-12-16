<?php
/**
 * Login - MACO Logística
 * Sistema de autenticación modernizado
 */
session_start();
// --- CONFIGURACIÓN DE SESIÓN Y CABECERAS ---
require_once __DIR__ . '/conexionBD/session_config.php';

// --- CSRF TOKEN ---
$csrfToken = generarTokenCSRF();

// --- CONEXIÓN A LA BASE DE DATOS ---
require_once __DIR__ . '/conexionBD/conexion.php';
$errorLogin = "";
$tiempo_espera = 1 * 60;

// --- LÓGICA DE BLOQUEO POR INTENTOS FALLIDOS ---
$ip = $_SERVER['REMOTE_ADDR'];
$max_intentos = 5;
$tiempo_bloqueo = 15; // en minutos

$sql_check_attempts = "SELECT COUNT(*) as attempts, MIN(fecha_hora) as first_attempt_time FROM log_accesos WHERE ip = ? AND tipo_intento = 'login' AND exito = 0 AND fecha_hora > DATEADD(minute, -?, GETDATE())";
$params_check_attempts = [$ip, $tiempo_bloqueo];
$stmt_check_attempts = sqlsrv_query($conn, $sql_check_attempts, $params_check_attempts);
if ($stmt_check_attempts === false) {
    // Si la consulta falla, es más seguro bloquear temporalmente que permitir el acceso.
    $errorLogin = "Error del servicio de seguridad. Intente más tarde.";
} else {
    $attempts_row = sqlsrv_fetch_array($stmt_check_attempts, SQLSRV_FETCH_ASSOC);
    $intentos_fallidos = $attempts_row['attempts'] ?? 0;

    if ($intentos_fallidos >= $max_intentos) {
        $errorLogin = "Demasiados intentos fallidos. Por favor, espere $tiempo_bloqueo minutos antes de volver a intentar.";
    }
}

// --- PROCESAMIENTO DEL FORMULARIO DE LOGIN ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && empty($errorLogin)) {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $errorLogin = "Error de validación de seguridad. Por favor, intente de nuevo.";
    } else {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    $sql = "SELECT usuario, password, pantalla FROM usuarios WHERE usuario = ?";
    $params = array($usuario);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $errorLogin = "Error en la consulta a la base de datos.";
    } else {
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if (password_verify($password, $row['password'])) {
                session_regenerate_id(true);
                $_SESSION['usuario'] = $row['usuario'];
                $_SESSION['pantalla'] = $row['pantalla'];

                // Limpiar intentos de login fallidos para esta IP
                $sqlClear = "DELETE FROM log_accesos WHERE ip = ? AND tipo_intento = 'login'";
                sqlsrv_query($conn, $sqlClear, [$ip]);

                unset($_SESSION['intentos_login']);
                unset($_SESSION['ultimo_intento']);

                // Sincronizar facturas al iniciar sesión
                $sqlSync = "{CALL SyncCustinvoicejour}";
                $stmtSync = sqlsrv_query($conn, $sqlSync);
                if ($stmtSync === false) {
                    error_log("Error al sincronizar facturas en login: " . print_r(sqlsrv_errors(), true));
                    // Continuar con el login aunque falle la sincronización
                } else {
                    sqlsrv_free_stmt($stmtSync);
                }

                switch ($row['pantalla']) {
                    case 0: header("Location: View/Admin.php"); break;
                    case 1: header("Location: View/Inicio_gestion.php"); break;
                    case 2: header("Location: View/facturas.php"); break;
                    case 3: header("Location: View/CXC.php"); break;
                    case 4: header("Location: View/Reporte.php"); break;
                    case 5: header("Location: View/Paneladmin.php"); break;
                    case 6: header("Location: View/BI.php"); break;
                    case 8: header("Location: View/Listo-etiquetas.php"); break;
                    case 9: header("Location: View/dashboard.php"); break;
                    case 10: header("Location: View/Listo_inventario.php"); break;
                    case 11: header("Location: View/Codigos_de_barras.php"); break;
                    case 12: header("Location: View/Codigos_referencia.php"); break;
                    case 13: header("Location: View/Gestion_imagenes.php"); break;
                    default: header("Location: View/Inicio.php"); break;
                }
                exit();
            } else {
                $sqlLog = "INSERT INTO log_accesos (ip, username, exito, tipo_intento) VALUES (?, ?, 0, 'login')";
                sqlsrv_query($conn, $sqlLog, [$ip, $usuario]);
                $errorLogin = "Usuario o contraseña incorrectos.";
            }
        } else {
            $sqlLog = "INSERT INTO log_accesos (ip, username, exito, tipo_intento) VALUES (?, ?, 0, 'login')";
            sqlsrv_query($conn, $sqlLog, [$ip, $usuario]);
            $errorLogin = "Usuario o contraseña incorrectos.";
        }
        sqlsrv_free_stmt($stmt);
    }
    sqlsrv_close($conn);
    }
}
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

        /* Partículas de fondo animadas */

        @keyframes particles {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }

        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1200px;
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

        .branding-panel::before {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -100px;
            right: -100px;
        }

        .branding-panel::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            bottom: -80px;
            left: -80px;
        }

        .logo-container {
            position: relative;
            z-index: 2;
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-container img {
            max-width: 280px;
            filter: drop-shadow(0 10px 30px rgba(0, 0, 0, 0.3));
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .branding-text {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .branding-text h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 16px;
            text-shadow: 0 2px 20px rgba(0, 0, 0, 0.2);
        }

        .branding-text p {
            font-size: 1.125rem;
            opacity: 0.95;
            line-height: 1.6;
        }

        .features {
            position: relative;
            z-index: 2;
            margin-top: 50px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        /* Panel derecho - Formulario */
        .login-panel {
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            margin-bottom: 40px;
        }

        .login-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .login-header p {
            color: #64748b;
            font-size: 0.95rem;
        }

        .error-alert {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            border-left: 4px solid #dc2626;
            color: #991b1b;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .input-group {
            margin-bottom: 24px;
            position: relative;
        }

        .input-label {
            display: block;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
            font-size: 0.875rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.1rem;
            z-index: 2;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: white;
            color: var(--text-dark);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(230, 57, 70, 0.1);
        }

        .form-input::placeholder {
            color: #cbd5e1;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px -10px var(--primary);
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            display: none;
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px -10px var(--primary);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login i {
            margin-right: 8px;
        }

        /* Responsive */
        @media (max-width: 968px) {
            .login-wrapper {
                grid-template-columns: 1fr;
                max-width: 500px;
            }

            .branding-panel {
                display: none;
            }

            .login-panel {
                padding: 40px 30px;
            }
        }

        @media (max-width: 480px) {
            .login-panel {
                padding: 30px 20px;
            }

            .branding-text h1 {
                font-size: 2rem;
            }

            .login-header h2 {
                font-size: 1.5rem;
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
            <p>Sistema de Gestión Integral</p>
        </div>

        <div class="features">
            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <div>
                    <strong>Gestión en Tiempo Real</strong>
                    <p style="font-size: 0.9rem; opacity: 0.9; margin: 4px 0 0 0;">
                        Control total de operaciones
                    </p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <strong>Reportes Inteligentes</strong>
                    <p style="font-size: 0.9rem; opacity: 0.9; margin: 4px 0 0 0;">
                        Análisis y métricas avanzadas
                    </p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div>
                    <strong>Seguridad Garantizada</strong>
                    <p style="font-size: 0.9rem; opacity: 0.9; margin: 4px 0 0 0;">
                        Protección de datos empresariales
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel de Login -->
    <div class="login-panel">
        <div class="login-header">
            <h2>Bienvenido de nuevo</h2>
            <p>Ingresa tus credenciales para continuar</p>
        </div>

        <?php if (!empty($errorLogin)): ?>
        <div class="error-alert">
            <i class="fas fa-exclamation-circle" style="font-size: 1.25rem;"></i>
            <span><?= htmlspecialchars($errorLogin) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <div class="input-group">
                <label class="input-label">Usuario</label>
                <div class="input-wrapper">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="usuario" class="form-input"
                           placeholder="Ingresa tu usuario" required autocomplete="username">
                </div>
            </div>

            <div class="input-group">
                <label class="input-label">Contraseña</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" class="form-input"
                           placeholder="Ingresa tu contraseña" required autocomplete="current-password">
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i>
                Iniciar Sesión
            </button>
        </form>
    </div>
</div>

<script>
    window.addEventListener("pageshow", function(event) {
        var historyTraversal = event.persisted ||
                               (typeof window.performance != "undefined" &&
                                window.performance.navigation.type === 2);
        if (historyTraversal) {
            window.location.reload(true);
        }
    });
</script>

</body>
</html>
