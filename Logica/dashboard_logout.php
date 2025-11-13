<?php
session_start();
date_default_timezone_set(timezoneId: 'America/Santo_Domingo');

// Solo eliminamos las variables de sesión específicas del dashboard
unset($_SESSION['dashboard_access_granted']);
unset($_SESSION['dashboard_user_type']);
unset($_SESSION['dashboard_warehouse']);

// Redirigir de vuelta a la página del dashboard
// Como 'dashboard_access_granted' ya no existe (pero 'usuario' sí),
// se mostrará la pantalla de login por PIN.
header("Location: ../View/dashboard.php");
exit();