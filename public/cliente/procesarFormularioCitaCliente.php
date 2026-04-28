<?php
session_start();
//require_once __DIR__ . '/../../src/auth/checkSession.php';
require_once __DIR__ . '/../../src/config/paths.php';
include_once __DIR__ . "/../../src/config/database.php";
include_once __DIR__ . "/../../src/lib/utils.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php'; // carga PHPMailer

$resultado = '';
$mostrarMenu = false; // así el header no mostrará los botones

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Filtrar campos que llegan por $_POST usando función (en lib/utils.php)
    $campos = filtrarCamposCita($_POST);
    extract($campos); // crea variables $cliente, $telefono, $emailCliente, etc.

    $duracion = 0;

    $errores = [];

    if ($cliente === '' || strlen($cliente) < 3) {
        $errores[] = "🆎 El nombre del cliente debe tener al menos 3 caracteres.";
    }

    if (!preg_match('/^[0-9]{9}$/', $telefono)) {
        $errores[] = "⚙ El teléfono debe tener exactamente 9 dígitos.";
    }

    if (!filter_var($emailCliente, FILTER_VALIDATE_EMAIL) || !preg_match('/\.[a-zA-Z]{2,}$/', $emailCliente)) {
        $errores[] = "📩 El email no es válido.";
    }

    if (!empty($errores)) {  // Si existen errores de validación redirige a index.php (formulario) con función específica
        // Al pasar superglobal $_POST ya introducimos los valores recibidos del formulario en la función
        volverFormularioCitaCliente($errores, $_POST, $basePath);
    } else {
        try {
            $conn = conectarPDO();
            
            // Obtener duración del servicio
            $stmt = $conn->prepare("SELECT Horas_Servicio, Minutos_Servicio FROM Articulos WHERE nombre = :servicio AND web = 1 AND borrado = 0");
            $stmt->execute([':servicio' => $servicio]);
            $duracion = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($duracion) {
                $horasDur = (int)$duracion['Horas_Servicio'];
                $minutosDur = (int)$duracion['Minutos_Servicio'];
                $duracion = $horasDur . "h " . $minutosDur . "min";
            } else {
                $horasDur = 0;
                $minutosDur = 0;
                $duracion = "-";
            }
        
            // Calcular hora de finalización 
            $inicio = new DateTime($horaInicioCompleta);
            $inicio->modify("+$horasDur hours +$minutosDur minutes");
            $horaFin = $inicio->format("H:i");

            // Formatear fechas y horas para SQL Server
            $fechaSQL = (new DateTime($fecha))->format('Y-m-d\T00:00:00');
            
            // Para las columnas Hora (Inicio) y Hora_Fin (Fin)
            $fechaHoraInicioSQL = (new DateTime($fecha . ' ' . $horaInicioCompleta))->format('Y-m-d\TH:i:s');
            $fechaHoraFinSQL = (new DateTime($fecha . ' ' . $horaFin))->format('Y-m-d\TH:i:s');

            // Inserción de datos de la cita en la tabla Agenda
            // Obtener el último Ref_Agenda (PK)
            $stmt = $conn->query("SELECT MAX(Ref_Agenda) AS ultimo FROM Agenda");
            $ultimo = $stmt->fetch(PDO::FETCH_ASSOC)['ultimo'] ?? 0; // si está vacío, usar 0
            $nuevoRef_Agenda = $ultimo + 1;

            // Ruta al config.ini
            $config = parse_ini_file(__DIR__ . '/../../src/config/config.ini', true);
            // Terminal seleccionado (si no hay, terminal 1 por defecto)
            $terminalId = isset($config['config']['Ref_Terminal']) ? (int)$config['config']['Ref_Terminal'] : 1;

            // Seleccionar las ubicaciones disponibles desde el terminal seleccionado (actualmente es el terminal 3)
            // newid() mezcla aleatoriamente las filas y top 1 devuelve sólo una.
            $stmt = $conn->prepare("SELECT TOP 1 Ref_Cabecera_Agenda FROM Agenda_Cabeceras WHERE Terminal = :terminalId ORDER BY NEWID()");
            $stmt->execute(['terminalId' => $terminalId]);

            $ubicacion = $stmt->fetchColumn();

            /*  CONFLICTO DESHABILITADO A LA ESPERA DE CONFIRMAR FUNCIONAMIENTO
            Necesitamos saber si cliente puede elegir el usuario (peluquero) o no. 

            // Comprobar si hay conflicto horario en la Agenda
            $stmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM Agenda
                WHERE Fecha = :fecha
                AND (
                    Usuario = :usuario
                    OR Ubicacion = :ubicacion
                )
                AND (
                    (Hora < :hora_fin)
                    AND (Hora_Fin > :hora_inicio)
                )
            ");

            // Ejecutar con los parámetros
            $stmt->execute([
                ':fecha' => $fechaSQL,
                ':usuario' => $usuario,
                ':ubicacion' => $ubicacion,
                ':hora_inicio' => $fechaHoraInicioSQL,
                ':hora_fin' => $fechaHoraFinSQL
            ]);

            $conflicto = $stmt->fetchColumn();

            

            if ($conflicto > 0) {
                // Guardar error y datos del formulario para volver al index
                volverFormularioCitaCliente(["❌ La hora seleccionada o el usuario está ocupado. Elige otra opción."], $_POST, $basePath);
            }
            */
            $stmt = $conn->prepare("
                INSERT INTO Agenda (Ref_Agenda, Fecha, Hora, Usuario, Concepto, Estado, Terminal, Ref_Cliente, Cliente_Externo, Ubicacion, Hora_Fin, Notas, Color, Ref_Servicio, Cliente) 
                VALUES (:ref_agenda, :fecha, :hora, :usuario, :concepto, 'N', :terminalId, NULL, 0, :ubicacion, :hora_fin, :notas, 'No usar color', NULL, :cliente)
            ");

            // Crear nota interna para guardar en BD la nota del cliente, email y teléfono
            $notasInterna = $notas . " Email: " . $emailCliente . " Telefono: " . $telefono;

            $stmt->execute([
                ':ref_agenda' => $nuevoRef_Agenda,
                ':fecha' => $fechaSQL,
                ':hora' => $fechaHoraInicioSQL,
                ':usuario' => $usuario,
                ':concepto' => $servicio,
                ':terminalId' => $terminalId,
                ':ubicacion' => $ubicacion,
                ':hora_fin' => $fechaHoraFinSQL,
                ':notas' => $notasInterna,
                ':cliente' => $cliente
            ]);

            // Si acceso e inserción a BD correcto, se configura el correo para el envío. Se usa cuenta fct@eclipse.es simulando origen (dueño)
            $mail = new PHPMailer(true);
            
            // Cargar la configuración. Devuelve un array asociativo con secciones ([database], [email] y otras si hubiera):
            $config = parse_ini_file(__DIR__ . '/../../src/config/config.ini', true);

            // Configuración SMTP (se usan variables configuradas en /src/config/config.ini)
            $mail->isSMTP();
            $mail->Host = $config['email']['host'];    // servidor SMTP
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
            $mail->addAddress($emailCliente, $cliente); // cliente
            
            // Correo al dueño de forma oculta
            if (!empty($config['email']['bcc'])) {
                $mail->addBCC($config['email']['bcc'], 'Dueño');
            }
            // Contenido del email
            $mail->isHTML(true);
            $mail->Subject = 'Solicitud de cita';

            // Obtener nombre del usuario (al enviar email irá su nombre, no Ref_Usuario)
            $stmt = $conn->prepare("SELECT Nombre_Usuario FROM Usuarios WHERE Ref_Usuario = :usuario");
            $stmt->execute([':usuario' => $usuario]);
            $nombreUsuario = $stmt->fetch(PDO::FETCH_COLUMN);

            // Usar plantilla para solicitud de cita (src/lib/utils.php)
            $mail->Body = plantillaEmailCita("¡Tu cita ha sido solicitada!\nPronto confirmaremos tu cita.", [
                'cliente' => $cliente,
                'telefono' => $telefono,
                'email' => $emailCliente,
                'servicio' => $servicio,
                'fecha' => $fecha,
                'horaInicio' => $horaInicioCompleta,
                'horaFin' => $horaFin,
                'duracion' => $duracion,
                'usuario' => $nombreUsuario,
                'notas' => $notas
            ]);

            // Texto alternativo para clientes que no leen HTML
            $mail->AltBody = "Tu cita ha sido solicitada\nCliente: $cliente\nTeléfono: $telefono\nEmail: $emailCliente\nServicio: $servicio\nFecha: $fecha\nHora: $horaInicioCompleta\nUsuario: $usuario\nNotas: $notas";

            $mail->CharSet = 'UTF-8';  // Define codificación de caracteres
            $mail->Encoding = 'base64';  // Asegura envío de caracteres especiales correctamente por mail

            $mail->send();
            $resultado = '<div class="alert alert-success" role="alert">✅ Cita solicitada. Se ha enviado un correo con los datos de la misma.</div>';

        } catch (PDOException $e) {
            $resultado = '<div class="alert alert-danger" role="alert">
                ❌ Error al guardar la cita: ' . htmlspecialchars($e->getMessage()) . '</div>';     
        
        } catch (Exception $e) {
            // Si el error vino del correo, mostramos info del mail
            if (isset($mail)) {
                $resultado = '<div class="alert alert-danger" role="alert">
                    ❌ Error al enviar correo: ' . htmlspecialchars($mail->ErrorInfo) . '</div>';
            } else {
                // Si el error vino de otra parte (como el conflicto horario)
                $resultado = '<div class="alert alert-danger" role="alert">'
                    . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar cita</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/style_base.css">
</head>
<body>
    <!-- header -->
    <?php include __DIR__ . '/../../partials/header.php'; ?>

    <div id="form-cita" action="" style="margin-top: 1rem;">
        <?php echo $resultado; ?>
        <a href="formularioCitaCliente.php" class="btn btn-secondary">Volver al formulario</a>
    </div>

    <!-- Footer -->
    <?php include __DIR__ . '/../../partials/footer.php'; ?>
</body>
</html>