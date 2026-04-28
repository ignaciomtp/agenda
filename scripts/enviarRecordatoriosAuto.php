<?php
echo "✅ Script iniciado\n";

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/lib/recordatorioService.php'; // Funciones de envío de recordatorios
require_once __DIR__ . '/../src/lib/utils.php'; 

$logFile = __DIR__ . '/../logs/recordatorios_auto.log';

function escribirLog(string $mensaje, string $logFile) {
    $fecha = (new DateTime())->format('Y-m-d H:i:s');
    file_put_contents($logFile, "[$fecha] $mensaje\n", FILE_APPEND);
}

try {
    $conn = conectarPDO();

    // Obtener configuración de recordatorios automáticos
    $stmt = $conn->prepare("SELECT Recordatorios_Auto, Recordatorio_DiasAnticipacion, Recordatorio_HorasMismoDia, Recordatorio_EnviarModo, Plantilla_Recordatorio, Recordatorio_HoraEnvio, Recordatorios_Auto_Tipo FROM Configuracion2");
    $stmt->execute();
    $configRecordatorio = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si valor Recordatorio_Auto = 0 (false) está desactivado
    if (!$configRecordatorio['Recordatorios_Auto']) {
        escribirLog("Recordatorios automáticos desactivados.", $logFile);
        exit;
    }

    if (!$configRecordatorio['Plantilla_Recordatorio']) {
        escribirLog("No hay plantilla configurada para los recordatorios.", $logFile);
        exit;    
    }
    
    $plantilla = $configRecordatorio['Plantilla_Recordatorio'] ?? null;
    if (!$plantilla) exit("No hay plantilla configurada para los recordatorios.");
    
    $tipoEnvio = $configRecordatorio['Recordatorios_Auto_Tipo'] ?? null;
    if (!$tipoEnvio) {
        escribirLog("No se ha definido el tipo de recordatorio automático (sms, email o whatsapp).", $logFile);
        exit;
    }

    // Variable guardará nombre de campo en BD para filtrar en la consulta 
    $campoRecordatorio = match($tipoEnvio) {
    'email'    => 'Recordatorio_Email',
    'sms'      => 'Recordatorio_Sms',
    'whatsapp' => 'Recordatorio_WA',
    default    => null,
    };

    if (!$campoRecordatorio) {
        escribirLog("Tipo de recordatorio automático no configurado correctamente.", $logFile);
        exit;
    }
    
    $modo = $configRecordatorio['Recordatorio_EnviarModo']; // "mismoDia" o "diasAnticipacion"
    $ahora = new DateTime();
    $fechaHoy = $ahora->format('Y-m-d');
    
     // --- Determinar fecha y hora de envío ---
    if ($modo === 'mismoDia') {
        // Enviar recordatorios para hoy si faltan menos de Recordatorio_HorasMismoDia horas
        $horaLimite = $ahora->format('H:i');
        $stmt = $conn->prepare("
            SELECT Ref_Agenda, Fecha, Hora, Concepto, Notas, Usuario
            FROM Agenda
            WHERE CONVERT(date, Fecha) = :fecha
            AND $campoRecordatorio = 0
        ");
        $stmt->execute([':fecha' => $fechaHoy]);
        $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filtrar por horas de anticipación
        $citas = array_filter($citas, function($cita) use ($ahora, $configRecordatorio) {
            $horaCita = new DateTime($cita['Hora']);
            // Calcula la diferencia en horas entre la cita y el momento actual ($ahora)
            $diffHoras = ($horaCita->getTimestamp() - $ahora->getTimestamp()) / 3600;
            // Mantiene en el array las citas que están dentro del rango de anticipación
            // Serán las citas a las que se enviará recordatorio automático
            return $diffHoras <= $configRecordatorio['Recordatorio_HorasMismoDia'] && $diffHoras >= 0;
        });
    } else { // diasAnticipacion
        $diasAnticipacion = (int)$configRecordatorio['Recordatorio_DiasAnticipacion'];
        // Crea objeto DateTime y se le añaden días de anticipación
        $fechaEnvio = (new DateTime())->modify("+$diasAnticipacion days")->format('Y-m-d');
        $horaEnvio = $configRecordatorio['Recordatorio_HoraEnvio'] ?? '00:00';
        $horaActual = $ahora->format('H:i');

        if ($horaActual < $horaEnvio) {
            escribirLog("Aún no es hora de enviar recordatorios programados $diasAnticipacion días antes.", $logFile);
            exit;
        }

        $stmt = $conn->prepare("
            SELECT Ref_Agenda, Fecha, Hora, Concepto, Notas, Usuario
            FROM Agenda
            WHERE CONVERT(date, Fecha) = :fecha
            AND $campoRecordatorio = 0
        ");
        $stmt->execute([':fecha' => $fechaEnvio]);
        $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($citas)) {
         escribirLog("No hay citas pendientes para enviar recordatorio automático.", $logFile);
        exit;
    }

    $resultado = [];

    // --- Enviar recordatorios ---
    foreach ($citas as $cita) {
        preg_match('/Email:\s*([^\s]+)\s*Telefono:\s*([\d\+\-\s]+)/', $cita['Notas'], $matches);
        $cita['Email'] = $matches[1] ?? null;
        $cita['Telefono'] = $matches[2] ?? null;

        if (!$cita['Email'] && !$cita['Telefono']) continue;

        //$nombreUsuario = obtenerNombreUsuario($conn, $cita['Usuario']);
        $reemplazos = [
            '{{fecha}}'    => date('d/m/Y', strtotime($cita['Fecha'])),
            '{{hora}}'     => date('H:i', strtotime($cita['Hora']))
        ];
        $mensaje = str_replace(array_keys($reemplazos), array_values($reemplazos), $plantilla);
        
        // Cargar la configuración desde config.ini. Devuelve un array asociativo con secciones ([database], [email] y otras si hubiera):
        $config = parse_ini_file(__DIR__ . '/../src/config/config.ini', true);

        switch ($tipoEnvio) {
            case 'email':
                if ($cita['Email']) {
                    if (!$config['email']) throw new Exception("No hay configuración de email en config.ini");
                    $header = '✂️ Recordatorio de cita';
                    $res = enviarEmailRecordatorio($cita, $config, $header, $mensaje, $conn);
                    // Quitar etiquetas HTML antes de escribir en el log
                    $resLimpio = strip_tags($res);
                    escribirLog($resLimpio, $logFile);
                }
                break;
            case 'sms':
                if ($cita['Telefono']) {
                    $remitente = $config['sms']['remitente'];
                    $res = enviarSmsRecordatorio($cita, $mensaje, $remitente, $conn);
                    $resLimpio = strip_tags($res);
                    escribirLog($resLimpio, $logFile);
                }
                break;
            case 'whatsapp':
                if ($cita['Telefono']) {
                    $sid = $config['whatsapp']['sid'];
                    $token =$config['whatsapp']['tokenTwilio'];
                    $telefonoTwilioWA = $config['whatsapp']['telefonoTwilioWA'];
                    $res = enviarWaRecordatorio($cita, $sid, $token, $telefonoTwilioWA, $conn);
                    $resLimpio = strip_tags($res);
                    escribirLog($resLimpio, $logFile);
                }
                break;
        }
    }
} catch (Exception $e) {
    escribirLog("Error general: " . $e->getMessage(), $logFile);
    if (php_sapi_name() !== 'cli') {
        echo "❌ Error general: " . $e->getMessage();
    }
}
    
    

