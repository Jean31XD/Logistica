<?php 
session_start();
date_default_timezone_set('America/Santo_Domingo');

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Verificar autenticación y permisos
if (!isset($_SESSION['usuario'], $_SESSION['pantalla']) || $_SESSION['pantalla'] != 1) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../conexionBD/conexion.php';

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
  <title>Panel de Despacho </title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet" />
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
        background: linear-gradient(-45deg, #cf8888ff, #6d5656ff, #6d5656ff, #6d5656ff);
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
        padding: 8px 20px;
        border: none;
        transition: all 0.3s ease;
    }
    .floating-header .logout-btn:hover {
        background-color: rgba(220, 53, 69, 0.8);
    }

    .main-title, .section-title {
        text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.3);
        font-weight: 700;
        text-align: center;
    }
    .section-title {
        font-size: 1.8rem;
        margin-top: 3rem;
        margin-bottom: 2rem;
    }

    .cards-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .card {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 1rem;
        box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
        color: #fff;
        display: flex;
        flex-direction: column;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
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

    .card-body {
        padding: 1.5rem;
        flex-grow: 1;
    }

    .card-body p {
        color: rgba(255, 255, 255, 0.9);
        text-shadow: 1px 1px 3px rgba(0,0,0,0.2);
    }

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
  </style>
</head>
<body>

<header class="floating-header animate__animated animate__fadeInDown" role="banner">
    <div class="logo" aria-hidden="true">
        <img src="../IMG/LOGO MC - NEGRO.png" alt="Logo empresa" />
    </div>
    <div class="username" aria-live="polite">
        <i class="fa-solid fa-user-tie me-2"></i><?= htmlspecialchars($_SESSION['usuario']) ?>
    </div>
    <a href="../Logica/logout.php" class="logout-btn" role="button">
        <i class="fa-solid fa-right-from-bracket me-1"></i>Cerrar Sesión
    </a>
</header>

<main class="container-fluid" role="main">
    <h1 class="main-title mb-4 animate__animated animate__fadeInDown">Panel de Despacho</h1>

    <section aria-labelledby="acceso-rapido-title">
        <h2 id="acceso-rapido-title" class="section-title animate__animated animate__fadeInUp">Acceso Rápido</h2>
        <div class="cards-container mb-5">
            <article class="card animate__animated animate__zoomIn" style="animation-delay: 0.2s;">
                <div class="card-header"><i class="fa-solid fa-inbox me-2"></i>Despacho</div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <p>Control de despacho de clientes.</p>
                    <a href="../View/Inicio.php" class="btn btn-outline-light w-100 mt-3" role="link">Ingresar</a>
                </div>
            </article>

            <article class="card animate__animated animate__zoomIn" style="animation-delay: 0.3s;">
                <div class="card-header"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Creacion de choferes</div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <p>Registro de nuevos choferes.</p>
                    <a href="../View/Gestion_de_camiones.php" class="btn btn-outline-light w-100 mt-3" role="link">Ingresar</a>
                </div>
            </article>
        </div>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
