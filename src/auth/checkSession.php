<?php
/* ## ARCHIVO PARA CHEQUEAR SI USUARIO PUEDE ACCEDER A PÁGINAS PRIVADAS ##
        Si no hay sesión iniciada, redirige a login.php. 
        Evita acceso escribiendo la url directamente en barra del navegador a usuario sin loguear. */

// Inicia sesión sólo si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Incluye src/config/paths.php para tener $basePath correctamente configurado
require_once __DIR__ . '/../config/paths.php'; 

// Verifica sesión
if (empty($_SESSION['Usuario'])) {
    // Redirige al login (ruta absoluta)
    header("Location: {$basePath}login.php");
    // Otra opción:  header("Location: " . $basePath . "login.php");
    exit;
}
?>
