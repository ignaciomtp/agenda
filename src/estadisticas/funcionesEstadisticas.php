<?php
require_once __DIR__ . '/../config/database.php'; // Conexión PDO

/**
 * Obtiene los clientes con el mayor número de citas en el año seleccionado
 *
 * @param PDO $conn             Conexión a la BD
 * @param integer $limit        Número de entradas que se recogen
 * @param integer|null $anio    Año de las citas
 * @return array                Array con los clientes
 */
function getClientesMasCitas(PDO $conn, int $limit = 5, ?int $anio = null): array {
    // Se declara (con ?) que anio puede ser int o null en parámetro de función 
    if (!$anio) {
        $anio = date('Y');
    }
    $limit = (int)$limit; // SQL seguro
    $stmt = $conn->prepare("
        SELECT Cliente, COUNT(*) AS Citas, MAX(Fecha) AS UltimaCita
        FROM Agenda
        WHERE YEAR(Fecha) = :anio
        GROUP BY Cliente
        ORDER BY Citas DESC
        OFFSET 0 ROWS FETCH NEXT $limit ROWS ONLY
    ");
    $stmt->bindValue(':anio', $anio, PDO::PARAM_INT);
    //$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Selecciona los días del año que han tenido más citas
 *
 * @param PDO $conn             Conexión a la BD
 * @param integer $limit        Número de entradas que se recogen
 * @param integer|null $anio    Año de las citas
 * @return array                Array con los días
 */
function getDiasMasCitas(PDO $conn, int $limit = 5, ?int $anio = null): array {
    if (!$anio) {
        $anio = date('Y');
    }
    $stmt = $conn->prepare("
        SELECT CAST(Fecha AS DATE) AS Fecha, COUNT(*) AS Citas
        FROM Agenda
        WHERE YEAR(Fecha) = :anio
        GROUP BY CAST(Fecha AS DATE)
        ORDER BY Citas DESC
        OFFSET 0 ROWS FETCH NEXT :limit ROWS ONLY
    ");
    $stmt->bindValue(':anio', $anio, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Selecciona los servicios más solicitados del año
 *
 * @param PDO $conn             Conexión a la BD
 * @param integer $limit        Número de entradas que se recogen
 * @param integer|null $anio    Año de las citas
 * @return array                Array con los servicios
 */
function getServiciosMasSolicitados(PDO $conn, int $limit = 5, ?int $anio = null): array {
    if (!$anio) {
        $anio = date('Y');
    }
    $stmt = $conn->prepare("
        SELECT Concepto AS Servicio, COUNT(*) AS Cantidad
        FROM Agenda
        WHERE YEAR(Fecha) = :anio
        GROUP BY Concepto
        ORDER BY Cantidad DESC
        OFFSET 0 ROWS FETCH NEXT :limit ROWS ONLY
    ");
    $stmt->bindValue(':anio', $anio, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Selecciona las horas del día con más citas del año seleccionado
 *
 * @param PDO $conn             Conexión a la BD
 * @param integer $limit        Número de entradas que se recogen
 * @param integer|null $anio    Año de las citas
 * @return array                Array con las horas
 */
function getHorasMasDemandadas(PDO $conn, int $limit = 5, ?int $anio = null): array {
    if (!$anio) {
        $anio = date('Y');
    }
    $stmt = $conn->prepare("
        SELECT CONVERT(VARCHAR(5), Hora, 108) AS Hora, COUNT(*) AS Citas
        FROM Agenda
        WHERE YEAR(Fecha) = :anio
        GROUP BY CONVERT(VARCHAR(5), Hora, 108)
        ORDER BY Citas DESC
        OFFSET 0 ROWS FETCH NEXT :limit ROWS ONLY
    ");
    $stmt->bindValue(':anio', $anio, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene el número de citas totales de cada mes y las ordena por meses
 *
 * @param PDO $conn             Conexión a la BD
 * @param integer|null $anio    Año de las citas
 * @return array                Array con las citas ordenadas por mes
 */
function getCitasPorMes(PDO $conn, ?int $anio = null): array {
    if (!$anio) {
        $anio = date('Y');
    }
    $stmt = $conn->prepare("
        SELECT MONTH(Fecha) AS Mes, COUNT(*) AS Citas
        FROM Agenda
        WHERE YEAR(Fecha) = :anio
        GROUP BY MONTH(Fecha)
        ORDER BY Mes
    ");
    $stmt->execute([':anio' => $anio]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Aseguramos que todos los meses estén presentes, aunque no tengan citas
    $meses = [];
    for ($i=1; $i<=12; $i++) {
        $meses[$i] = 0;
    }
    foreach ($result as $r) {
        $meses[(int)$r['Mes']] = (int)$r['Citas'];
    }

    return $meses;
}
