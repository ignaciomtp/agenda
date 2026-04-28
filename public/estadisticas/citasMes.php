<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../src/auth/checkSession.php';
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/estadisticas/funcionesEstadisticas.php';

try {
    $conn = conectarPDO(); // Función que devuelve el PDO

    $anio = isset($_GET['anio']) ? intval($_GET['anio']) : null;
    // Llamamos a la función que obtiene los clientes con más citas
    $citas = getCitasPorMes($conn, $anio);

    echo json_encode(['success' => true, 'data' => $citas]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
