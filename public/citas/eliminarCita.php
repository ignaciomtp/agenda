<?php
require_once __DIR__ . '/../../src/auth/checkSession.php';
require_once __DIR__ . '/../../src/config/database.php';

$idCita = $_GET['id'] ?? null;
$fechaActual = date('Y-m-d');

// Obtener redirect desde GET si existe
if (!empty($_GET['redirect'])) {
    // Decodificamos la URL pasada como parámetro
    $redirect = urldecode($_GET['redirect']);
} else {
    // Redirect por defecto a mostrarCitas.php con fecha actual
    $redirect = "mostrarCitas.php?fecha=$fechaActual";
}

if (!$idCita) {
    $_SESSION['error_msg'] = "❌ No se especificó la cita a eliminar.";
    // Si no tenemos la fecha, volvemos al día actual como fallback
    header("Location:" . $redirect);
    exit;
}

try {
    $conn = conectarPDO();

    // Obtener la fecha de la cita antes de eliminar
    $stmt = $conn->prepare("SELECT Fecha FROM Agenda WHERE Ref_Agenda = :id");
    $stmt->execute([':id' => $idCita]);
    $cita = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cita) {
        $_SESSION['error_msg'] = "⚠️ La cita no existe o ya fue eliminada.";
        // Si no existe, redirigir al día actual
        header("Location: $redirect");
        exit;
    }

    $fecha = date('Y-m-d', strtotime($cita['Fecha']));

    // Eliminar la cita
    $stmt = $conn->prepare("DELETE FROM Agenda WHERE Ref_Agenda = :id");
    $stmt->execute([':id' => $idCita]);

    $_SESSION['success_msg'] = "✅ La cita se eliminó correctamente.";
    header("Location: $redirect");
    exit;

} catch (Exception $e) {
    // Si ocurre un error, redirigir con la fecha si la tenemos, o al día actual
    $fecha = $fecha ?? date('Y-m-d');
    $_SESSION['error_msg'] = "❌ Error al eliminar la cita: " . $e->getMessage();
    header("Location: $redirect");
    exit;
}
