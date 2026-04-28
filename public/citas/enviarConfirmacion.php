<?php
require_once __DIR__ . '/../../src/auth/checkSession.php';
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/lib/recordatorioService.php'; // Reutilizamos funciones de envío
include_once __DIR__ . "/../../src/lib/utils.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Twilio\Rest\Client;

// Servidor indica al cliente (navegador u otro) que lo que se envía es JSON
header('Content-Type: application/json');

try {
    $conn = conectarPDO();

    $idCita = $_POST['idCita'] ?? null;
    $canal  = $_POST['canal'] ?? null;

    if (!$idCita) throw new Exception("No se especificó la cita.");
    if (!$canal || !in_array($canal, ['email', 'sms', 'whatsapp'])) {
        throw new Exception("Canal de envío inválido.");
    }

    // Obtener la cita
    $stmt = $conn->prepare("SELECT Ref_Agenda, Fecha, Hora, Concepto, Notas, Usuario FROM Agenda WHERE Ref_Agenda = :id");
    $stmt->execute([':id' => $idCita]);
    $cita = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cita) throw new Exception("Cita no encontrada.");

    // Extraer email y teléfono
    preg_match('/Email:\s*([^\s]+)\s*Telefono:\s*([\d\+\-\s]+)/', $cita['Notas'], $matches);
    $cita['Email'] = $matches[1] ?? null;
    $cita['Telefono'] = $matches[2] ?? null;

    if (!$cita['Email'] && !$cita['Telefono']) {
        throw new Exception("No hay datos de contacto para enviar la confirmación.");
    }

    // Obtener nombre del usuario
    $nombreUsuario = obtenerNombreUsuario($conn, $cita['Usuario']);

    // Reemplazar variables en la plantilla (puedes usar la misma de recordatorio)
    $mensaje = "Hola, tu cita ha sido confirmada:\n\n";
    $mensaje .= "Fecha: " . date('d/m/Y', strtotime($cita['Fecha'])) . "\n";
    $mensaje .= "Hora: " . date('H:i', strtotime($cita['Hora'])) . "\n";
    $mensaje .= "Concepto: " . $cita['Concepto'] . "\n";
    $mensaje .= "Usuario: " . $nombreUsuario;

    // Cargar la configuración. Devuelve un array asociativo con secciones ([database], [email] y otras si hubiera):
    $config = parse_ini_file(__DIR__ . '/../../src/config/config.ini', true);
    // Enviar según el canal
    switch($canal) {
        case 'email':
            if (!$cita['Email']) throw new Exception("No hay email para enviar la confirmación.");
            if (!$config['email']) throw new Exception("No hay configuración de email en config.ini");
            $header = '✂️ Confirmación de cita';
            $resultado = enviarEmailRecordatorio($cita, $config, $header, $mensaje, $conn);
            break;
        case 'sms':
            if (!$cita['Telefono']) throw new Exception("No hay teléfono para enviar SMS.");
            $remitente = $config['sms']['remitente'];
            $resultado = enviarSmsRecordatorio($cita, $mensaje, $remitente, $conn);
            break;
        case 'whatsapp':
            if (!$cita['Telefono']) throw new Exception("No hay teléfono para enviar WhatsApp.");
            $sid = $config['whatsapp']['sid'];
            $token =$config['whatsapp']['tokenTwilio'];
            $telefonoTwilioWA = $config['whatsapp']['telefonoTwilioWA'];
            $resultado = enviarWaRecordatorio($cita, $sid, $token, $telefonoTwilioWA, $conn);
            break;
    }

    echo json_encode(['status' => 'ok', 'mensaje' => $resultado]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'mensaje' => $e->getMessage()]);
}
