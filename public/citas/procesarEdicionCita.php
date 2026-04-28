<?php
require_once __DIR__ . '/../../src/auth/checkSession.php';
require_once __DIR__ . '/../../src/config/paths.php';
include_once __DIR__ . "/../../src/config/database.php";
include_once __DIR__ . "/../../src/lib/utils.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../../vendor/autoload.php'; // PHPMailer

$fechaActual = new DateTime();
$fechaActualStr = $fechaActual->format('Y-m-d');

// Obtener redirect desde POST si existe
if (!empty($_POST['redirect'])) {
    // Decodificamos la URL pasada como parámetro
    $redirect = urldecode($_POST['redirect']);
} else {
    // Redirect por defecto a mostrarCitas.php con fecha actual
    $redirect = "mostrarCitas.php?fecha=$fechaActualStr";
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    $_SESSION['error_msg'] = "⚠️ No se ha especificado ninguna cita.";
    header("Location: $redirect");
    exit;
}

$idCita = (int)$_POST['id'];

// Filtrar campos
$campos = filtrarCamposCita($_POST);
extract($campos); // $cliente, $telefono, $emailCliente, $servicio, $fecha, $horaInicioHora, $horaInicioMinutos, $horaInicioCompleta, $usuario, $notas

$errores = [];

// Validaciones
if ($cliente === '' || strlen($cliente) < 3) {
    $errores[] = "🆎 El nombre del cliente debe tener al menos 3 caracteres.";
}
if (!preg_match('/^[0-9]{9}$/', $telefono)) {
    $errores[] = "⚙ El teléfono debe tener exactamente 9 dígitos.";
}
if (!filter_var($emailCliente, FILTER_VALIDATE_EMAIL) || !preg_match('/\.[a-zA-Z]{2,}$/', $emailCliente)) {
    $errores[] = "📩 El email no es válido.";
}

if (!empty($errores)) {
    $_SESSION['errores'] = $errores;
    $_SESSION['formulario'] = $_POST;
    header("Location: editarCita.php?id=$idCita");
    exit;
}

try {
    $conn = conectarPDO();

    // Recuperar cita actual
    $stmt = $conn->prepare("SELECT Fecha, Hora, Cliente, Concepto, Usuario, Notas, Ubicacion FROM Agenda WHERE Ref_Agenda = :id");
    $stmt->execute([':id' => $idCita]);
    $citaActual = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$citaActual) {
        $_SESSION['error_msg'] = "⚠️ La cita no existe.";
        header("Location: $redirect");
        exit;
    }

    // Calcular hora fin
    $stmt = $conn->prepare("SELECT Horas_Servicio, Minutos_Servicio FROM Articulos WHERE nombre = :servicio AND web = 1 AND borrado = 0");
    $stmt->execute([':servicio' => $servicio]);
    $duracion = $stmt->fetch(PDO::FETCH_ASSOC);

    $horasDur = (int)($duracion['Horas_Servicio'] ?? 0);
    $minutosDur = (int)($duracion['Minutos_Servicio'] ?? 0);

    $inicio = new DateTime($horaInicioCompleta);
    $inicio->modify("+$horasDur hours +$minutosDur minutes");
    $horaFin = $inicio->format("H:i");

    // Formatear fechas y horas para SQL Server
    $fechaSQL = (new DateTime($fecha))->format('Y-m-d\T00:00:00');
    $fechaHoraInicioSQL = (new DateTime($fecha . ' ' . $horaInicioCompleta))->format('Y-m-d\TH:i:s');
    $fechaHoraFinSQL = (new DateTime($fecha . ' ' . $horaFin))->format('Y-m-d\TH:i:s');

    // Comprobar conflicto de horarios para el mismo usuario
    // Ref_Agenda != :id ignora la cita que estamos editando para no chocar consigo misma
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM Agenda
        WHERE Fecha = :fecha
        AND Ref_Agenda <> :id
        AND (Usuario = :usuario OR Ubicacion = :ubicacion)
        AND (Hora < :hora_fin AND Hora_Fin > :hora_inicio)
    ");

    // Para la ubicación usamos la misma que tiene la cita actual
    $ubicacion = $citaActual['Ubicacion'];

    $stmt->execute([
        ':fecha' => $fechaSQL,
        ':usuario' => $usuario,
        ':ubicacion' => $ubicacion,
        ':hora_inicio' => $fechaHoraInicioSQL,
        ':hora_fin' => $fechaHoraFinSQL,
        ':id' => $idCita
    ]);

    /*Si no se permite conflicto entre horas de citas y usuario ocupado, activar este código
    $conflicto = $stmt->fetchColumn();

    if ($conflicto > 0) {
        $_SESSION['errores'] = ["❌ La hora seleccionada o el usuario está ocupado. Elige otra opción."];
        $_SESSION['formulario'] = $_POST;
        header("Location: editarCita.php?id=$idCita&redirect=$redirect");
        exit;
    }
    */

    // Limpiar notas actuales para evitar duplicar email y teléfono
    $notasOriginal = $notas ?? '';
    $notasSolo = preg_replace('/\s*Email:\s*[^\s]+\s*Telefono:\s*[\d\+\-\s]+/', '', $notasOriginal);
    $notasInterna = trim($notasSolo) . " Email: $emailCliente Telefono: $telefono";

    // Actualizar cita
    $stmt = $conn->prepare("
        UPDATE Agenda
        SET Fecha = :fecha,
            Hora = :hora,
            Usuario = :usuario,
            Concepto = :concepto,
            Ubicacion = :ubicacion,
            Hora_Fin = :hora_fin,
            Notas = :notas,
            Cliente = :cliente
        WHERE Ref_Agenda = :id
    ");

    $stmt->execute([
        ':fecha' => $fechaSQL,
        ':hora' => $fechaHoraInicioSQL,
        ':usuario' => $usuario,
        ':concepto' => $servicio,
        ':ubicacion' => $ubicacion,
        ':hora_fin' => $fechaHoraFinSQL,
        ':notas' => $notasInterna,
        ':cliente' => $cliente,
        ':id' => $idCita
    ]);

    // Solo enviar email si cambió fecha o hora
    // Convierte ambos valores a DateTime y compara los timestamps en vez de la cadena
    $fechaActual = new DateTime($citaActual['Fecha']);
    $horaActual = new DateTime($citaActual['Hora']);

    $fechaInicio = new DateTime($fecha . ' ' . $horaInicioCompleta);

    $fechaChanged = $fechaActual->format('Y-m-d') !== $fechaInicio->format('Y-m-d');
    $horaChanged = $horaActual->format('H:i') !== $fechaInicio->format('H:i');


    if ($fechaChanged || $horaChanged) {
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
        $mail->addAddress($emailCliente, $cliente);

        // Contenido del email
        $mail->isHTML(true);
        $mail->Subject = '📅 Modificación en tu cita';

        // Obtener nombre del usuario (al enviar email irá su nombre, no Ref_Usuario)
        $stmt = $conn->prepare("SELECT Usuario FROM Usuarios WHERE Ref_Usuario = :usuario");
        $stmt->execute([':usuario' => $usuario]);
        $nombreUsuario = $stmt->fetch(PDO::FETCH_COLUMN);

        $mail->Body = plantillaEmailCita("¡Tu cita ha sido modificada!", [
            'cliente' => $cliente,
            'telefono' => $telefono,
            'email' => $emailCliente,
            'servicio' => $servicio,
            'fecha' => $fecha,
            'horaInicio' => $horaInicioCompleta,
            'horaFin' => $horaFin,
            'duracion' => "$horasDur h $minutosDur min",
            'usuario' => $nombreUsuario,
            'notas' => $notasSolo
        ]);
        $mail->AltBody = "Tu cita ha sido modificada.\nCliente: $cliente\nTeléfono: $telefono\nEmail: $emailCliente\nServicio: $servicio\nFecha: $fecha\nHora: $horaInicioCompleta\nNotas: $notas";
        
        $mail->CharSet = 'UTF-8';  // Define codificación de caracteres
        $mail->Encoding = 'base64';  // Asegura envío de caracteres especiales
        
        $mail->send();
    }

    $_SESSION['success_msg'] = "✅ La cita se ha actualizado correctamente.";
    header("Location: $redirect");
    exit;

} catch (PDOException $e) {
    $_SESSION['error_msg'] = "❌ Error al actualizar la cita: " . $e->getMessage();
    header("Location: $redirect");
    exit;
} catch (Exception $e) {
    $_SESSION['error_msg'] = "❌ Error al enviar correo: " . ($mail->ErrorInfo ?? $e->getMessage());
    header("Location: $redirect");
    exit;
}
