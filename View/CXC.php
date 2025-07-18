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

if (!isset($_SESSION['usuario'], $_SESSION['pantalla']) || $_SESSION['pantalla'] != 3) {
    header("Location: ../View/index.php");
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
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate" />
  <meta http-equiv="Pragma" content="no-cache" />
  <meta http-equiv="Expires" content="0" />
  <title>Panel de Administración</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
      :root {
        --rojo: #e31f25;
        --rojo-oscuro: #b71c1c;
        --fondo-degradado: linear-gradient(to bottom, #e31f25 0%, #ffffff 100%);
      }

      body {
        min-height: 100vh;
        background: var(--fondo-degradado);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #333;
        margin: 0;
        padding-top: 100px; /* espacio para el header flotante */
        padding-bottom: 3rem;
      }

      /* Header flotante con logo + usuario + logout */
      .floating-header {
        position: fixed;
        top: 15px;
        left: 50%;
        transform: translateX(-50%);
        background: #fff;
        padding: 12px 40px;
        border-radius: 30px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 40px;
        min-width: 400px;
        max-width: 600px;
        justify-content: space-between;
        z-index: 1100;
        user-select: none;
      }

      /* Logo */
      .floating-header .logo img {
        height: 48px;
        user-select: none;
      }

      /* Nombre de usuario centrado */
      .floating-header .username {
        flex-grow: 1;
        text-align: center;
        font-weight: 700;
        font-size: 1.25rem;
        color: #000; /* negro para mejor contraste */
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        user-select: text;
      }

      /* Botón cerrar sesión */
      .floating-header .logout-btn {
        background: var(--rojo);
        color: #fff;
        border: none;
        padding: 8px 20px;
        border-radius: 25px;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.3s ease;
        white-space: nowrap;
        text-decoration: none;
        display: inline-block;
      }

      .floating-header .logout-btn:hover {
        background-color: var(--rojo-oscuro);
        color: #fff;
        text-decoration: none;
      }

      /* Título principal */
      .main-title {
        color: #000; /* negro */
        font-weight: 700;
        margin: 2rem 0 1rem;
        text-align: center;
        text-shadow: none;
        background-color: #fff;
        display: inline-block;
        padding: 0.75rem 2rem;
        border-radius: 15px;
        user-select: none;
        box-shadow: 0 3px 8px rgb(0 0 0 / 0.1);
      }

      /* Títulos secciones */
      .section-title {
        color: #000; /* negro */
        font-size: 1.5rem;
        font-weight: 700;
        border-left: 6px solid var(--rojo);
        padding: 0.4rem 1.2rem;
        margin-top: 3rem;
        margin-bottom: 1.5rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        user-select: none;
        background-color: #fff;
        border-top-right-radius: 15px;
        border-bottom-right-radius: 15px;
        display: inline-block;
        box-shadow: 0 2px 10px rgb(0 0 0 / 0.1);
      }

      /* Tarjetas acceso rápido */
      .cards-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 2rem;
        margin-bottom: 3rem;
      }

      .card {
        border-radius: 15px;
        box-shadow: 0 8px 25px rgb(0 0 0 / 0.15);
        border: none;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
      }

      .card:hover {
        transform: translateY(-8px);
        box-shadow: 0 16px 40px rgb(0 0 0 / 0.25);
      }

      .card-header {
        background-color: var(--rojo);
        color: #fff;
        font-weight: 700;
        font-size: 1.2rem;
        border-top-left-radius: 15px;
        border-top-right-radius: 15px;
        user-select: none;
        text-align: center;
        padding: 0.9rem 1rem;
      }

      .card-body {
        padding: 1.25rem 1.5rem;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
      }

      .card-body p {
        margin-bottom: 1.2rem;
        color: #444;
        font-size: 0.95rem;
      }

      /* Formularios gestión usuarios */
      .form-section {
        background-color: #fff;
        padding: 2rem 1.8rem;
        border-radius: 15px;
        box-shadow: 0 0 18px rgb(0 0 0 / 0.1);
        transition: box-shadow 0.3s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
      }

      .form-section:hover {
        box-shadow: 0 0 28px rgb(0 0 0 / 0.16);
      }

      /* Títulos formularios */
      .form-section h5 {
        color: var(--rojo);
        font-weight: 700;
        margin-bottom: 1.8rem;
        text-align: center;
        user-select: none;
      }

      /* Botón rojo */
      .btn-rojo {
        background-color: var(--rojo);
        color: #fff;
        font-weight: 700;
        border: none;
        box-shadow: 0 6px 14px rgb(227 31 37 / 0.5);
        transition: background-color 0.3s ease;
      }

      .btn-rojo:hover,
      .btn-rojo:focus {
        background-color: var(--rojo-oscuro);
        box-shadow: 0 8px 20px rgb(183 28 28 / 0.7);
        outline: none;
      }

      .alert {
        font-size: 1rem;
        border-radius: 10px;
        padding: 0.85rem 1.2rem;
        margin-bottom: 1.2rem;
        user-select: none;
      }

      label.form-label {
        font-weight: 700;
        color: var(--rojo-oscuro);
        user-select: none;
      }

      input.form-control,
      select.form-select {
        border-radius: 10px;
        border: 1.7px solid #bbb;
        transition: border-color 0.3s ease;
        font-size: 0.95rem;
        padding: 0.5rem 0.75rem;
      }

      input.form-control:focus,
      select.form-select:focus {
        border-color: var(--rojo);
        box-shadow: 0 0 8px var(--rojo);
        outline: none;
      }

      /* Grid para formularios en pantallas grandes */
      @media (min-width: 768px) {
        .row.g-4 > .col-md-4 {
          display: flex;
        }
      }
    </style>
</head>
<body>

<!-- Panel flotante arriba -->
<div class="floating-header" role="banner" aria-label="Panel de administración superior">
  <div class="logo" aria-hidden="true">
    <img src="../IMG/LOGO MC - NEGRO.png" alt="Logo empresa" />
  </div>
  <div class="username" aria-live="polite" aria-atomic="true">
    <?= htmlspecialchars($_SESSION['usuario']) ?>
  </div>
  <a href="../Logica/logout.php" class="logout-btn" role="button" aria-label="Cerrar sesión">Cerrar Sesión</a>
</div>

<div class="container" role="main" aria-labelledby="main-title">

  <section aria-labelledby="acceso-rapido-title">
    <h2 id="acceso-rapido-title" class="section-title">Acceso rápido a pantallas</h2>
    <div class="cards-container mb-5">
   
    
      <article class="card" tabindex="0" aria-label="Acceso a Recepción">
        <div class="card-header">Recepción</div>
        <div class="card-body d-flex flex-column justify-content-between">
          <p class="mb-3 text-muted">Control de recepción de documentos.</p>
          <a href="../View/facturas-recepcion.php" class="btn btn-rojo w-100" role="link">Ingresar</a>
        </div>
      </article>
    
       <article class="card" tabindex="0" aria-label="Acceso a Reporte de Facturas">
        <div class="card-header">Reporte de facturas CXC</div>
        <div class="card-body d-flex flex-column justify-content-between">
          <p class="mb-3 text-muted">Reporte de Facturas faltantes.</p>
          <a href="../View/BI.php" class="btn btn-rojo w-100" role="link">Ingresar</a>
        </div>
      </article>
     
    </div>
    
       
  </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
