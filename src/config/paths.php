<?php
/**
 * Archivo de configuración de rutas base del proyecto
 * Creado para funcionar automáticamente en local y servidor
 */

// Obtiene el DocumentRoot (por ejemplo: "C:\Users\pruebas\Desktop\Proyectos\peluqueria\peluqueria v3)
// realpath() convierte en ruta absoluta correcta (sin .. o . si hubiera, con separadores correctos del sistema, sin duplicados...)
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT']);

// Detecta la carpeta "public" del proyecto (relativa al root)
// __DIR__ contiene la ruta absoluta del directorio donde está archivo actual (paths.php)
// Subir 2 carpetas para tener ruta completa a public -> "C:\Users\pruebas\Desktop\Proyectos\peluqueria\peluqueria v3\public"
$publicDir = realpath(__DIR__ . '/../../public');

// Si no se encuentra la carpeta public, usa una ruta por defecto
if (!$publicDir) {
    $basePath = '/public';
} else {
    // Calcula la ruta web relativa (sustituye el document root del path completo por '', lo elimina). En ejemplo se queda con "\public"
    $basePath = str_replace($documentRoot, '', $publicDir);
}

// Si la carpeta "public" ya es el DocumentRoot, basePath queda vacío (''), así que lo ajustamos
if ($basePath === '' || $basePath === false) {
    $basePath = '/';
}

// Normaliza los separadores de ruta (por si estás en Windows)
$basePath = str_replace('\\', '/', $basePath);

// 🔧 Asegura que siempre termine en /
if (substr($basePath, -1) !== '/') {
    $basePath .= '/';
}

// Ahora $basePath puede ser:
// - "/peluqueria v2/public/" → en local
// - "/agenda/public/" → en servidor dentro de carpeta
// - "/" → si el servidor apunta directamente a /public
