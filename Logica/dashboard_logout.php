<?php
session_start();
date_default_timezone_set(timezoneId: 'America/Santo_Domingo');

// Solo eliminamos las variables de sesión específicas del dashboard
unset($_SESSION['dashboard_access_granted']);
unset($_SESSION['dashboard_user_type']);
unset($_SESSION['dashboard_warehouse']);

// ===== ESTA ES LA LÍNEA CORREGIDA =====
// Redirigir de vuelta al dashboard.php (que está en la carpeta ../view/)
header("Location: ../view/dashboard.php");
exit();