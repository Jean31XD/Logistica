<?php
ini_set('session.cookie_lifetime', 0);
ini_set('session.gc_maxlifetime', 1800);

session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/conexionBD/conexion.php';


$errorLogin = "";

$tiempo_espera = 1 * 60;

if (!isset($_SESSION['intentos_login'])) {
    $_SESSION['intentos_login'] = 0;
    $_SESSION['ultimo_intento'] = time();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($_SESSION['intentos_login'] >= 5) {
        $tiempo_transcurrido = time() - $_SESSION['ultimo_intento'];
        if ($tiempo_transcurrido < $tiempo_espera) {
            $min_rest = ceil(($tiempo_espera - $tiempo_transcurrido) / 60);
            $errorLogin = "Demasiados intentos fallidos. Espera $min_rest minutos para volver a intentar.";
        } else {
            $_SESSION['intentos_login'] = 0;
            $_SESSION['ultimo_intento'] = time();
        }
    }

    if ($_SESSION['intentos_login'] < 5 && empty($errorLogin)) {
        $sql = "SELECT usuario, password, pantalla FROM usuarios WHERE usuario = ?";
        $params = array($usuario);
        // Usar sqlsrv_prepare es más seguro, pero para la ejecución inmediata, sqlsrv_query es más directo.
        // Mantendremos tu estructura original.
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
             $errorLogin = "Error en la base de datos.";
        } else {
            if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                if (password_verify($password, $row['password'])) {
                    // Autenticación exitosa
                    session_regenerate_id(true);
                    $_SESSION['usuario'] = $row['usuario'];
                    $_SESSION['pantalla'] = $row['pantalla'];
                    unset($_SESSION['intentos_login']); // Limpiar intentos al tener éxito
                    unset($_SESSION['ultimo_intento']);

                    // Redirige según la pantalla
                    switch ($row['pantalla']) {
                        case 0: header("Location: View/Admin.php"); break;
                        case 1: header("Location: View/Inicio.php"); break;
                        case 2: header("Location: View/facturas.php"); break;
                        case 3: header("Location: View/CXC.php"); break;
                        case 4: header("Location: View/Reporte.php"); break;
                        case 5: header("Location: View/Paneladmin.php"); break;
                        case 6: header("Location: View/BI.php"); break;
                        default: header("Location: View/Inicio.php"); break; // Un default por si acaso
                    }
                    exit();
                } else {
                    $_SESSION['intentos_login']++;
                    $_SESSION['ultimo_intento'] = time();
                    $errorLogin = "Usuario o contraseña incorrectos.";
                }
            } else {
                $_SESSION['intentos_login']++;
                $_SESSION['ultimo_intento'] = time();
                $errorLogin = "Usuario o contraseña incorrectos.";
            }
            sqlsrv_free_stmt($stmt);
        }
    }

    sqlsrv_close($conn);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="Cache-Control" content="no-store" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Iniciar sesión</title>
    
    <style>
        @media (max-width: 768px) {
            /* Ocultamos el panel decorativo con el carrusel en pantallas pequeñas */
            .toggle-container {
                display: none;
            }

            /* Hacemos que el contenedor principal sea más simple y ocupe un ancho razonable */
            .container {
                width: 90%;
                max-width: 400px; /* Un ancho máximo para que no se estire demasiado */
                min-height: auto;
                box-shadow: none; /* Quitamos la sombra compleja en móviles */
                padding: 2rem 1rem;
            }

            /* Nos aseguramos que el contenedor del formulario ocupe todo el espacio disponible */
            .form-container.sign-in {
                width: 100%;
                position: static; /* Anulamos cualquier posicionamiento absoluto del CSS original */
                left: 0;
                opacity: 1;
                z-index: 1;
            }
        }
    </style>
</head>
<body>
    
<div class="container" id="container">
    <div class="form-container sign-in">
        <form method="POST" action="">
            <img src="IMG/LOGO MC - NEGRO.png" class="img-fluid mb-4" alt="LOGO">

            <?php if (!empty($errorLogin)): ?>
                <div class="alert alert-danger mt-3" role="alert">
                    <?= htmlspecialchars($errorLogin) ?>
                </div>
            <?php endif; ?>

            <input type="text" name="usuario" placeholder="Usuario" required autocomplete="username" />
            <input type="password" name="password" placeholder="Contraseña" required autocomplete="current-password" />
            <button type="submit" class="btnLog btn btn-danger">Iniciar sesión</button>
        </form>
    </div>

    <?php
    // Aleatorizar las imágenes para el carrusel
    $imagenes_disponibles = range(1, 7);
    shuffle($imagenes_disponibles);
    $imagenes = array_map(fn($n) => "IMG/{$n}.jpg", $imagenes_disponibles);
    ?>

    <div class="toggle-container">
        <div class="toggle">
            <div class="toggle-panel toggle-right border bg-white p-1 rounded" style="height: 100%;">
                <div id="carouselExampleSlides" class="carousel slide" data-bs-ride="carousel" data-bs-interval="3000">
                    <div class="carousel-inner" style="height: 100%; border-radius: 0.25rem; overflow: hidden;">
                        <?php foreach ($imagenes as $index => $img): ?>
                            <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                <img src="<?= $img ?>" class="d-block w-100" style="object-fit: cover; height: 100%;" alt="Imagen decorativa <?= $index + 1 ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Script para prevenir el uso de la caché al navegar hacia atrás
    window.addEventListener("pageshow", function(event) {
        if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
            window.location.reload(true);
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>