<?php 
require_once __DIR__ . '/../config/paths.php';
/**
 * Filtra el campo introducido por parámetro y lo devuelve filtrado
 *
 * @param string|int $campo  Campo a ser filtrado
 * @return string Campo filtrado
 */
function filtrarCampo($campo)
{
    if (!isset($campo)) {  // Evita errores si el valor es null o no está definido
        return '';
    }
    // Convertir a string por seguridad. Asegura que devuelve siempre una cadena (incluso si se pasa un número)
    $campo = (string) $campo;

    $campo = trim($campo);
    $campo = stripslashes($campo);
    $campo = htmlspecialchars($campo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    /*
    ENT_QUOTES → escapa comillas simples y dobles
    ENT_SUBSTITUTE → reemplaza caracteres inválidos en lugar de fallar
    'UTF-8' → evita problemas con caracteres internacionales
    */
    return $campo;
}

/**
 * Redirige al formulario guardando errores y datos en sesión
 */
function volverFormularioCitaCliente($errores, $datosFormulario, $basePath) {
    // Usamos $basePath generado en src/config/paths.php
    $_SESSION['errores'] = $errores;
    $_SESSION['formulario'] = $datosFormulario;
    // Usamos $basePath generado en src/config/paths.php
    header("Location: {$basePath}cliente/formularioCitaCliente.php");
    exit;
}

/**
 * Filtra los campos de un formulario de cita y devuelve un array con los valores.
 * Incluye hora completa concatenada.
 */
function filtrarCamposCita(array $datos): array {
    $cliente = filtrarCampo($datos['cliente'] ?? '');
    $telefono = filtrarCampo($datos['telefono'] ?? '');
    $emailCliente = filtrarCampo($datos['email'] ?? '');
    $servicio = filtrarCampo($datos['servicio'] ?? '');
    $fecha = filtrarCampo($datos['fecha'] ?? '');
    $horaInicioHora = filtrarCampo($datos['horaInicioHora'] ?? '');
    $horaInicioMinutos = filtrarCampo($datos['horaInicioMinutos'] ?? '');
    $horaInicioCompleta = $horaInicioHora . ':' . $horaInicioMinutos;
    $usuario = filtrarCampo($datos['usuario'] ?? 0);  // Valor 0 al solicitar cita el cliente, viene vacío
    $notas = filtrarCampo($datos['notas'] ?? '');

    return compact(
        'cliente', 'telefono', 'emailCliente', 'servicio', 
        'fecha', 'horaInicioHora', 'horaInicioMinutos', 
        'horaInicioCompleta', 'usuario', 'notas'
    );
}

/**
 * Rellena la plantilla con el título y los datos de la cita
 *
 * @param [type] $titulo    Título del email
 * @param [type] $datos     Datos para rellenar la plantilla
 * @return void     Plantilla con el título y los datos cubiertos
 */
function plantillaEmailCita($titulo, $datos) {
  
    return "
    <html>
    <head>
        <style>
            .card {
                background-color: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                font-family: Arial, sans-serif;
            }
            .card h2 { color: #ff6600; }
            .card p { margin: 5px 0; }
            .label { font-weight: bold; color: #333; }
        </style>
    </head>
    <body>
        <div class='card'>
            <h2>$titulo</h2>
            <p><span class='label'>Cliente:</span> {$datos['cliente']}</p>
            <p><span class='label'>Teléfono:</span> {$datos['telefono']}</p>
            <p><span class='label'>Email:</span> {$datos['email']}</p>
            <p><span class='label'>Servicio:</span> {$datos['servicio']}</p>
            <p><span class='label'>Fecha:</span> {$datos['fecha']}</p>
            <p><span class='label'>Hora inicio:</span> {$datos['horaInicio']}</p>
            <p><span class='label'>Hora finalización:</span> {$datos['horaFin']}</p>
            <p><span class='label'>Duración del servicio:</span> {$datos['duracion']}</p>
            <p><span class='label'>Usuario:</span> {$datos['usuario']}</p>
            <p><span class='label'>Notas:</span> {$datos['notas']}</p>
        </div>
    </body>
    </html>";
}

/**
 * Obtiene el nombre de un usuario a partir de su ID.
 * Devuelve cadena vacía si no se encuentra o hay error.
 *
 * @param PDO $conn Conexión PDO a la BD
 * @param int $usuarioId ID del usuario
 * @return string Nombre del usuario
 */
function obtenerNombreUsuario(PDO $conn, int $usuarioId): string
{
    try {
        $stmt = $conn->prepare("SELECT Usuario FROM Usuarios WHERE Ref_Usuario = :id");
        $stmt->execute([':id' => $usuarioId]);
        $nombre = $stmt->fetchColumn();

        return $nombre !== false ? $nombre : 'Sin asignar';
    } catch (PDOException $e) {
        // Aquí podrías registrar el error en un log si quieres
        return 'Error: Nombre no disponible';
    }
}

/**
 * Formatea los campos Fecha (d/m/Y) y Hora y Hora_Fin (H:i) de las citas pasadas como parámetro
 *
 * @param array $rows   Conjunto de citas a formatear
 * @return array    Citas con 3 nuevos campos que contienen los datos formateados
 */
function formatearCitas(array $rows) : array {
    return array_map(function($c) {
        $c['Fecha_formateada'] = !empty($c['Fecha']) ? date('d/m/Y', strtotime($c['Fecha'])) : '';
        $c['Hora_formateada'] = !empty($c['Hora']) ? date('H:i', strtotime($c['Hora'])) : '';
        $c['Hora_Fin_formateada'] = !empty($c['Hora_Fin']) ? date('H:i', strtotime($c['Hora_Fin'])) : '';
        return $c;
    }, $rows);
}

/**
 * Muestra los mensajes de éxito o error que contengan las variables $_SESSION['success_msg'] o $_SESSION['error_msg']
 *
 * @return void
 */
function mostrarMensajesSESSION() {
    if (!empty($_SESSION['success_msg'])) {
        echo '
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            ' . $_SESSION['success_msg'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>';
        unset($_SESSION['success_msg']);
    } elseif (!empty($_SESSION['error_msg'])) {
        echo '
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            ' . $_SESSION['error_msg'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>';
        unset($_SESSION['error_msg']);
    }
}

?>