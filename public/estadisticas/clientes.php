<?php
header('Content-Type: application/json');

// Si usuario no está logueado se redirige desde panel_estadisticas.js (función obtenerDatos(endpoint)) a login.php
require_once __DIR__ . '/../../src/auth/checkSession.php';
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/estadisticas/funcionesEstadisticas.php';

try {
    $conn = conectarPDO(); 

    $anio = isset($_GET['anio']) ? intval($_GET['anio']) : null;
    // Llamamos a la función que obtiene los clientes con más citas
    $clientes = getClientesMasCitas($conn, 5, $anio);

    echo json_encode(['success' => true, 'data' => $clientes]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
