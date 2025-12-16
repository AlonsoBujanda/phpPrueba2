<?php
require_once 'includes/auth.php';

// Cerrar sesión
$auth->logout();

// Redirigir al login
header('Location: login.php?msg=logout');
exit;
?>