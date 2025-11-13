<?php
session_start();
date_default_timezone_set('America/Santo_Domingo');

// Destruir solo las variables de sesión del dashboard
unset($_SESSION['dashboard_access_granted']);
unset($_SESSION['dashboard_user_type']);
unset($_SESSION['dashboard_warehouse']);

// Redirigir de vuelta al dashboard (que ahora mostrará el login por código)
header('Location: ../View/dashboard.php');
exit;
?>