<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/auth/checkSession.php';

try {
    $conn = conectarPDO();

     // Recogida y validación básica de datos
    $modoEnvio = isset($_POST['modo_envio']) && ($_POST['modo_envio'] == '1' || $_POST['modo_envio'] == '0') ? (int)$_POST['modo_envio'] : 1;

    $tipoEnvio = isset($_POST['tipo_envio']) ? $_POST['tipo_envio'] : 'email';
    $cuandoEnviar = isset($_POST['cuando_enviar']) ? $_POST['cuando_enviar'] : 'mismoDia';

    // Valores por defecto
    $horasMismoDia = isset($_POST['horas_mismo_dia']) ? (int)$_POST['horas_mismo_dia'] : 4;
    if ($horasMismoDia < 1 || $horasMismoDia > 23) $horasMismoDia = 4;

    $diasAnticipacion = isset($_POST['dias_anticipacion']) ? (int)$_POST['dias_anticipacion'] : 1;
    if ($diasAnticipacion < 1 || $diasAnticipacion > 30) $diasAnticipacion = 1;

    $horaEnvio = isset($_POST['horas_dias_anticipacion']) ? $_POST['horas_dias_anticipacion'] : '20:00';
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $horaEnvio)) {
        $horaEnvio = '20:00';
    }

    // Actualización en la tabla Configuracion2 (una única fila)
    $sql = "UPDATE Configuracion2
            SET Recordatorios_Auto = :modoEnvio,
                Recordatorios_Auto_Tipo = :tipoEnvio,
                Recordatorio_EnviarModo = :cuandoEnviar,
                Recordatorio_HorasMismoDia = :horasMismoDia,
                Recordatorio_DiasAnticipacion = :diasAnticipacion,
                Recordatorio_HoraEnvio = :horaEnvio";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':modoEnvio' => $modoEnvio,
        ':tipoEnvio' => $tipoEnvio,
        ':cuandoEnviar' => $cuandoEnviar,
        ':horasMismoDia' => $horasMismoDia,
        ':diasAnticipacion' => $diasAnticipacion,
        ':horaEnvio' => $horaEnvio
    ]);
    $_SESSION['success_msg'] = "✅ Preferencias guardadas correctamente.";
    // Redirigir con mensaje de éxito
    header("Location: " . $basePath . "configuracionCitas/preferencias.php?success=true");
    exit;

} catch (PDOException $e) {
   // En caso de error, redirigimos con mensaje
    $_SESSION['error_msg'] = "❌ Error al guardar las preferencias: " . $e->getMessage();
    header("Location: " . $basePath . "configuracionCitas/preferencias.php?success=false");
    exit;
}
?>
