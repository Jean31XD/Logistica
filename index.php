<?php
// --- CONFIGURACIÓN DE SESIÓN Y CABECERAS ---
ini_set('session.cookie_lifetime', 0); // La cookie de sesión dura hasta que se cierra el navegador
ini_set('session.gc_maxlifetime', 1800); // La sesión en el servidor dura 30 minutos

session_start();

// --- CABECERAS PARA EVITAR CACHÉ ---
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

// --- FUNCIÓN DE CONEXIÓN A LA BASE DE DATOS ---
function conectarBD()
{
    // Es recomendable mover estas credenciales a variables de entorno o un archivo de configuración no accesible públicamente.
    $serverName = "sdb-apptransportistas-maco.privatelink.database.windows.net";
    $database   = "db-apptransportistas-maco";
    $username   = "ServiceAppTrans";
    $password   = "⁠nZ(#n41LJm)iLmJP"; 

    $connectionInfo = array(
        "Database" => $database,
        "UID" => $username,
        "PWD" => $password,
        "TrustServerCertificate" => true, // Necesario para Azure SQL
        "CharacterSet" => "UTF-8"
    );

    $conn = sqlsrv_connect($serverName, $connectionInfo);
    if ($conn === false) {
        // En un entorno de producción, registra el error en un log en lugar de mostrarlo.
        error_log(print_r(sqlsrv_errors(), true));
        // Muestra un mensaje genérico al usuario.
        die("<div class='alert alert-danger'>❌ Error de conexión con el servidor. Por favor, contacte al administrador.</div>");
    }
    return $conn;
}

$conn = conectarBD();
$errorLogin = "";
$tiempo_espera = 1 * 60; // 1 minuto de espera

// --- LÓGICA DE BLOQUEO POR INTENTOS FALLIDOS ---
if (!isset($_SESSION['intentos_login'])) {
    $_SESSION['intentos_login'] = 0;
}

if ($_SESSION['intentos_login'] >= 5) {
    $ultimo_intento = $_SESSION['ultimo_intento'] ?? 0;
    $tiempo_transcurrido = time() - $ultimo_intento;

    if ($tiempo_transcurrido < $tiempo_espera) {
        $seg_rest = $tiempo_espera - $tiempo_transcurrido;
        $errorLogin = "Demasiados intentos fallidos. Espera $seg_rest segundos para volver a intentar.";
    } else {
        // Si ya pasó el tiempo, resetea los intentos
        $_SESSION['intentos_login'] = 0;
        unset($_SESSION['ultimo_intento']);
    }
}


// --- PROCESAMIENTO DEL FORMULARIO DE LOGIN ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && empty($errorLogin)) {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    $sql = "SELECT usuario, password, pantalla FROM usuarios WHERE usuario = ?";
    $params = array($usuario);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $errorLogin = "Error en la consulta a la base de datos.";
    } else {
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Verifica la contraseña hasheada
            if (password_verify($password, $row['password'])) {
                // --- AUTENTICACIÓN EXITOSA ---
                session_regenerate_id(true); // Previene la fijación de sesión
                $_SESSION['usuario'] = $row['usuario'];
                $_SESSION['pantalla'] = $row['pantalla'];

                // Limpia los contadores de intentos
                unset($_SESSION['intentos_login']);
                unset($_SESSION['ultimo_intento']);

                // Redirige según el perfil del usuario
                switch ($row['pantalla']) {
                    case 0: header("Location: View/Admin.php"); break;
                    case 1: header("Location: View/Inicio.php"); break;
                    case 2: header("Location: View/facturas.php"); break;
                    case 3: header("Location: View/CXC.php"); break;
                    case 4: header("Location: View/Reporte.php"); break;
                    case 5: header("Location: View/Paneladmin.php"); break;
                    case 6: header("Location: View/BI.php"); break;
                    default: header("Location: View/Inicio.php"); break; // Redirección por defecto
                }
                exit(); // Termina el script después de la redirección
            } else {
                // Contraseña incorrecta
                $_SESSION['intentos_login']++;
                $_SESSION['ultimo_intento'] = time();
                $errorLogin = "Usuario o contraseña incorrectos.";
            }
        } else {
            // Usuario no encontrado
            $_SESSION['intentos_login']++;
            $_SESSION['ultimo_intento'] = time();
            $errorLogin = "Usuario o contraseña incorrectos.";
        }
        sqlsrv_free_stmt($stmt);
    }
    sqlsrv_close($conn);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Iniciar Sesión </title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #0d6efd;
            --danger-color: #dc3545;
        }

        body {
            font-family: 'Poppins', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #fff;
            /* Fondo animado de gradiente */
            background: linear-gradient(-45deg, #ff0000ff, #cb1717ef, #bb1b1bff, #751010ff);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Estilo Glassmorphism para el contenedor de login */
        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 3rem 2.5rem;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1.5rem;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease;
        }

        .login-container:hover {
            transform: translateY(-5px);
        }
        
        .login-title {
            font-weight: 700;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.2);
        }

        .form-control {
            background-color: rgba(255, 255, 255, 0.2) !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
            color: #fff !important;
            border-radius: 0.5rem;
            padding-left: 2.5rem; /* Espacio para el ícono */
        }

        .form-control::placeholder {
            color: rgba(0, 0, 0, 0.7);
        }

        .form-control:focus {
            background-color: rgba(255, 255, 255, 0.3) !important;
            color: #fff !important;
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.3);
        }

        /* Contenedor para el ícono dentro del input */
        .input-group-text {
            background-color: transparent !important;
            border: none !important;
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            color: rgba(255, 255, 255, 0.8);
        }

        .btn-login {
            font-weight: 600;
            border-radius: 0.5rem;
            padding: 0.75rem;
            background-color: white;
            border-color: white;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-login:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
        }

        .alert-custom {
            background: rgba(220, 53, 69, 0.25); /* Rojo semi-transparente */
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #fff;
            border-radius: 0.5rem;
            font-size: 0.9rem;
        }

    </style>
</head>
<body>
    
<div class="login-container animate__animated animate__fadeInUp">
    <form method="POST" action="">
        <div class="text-center mb-4">
             <img src="IMG/LOGO MC - BLANCO.png" class="img-fluid mb-3" alt="LOGO" style="max-width: 300px;">
             <h1 class="h3 mb-3 login-title">Bienvenido</h1>
        </div>

        <?php if (!empty($errorLogin)): ?>
            <div class="alert alert-custom text-center" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($errorLogin) ?>
            </div>
        <?php endif; ?>

        <div class="position-relative mb-3">
            <i class="fa fa-user input-group-text"></i>
            <input type="text" name="usuario" class="form-control" placeholder="Usuario" required autocomplete="username" />
        </div>
        
        <div class="position-relative mb-4">
            <i class="fa fa-lock input-group-text"></i>
            <input type="password" name="password" class="form-control" placeholder="Contraseña" required autocomplete="current-password" />
        </div>
        
        <button type="submit" class="btn btn-login w-100">
            <i class="fa-solid fa-right-to-bracket me-2"></i>Iniciar sesión
        </button>
    </form>
</div>

<script>
    // Script para forzar la recarga de la página y evitar problemas de caché al usar el botón "atrás" del navegador.
    window.addEventListener("pageshow", function(event) {
        var historyTraversal = event.persisted || 
                               (typeof window.performance != "undefined" && 
                                window.performance.navigation.type === 2);
        if (historyTraversal) {
            window.location.reload(true);
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>