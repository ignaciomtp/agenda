<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Twilio\Rest\Client;

require_once __DIR__ . '/../../vendor/autoload.php'; // PHPMailer y Twilio
include_once __DIR__ . '/altiriaSMS.php';
include_once __DIR__ . '/WhatsAppService.php';
include_once __DIR__ . '/smsSender.inc.php';

/**
 * Gestiona el envío de email a través de PHPMailer
 *
 * @param array $cita       Datos de la cita
 * @param array $config     Datos de configuración de envío (se configuran en /src/config/config.ini)
 * @param string $header    Header que llevaré el email
 * @param string $mensaje   Contenido del email
 * @param PDO $conn         Conexión a la BD
 * @return string           Resultado del proceso de envío
 */
function enviarEmailRecordatorio(array $cita, array $config, string $header, string $mensaje, PDO $conn):string {
    try {
        $mail = new PHPMailer(true);
        // Configuración SMTP (se usan variables configuradas en /src/config/config.ini)
        $mail->isSMTP();
        $mail->Host = $config['email']['host']; // servidor SMTP
        $mail->SMTPAuth = true;
        $mail->Username = $config['email']['username'];  // correo
        $mail->Password = $config['email']['password'];   //  contraseña del correo
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL implícito
        $mail->Port = $config['email']['port'];

        // Configuración para que salte autenticación SSL
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        // Destinatarios
        $mail->setFrom($config['email']['username'], $config['email']['from_name']);
        $mail->addAddress($cita['Email']);
        $mail->isHTML(true);
        $mail->Subject = "📅 Información sobre tu cita";
        $mail->Body = "
            <html>
            <head>
            <style>
                .card { 
                max-width: 600px; 
                margin: 0 auto; 
                background-color: #fff; 
                padding: 20px; 
                border-radius: 12px; 
                box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
                font-family: Arial, sans-serif;
                }
                .header { background-color: #ff6600; color: #fff; text-align: center; padding: 15px; border-radius: 12px 12px 0 0; font-weight: bold; }
                .content { padding: 15px; text-align: center; }
            </style>
            </head>
            <body>
            <div class='card'>
                <div class='header'>" . $header . "</div>
                <div class='content'>
                <p>" . nl2br(htmlspecialchars($mensaje)) . "</p>
                </div>
            </div>
            </body>
            </html>
            ";
        $mail->AltBody = $mensaje;
        $mail->CharSet = 'UTF-8';  // Define codificación de caracteres
        $mail->Encoding = 'base64';  // Asegura envío de caracteres especiales correctamente por mail
        $mail->send();
        
        $result = '<div class="alert alert-success" role="alert">✅ Recordatorio enviado a ' . $cita['Email'] . '</div>';
        marcarRecordatorioEnviado($conn, $cita['Ref_Agenda'], 'email');
        return $result;
    } catch (Exception $e) {
        $result = '<div class="alert alert-danger" role="alert">❌ Error enviando correo a ' . $cita['Email'] . ' :' . $mail->ErrorInfo . '</div>';
        return $result;
    }
}

/**
 * Gestiona el envío de sms a través de Altiria
 *
 * @param array $cita       Datos de la cita
 * @param string $mensaje   Contenido del sms
 * @param string $remitente Remitente del sms
 * @param PDO $conn         Conexión a la BD
 * @return string           Resultado del proceso de envío
 */
function enviarSmsRecordatorio(array $cita, string $mensaje, string $remitente, PDO $conn): string {
    
    //$telefono = normalizarTelefono($cita['Telefono']);
    $telefono = "34661549036";
    $config = parse_ini_file(__DIR__ . '/../config/config.ini', true);
    $dinaUser = $config['dinahosting']['user'];
    $dinaPass = $config['dinahosting']['password'];
    $dinaAccount = $config['dinahosting']['account'];

    $mensaje = "Recuerda que tienes una cita con nosotros";

    $sms = new smsSender($dinaUser, $dinaPass, $dinaAccount);

    try {
        

        /*
        // Obtener credenciales Altiria del cliente desde la BD
        $stmt = $conn->prepare("SELECT SMS_Envio_Usuario, SMS_Envio_Contra FROM Configuracion2");
        $stmt->execute();
        $credenciales = $stmt->fetch(PDO::FETCH_ASSOC);

        // Credenciales Altiria (cada cliente puede tener sus propias)
        $usuario = $credenciales['SMS_Envio_Usuario'] ?? null;  // sustituye por el login de Altiria del cliente
        $clave = $credenciales['SMS_Envio_Contra'] ?? null;    // sustituye por la contraseña de Altiria del cliente

        if (!$usuario || !$clave) {
            return '<div class="alert alert-danger" role="alert">❌ No se encontraron credenciales Altiria para el cliente.';
        } 
        
        enviarSMSAltiria($telefono, $mensaje, $usuario, $clave, $remitente, false);
        */


        $sms->sendMessage([$telefono], $mensaje);  

        $result = '<div class="alert alert-success" role="alert">📱 SMS enviado a ' . $telefono . ' correctamente.</div>';
        marcarRecordatorioEnviado($conn, $cita['Ref_Agenda'], 'sms');
                
        return $result;
    } catch (Exception $e) {
        $result = '<div class="alert alert-danger" role="alert">❌ Error enviando SMS a ' . $telefono . ' — ' . $e->getMessage() . '</div>';
        return $result;
    }
}

/**
 * Gestiona el envío de whatsapp a través de Twilio
 *
 * @param array $cita               Datos de la cita
 * @param string $sid               sid de Twilio
 * @param string $token             Token de Twilio 
 * @param integer $telefonoTwilioWA Teléfono desde el que se envía el WA
 * @param PDO $conn                 Conexión a la BD
 * @return string                   Resultado del proceso de envío
 */
function enviarWaRecordatorio(array $cita, string $sid, string $token, string $telefonoTwilioWA, PDO $conn): string {
    $telefono = "34661549036";
    //$telefono = "34634610794";
    $mensaje = "Recuerda que tienes una cita con nosotros el ".$cita['Fecha']. " a las ".$cita['Hora'];

    $whatsapp = new WhatsAppService();

    try {
        /*
        $twilio = new Client($sid, $token);
        $telefono = normalizarTelefono($cita['Telefono']);
        
        $message = $twilio->messages
        ->create("whatsapp:+$telefono", 
            array(
            "from" => "whatsapp:+$telefonoTwilioWA",
            "body" => "Recuerda que tienes una cita con nosotros"
            )    
        );
        */
       
        $whatsapp->sendTemplate($telefono, 'plantilla3', $cita['Fecha'], $cita['Hora'], 'en');

        $result = '<div class="alert alert-success" role="alert">✅ WhatsApp enviado a ' . $telefono . '</div>';
        marcarRecordatorioEnviado($conn, $cita['Ref_Agenda'], 'whatsapp');
        return $result;
    } catch (Exception $e) {
        $result = '<div class="alert alert-danger" role="alert">❌ Error enviando WhatsApp a ' . $telefono . ' — ' . htmlspecialchars($e->getMessage()) . '</div>';
        return $result;
    }
}

/*
### IMPORTANTE ###
En documentación de Twilio (https://www.twilio.com/docs/whatsapp/quickstart) aparecen algunos apartados a incluir dentro de $message
algo diferentes a los que se usan con el Sandbox. Es probable que estos sean algún tipo de plantilla, como utiliza la API oficial de WhatsApp,
y en la versión de pago pidan las plantillas para validar con Meta.

$message = $twilio->messages->create(
    "whatsapp:+$telefono", // To
    [
        "from" => "whatsapp:+14155238886",
        "contentSid" => "HXb5b62575e6e4ff6129ad7c8efe1f983e",    
        "contentVariables" => json_encode([
            "1" => "22 July 2026",
            "2" => "3:15pm",
        ]),
    ]
);

Es probable que al utilizar las plantillas haya que modificar la lógica que le llega a esta función en public/citas/enviarRecordatoriosManual.php
El parámetro contentSid es el identificador de un mensaje preaprobado en WhatsApp que hay en Twilio.
Cada plantilla que se creamos y se aprueba en WhatsApp Business Manager recibe un contentSid en Twilio. Hay que recuperarla de alguna manera, por ejemplo,
almacenando el contentSid en la BD, al igual que se almacena la plantilla ("plantilla1"...) e introduciéndola con una variable (es diferente para cada plantilla)
En contentVariables se introducen los campos que se van a sustituir en la plantilla aprobada por Meta. En la aplicación, las plantillas están configuradas para
recibir únicamente fecha y hora, que se recogen del array $cita ($cita['Fecha'] y $cita['Hora']) en enviarRecordatorio(). Ejemplo:

$message = $twilio->messages->create(
    "whatsapp:+16285550100", // To
    [
        "from" => "whatsapp:+$telefonoTwilioWA",
        "contentSid" => "HXb5b62575e6e4ff6129ad7c8efe1f983e",    // Introducir una variable para este dato, ya que cambia para cada plantilla y el cliente la elige
        "contentVariables" => json_encode([
            "1" => $cita['Fecha'],
            "2" => $cita['Hora'],
        ]),
    ]
);
*/

/**
 * Formatear teléfono a número internacional (España)
 *
 * @param string $telefono  Teléfono a formatear
 * @return string   Número formateado
 */
function normalizarTelefono(string $telefono): string {
    $t = preg_replace('/\D/', '', $telefono);
    return str_starts_with($t, '34') ? $t : '34' . $t;
}

/**
 * Función que marca el tipo de recordatorio enviado, atendiendo al "tipo" que se recibe por GET
 *
 * @param PDO $conn     Conexión a la BD
 * @param integer $refAgenda    Número de referencia en la tabla Agenda (PK)
 * @param string $tipo  Tipo de envío
 * @return void
 */
function marcarRecordatorioEnviado(PDO $conn, int $refAgenda, string $tipo) {
    $campo = match($tipo) {
        'email'    => 'Recordatorio_Email',
        'sms'      => 'Recordatorio_Sms',
        'whatsapp' => 'Recordatorio_WA',
        default    => null,
    };

    if (!$campo) return false;

    $stmt = $conn->prepare("UPDATE Agenda SET $campo = 1 WHERE Ref_Agenda = :id");
    return $stmt->execute([':id' => $refAgenda]);
}

/**
 * Enviar recordatorio por WhatsApp usando WhatsApp Cloud API
 *
 * @param array $cita           Cita con datos de lal misma
 * @param string $waToken       Token de la API de WhatsApp
 * @param string $waPhoneId     Identificador de número de teléfono en Meta for developers
 * @param string $plantillaWA   Plantilla seleccionada para el envío del recordatorio
 * @param PDO $conn             Conexión a la BD
 * @return string               Resultado del proceso de envío
 */
/*
function enviarWaRecordatorio(array $cita, string $waToken, string $waPhoneId, string $plantillaWA, PDO $conn): string {
   
    try {
        $telefono = normalizarTelefono($cita['Telefono']);
        $url = "https://graph.facebook.com/v20.0/$waPhoneId/messages";

        // Parámetros de la plantilla
        $payload = [
            "messaging_product" => "whatsapp",
            "to" => "+$telefono",
            "type" => "template",
            "template" => [
                "name" => $plantillaWA,
                "language" => [
                    "code" => "es"
                ],
                "components" => [
                    [
                        "type" => "body",
                        "parameters" => [
                            ["type" => "text", "text" => $cita['Fecha']],
                            ["type" => "text", "text" => $cita['Hora']]
                        ]
                    ]
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $waToken",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode >= 200 && $httpcode < 300) {
            marcarRecordatorioEnviado($conn, $cita['Ref_Agenda'], 'whatsapp');
            return '<div class="alert alert-success">📩 WhatsApp enviado correctamente a ' . $telefono . '</div>';
        }

        return '<div class="alert alert-danger">❌ Error enviando WhatsApp: ' . htmlspecialchars($response) . '</div>';

    } catch (Exception $e) {
        return '<div class="alert alert-danger">❌ Excepción: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
*/