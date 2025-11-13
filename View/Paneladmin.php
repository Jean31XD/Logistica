<?php 
session_start();
date_default_timezone_set('America/Santo_Domingo');

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Verificar que esté autenticado
if (!isset($_SESSION['usuario'])) {
    header("Location: ../index.php");
    exit();
}
require_once __DIR__ . '/../conexionBD/conexion.php';

if (!isset($_SESSION['usuario'], $_SESSION['pantalla']) || $_SESSION['pantalla'] != 5) {
    header("Location: /index.php");
    exit();
}

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Panel de Administración ✨</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet" />

    <style>
        :root {
            --primary-color: #0d6efd;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #0dcaf0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(-45deg, #cf8888ff,  #6d5656ff, #6d5656ff,  #6d5656ff);
            background-size: 400% 400%;
            animation: gradientBG 20s ease infinite;
            min-height: 100vh;
            color: #fff;
            padding-top: 100px;
            padding-bottom: 3rem;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .floating-header {
            position: fixed;
            top: 15px;
            left: 50%;
            transform: translateX(-50%);
            width: 95%;
            max-width: 800px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 10px 25px;
            border-radius: 50px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            z-index: 1100;
        }

        /* Estilo para hacer visible el logo negro */
        .floating-header .logo img {
            height: 48px;
            background-color: rgba(255, 255, 255, 0.85);
            border-radius: 50%;
            padding: 5px;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }

        .floating-header .username {
            font-weight: 600;
            font-size: 1.1rem;
            text-shadow: 1px 1px 5px rgba(0,0,0,0.2);
        }

        .floating-header .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            border-radius: 25px;
            transition: all 0.3s ease;
            text-decoration: none;
            padding: 8px 20px;
            border: none;
        }
        .floating-header .logout-btn:hover { background-color: rgba(220, 53, 69, 0.8); }

        .main-title, .section-title {
            text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.3);
            font-weight: 700;
            text-align: center;
        }
        .section-title { font-size: 1.8rem; margin-top: 3rem; margin-bottom: 2rem; }
        
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1rem;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            color: #fff;
            display: flex;
            flex-direction: column;
        }
        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 16px 40px 0 rgba(0, 0, 0, 0.3);
        }
        .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            font-weight: 600;
            font-size: 1.2rem;
            text-align: center;
        }
        .card-body { padding: 1.5rem; flex-grow: 1; }
        .card-body p, .card-body .form-label {
            color: rgba(255, 255, 255, 0.9);
            text-shadow: 1px 1px 3px rgba(0,0,0,0.2);
        }
        
        .form-control, .form-select {
            background-color: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff;
            border-radius: 0.5rem;
        }
        .form-control::placeholder { color: rgba(255, 255, 255, 0.6); }
        .form-control:focus, .form-select:focus {
            background-color: rgba(0, 0, 0, 0.3);
            color: #fff;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.3);
        }
        select.form-select option { background: #333; color: #fff; }

        .btn {
            font-weight: 600;
            border-radius: 0.5rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: none;
        }
        .btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }
        
        .alert {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid;
            color: #fff;
        }
        .alert-success { border-color: var(--success-color); }
        .alert-danger { border-color: var(--danger-color); }
        .alert-warning { border-color: var(--warning-color); color: #000; }
        .alert-info { border-color: var(--info-color); }
    </style>
</head>
<body>

<header class="floating-header animate__animated animate__fadeInDown" role="banner">
    <div class="logo" aria-hidden="true">
        <img src="../IMG/LOGO MC - NEGRO.png" alt="Logo de la empresa" />
    </div>
    <div class="username" aria-live="polite">
        <i class="fa-solid fa-user-shield me-2"></i><?= htmlspecialchars($_SESSION['usuario']) ?>
    </div>
    <a href="../Logica/logout.php" class="logout-btn" role="button">
        <i class="fa-solid fa-right-from-bracket me-1"></i>Cerrar Sesión
    </a>
</header>

<main class="container-fluid" role="main">
    <h1 class="main-title mb-4 animate__animated animate__fadeInDown" style="animation-delay: 0.2s;">Panel de Administración</h1>

    <section aria-labelledby="acceso-rapido-title">
        <h2 id="acceso-rapido-title" class="section-title animate__animated animate__fadeInUp">Acceso Rápido</h2>
        <div class="cards-container mb-5">
            <?php 
                $cards = [
                    ['title' => 'Reporte Facturas CXC', 'desc' => 'Reporte de Facturas faltantes.', 'link' => '../View/BI.php', 'icon' => 'fa-file-invoice-dollar'],
                    ['title' => 'Dashboard', 'desc' => 'Visión general de la gestión.', 'link' => '../View/dashboard.php', 'icon' => 'fa-tachometer-alt'],
                ];
                $delay = 0.2;
                foreach ($cards as $card):
            ?>
            <article class="card animate__animated animate__zoomIn" style="animation-delay: <?= $delay ?>s;">
                <div class="card-header"><i class="fa-solid <?= $card['icon'] ?> me-2"></i><?= $card['title'] ?></div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <p><?= $card['desc'] ?></p>
                    <a href="<?= $card['link'] ?>" class="btn btn-outline-light w-100 mt-3" role="link">Ingresar</a>
                </div>
            </article>
            <?php $delay += 0.1; endforeach; ?>
        </div>
    </section>



</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>