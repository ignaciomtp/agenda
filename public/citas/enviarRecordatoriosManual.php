<?php
require_once __DIR__ . '/../../src/auth/checkSession.php';
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/lib/recordatorioService.php'; // usar funciones de envío de recordatorio
include_once __DIR__ . "/../../src/lib/utils.php";

use PHPMailer\PHPMailer\Exception;

try {
    $conn = conectarPDO();

    $tipoEnvio = $_GET['tipo'] ?? null;
    $idCita = $_GET['id'] ?? null;
    $fechaRecordatorio = $_GET['fecha'] ?? null;

    if (!$tipoEnvio) throw new Exception("Tipo de envío no especificado.");
    
    $resultado = [];

    // Obtener citas: individual o masivo por fecha
    if ($idCita) {
        // Envío individual: no filtramos por recordatorio ya enviado
        $stmt = $conn->prepare("SELECT Ref_Agenda, Fecha, Hora, Concepto, Notas, Usuario FROM Agenda WHERE Ref_Agenda = :id");
        $stmt->execute([':id' => $idCita]);
        $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
         // Envío masivo: solo a los que no hayan recibido este tipo de recordatorio
        if (!$fechaRecordatorio) throw new Exception("Fecha no especificada para envío masivo.");

        $campoRecordatorio = match($tipoEnvio) {
            'email'    => 'Recordatorio_Email',
            'sms'      => 'Recordatorio_Sms',
            'whatsapp' => 'Recordatorio_WA',
            default    => null,
        };
        if (!$campoRecordatorio) throw new Exception("Tipo de envío desconocido.");

        $stmt = $conn->prepare("
            SELECT Ref_Agenda, Fecha, Hora, Concepto, Notas, Usuario
            FROM Agenda
            WHERE CONVERT(date, Fecha) = :fecha
            AND $campoRecordatorio = 0
        ");
        $stmt->execute([':fecha' => $fechaRecordatorio]);
        $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($citas)) {
            $resultado[] = '<div class="alert alert-info" role="alert">ℹ️ No hay citas pendientes para enviar recordatorio.</div>';
        }
    }

    // Cargar la configuración. Devuelve un array asociativo con secciones ([database], [email], [whatsapp]...):
    $config = parse_ini_file(__DIR__ . '/../../src/config/config.ini', true);
    if (!$config['email']) throw new Exception("No hay configuración de email en config.ini");
    
    // Obtener token y phone number id de WA si se usa API de WhatsApp
    //$waToken = $config['whatsapp']['tokenWa'];
    //$waPhoneId = $config['whatsapp']['phone_number_id'];

    // Obtener plantilla desde BD (sólo para EMAIL y SMS)
    $stmt = $conn->prepare("SELECT Plantilla_Recordatorio FROM Configuracion2");
    $stmt->execute();
    $plantilla = $stmt->fetchColumn();
    
    if (!$plantilla) throw new Exception("No hay plantilla configurada para los recordatorios de sms o email.");

    // Obtener plantilla de WA
    $stmt = $conn->prepare("SELECT Plantilla_RecordatorioWA FROM Configuracion2");
    $stmt->execute();
    $plantillaWA = $stmt->fetchColumn();

    if (!$plantillaWA) throw new Exception("No hay plantilla configurada para los recordatorios de WhatsApp.");
    
    foreach ($citas as $cita) {
        // Extraer email y teléfono de la columna Notas
        preg_match('/Email:\s*([^\s]+)\s*Telefono:\s*([\d\+\-\s]+)/', $cita['Notas'], $matches);
        // $matches[0] = "Email: juan@correo.com Telefono: 612345678" -> toda la coincidencia completa en índice 0
        $cita['Email'] = $matches[1] ?? null;
        $cita['Telefono'] = $matches[2] ?? null;
        // Formatear fecha y hora
        $cita['Fecha'] = date('d/m/Y', strtotime($cita['Fecha']));
        $cita['Hora'] = date('H:i', strtotime($cita['Hora']));

        if (!$cita['Email'] && !$cita['Telefono']) continue; // Si no hay forma de contacto, saltamos al siguiente registro
                
        // Seleccionar nombre de la persona que llevará a cabo el servicio
        //$nombreUsuario = obtenerNombreUsuario($conn, $cita['Usuario']);
      
        // --- Enviar email ---
        if ($tipoEnvio === "email" && $cita['Email']) {
            $header = '✂️ Recordatorio de cita';
            // Reemplazar variables en la plantilla
            $reemplazos = [
                '{{fecha}}'    => $cita['Fecha'],
                '{{hora}}'     => $cita['Hora']
            ];
            $mensaje = str_replace(array_keys($reemplazos), array_values($reemplazos), $plantilla);
            try {
                $resultado[] = enviarEmailRecordatorio($cita, $config, $header, $mensaje, $conn);
            } catch (Exception $e) {
                $resultado[] = '<div class="alert alert-danger" role="alert">❌ Error enviando correo a ' . $email . ' :' . $mail->ErrorInfo . '</div>';
            }
        }
        
        // --- Enviar SMS con Altiria ---
        if ($tipoEnvio === "sms" && $cita['Telefono']) {
            $reemplazos = [
                '{{fecha}}'    => $cita['Fecha'],
                '{{hora}}'     => $cita['Hora']
            ];
            $mensaje = str_replace(array_keys($reemplazos), array_values($reemplazos), $plantilla);
            $remitente = $config['sms']['remitente'];
            $resultado[] = enviarSmsRecordatorio($cita, $mensaje, $remitente, $conn);
        }

        // --- Enviar mensaje por WhatsApp ---
        if ($tipoEnvio === "whatsapp" && $cita['Telefono']) {
            // Obtener token y sid si se usa Twilio para envío de WhatsApp
            $twilioToken = $config['whatsapp']['tokenTwilio'];
            $twilioSid = $config['whatsapp']['sid'];
            $telefonoTwilioWA = $config['whatsapp']['telefonoTwilioWA'];

            // Envío usanto Twilio (prueba con sandbox, no con versión de pago)
            //$resultado[] = enviarWaRecordatorio($cita, $conn);
            $resultado[] = enviarWaRecordatorio($cita, $twilioSid, $twilioToken, $telefonoTwilioWA, $conn);

        }

    }
} catch (Exception $e) {
    $resultado[] = '<div class="alert alert-danger" role="alert">❌ Error general: ' . $e->getMessage() . '</div>';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envío de recordatorios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/style_base.css">

</head>
<body>
    <!-- header -->
    <?php include __DIR__ . '/../../partials/header.php'; ?>

    <div id="form-cita" style="margin-top: 1rem;">
        <?php 
        if (!empty($resultado)) {
            echo implode($resultado); 
        } else {
            echo '<div class="alert alert-info mb-3">No hay citas para enviar recordatorio.</div>';
        }
        ?>
        <a href="mostrarCitas.php?fecha=<?= $fechaRecordatorio ?>" class="btn btn-secondary mt-2">Volver a las citas</a> 
    </div>

    <!-- Footer -->
    <?php include __DIR__ . '/../../partials/footer.php'; ?>
</body>
</html>
