<?php
require_once __DIR__ . '/../../src/auth/checkSession.php';
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/lib/utils.php';

// Defaults & input normalization
$usuarioSeleccionado = $_GET['usuario'] ?? 'individual';
// Si el usuario también selecciona un usuario concreto desde el <select>, priorizamos eso
$usuarioFiltro = isset($_GET['Ref_Usuario']) && $_GET['Ref_Usuario'] !== '' ? (int)$_GET['Ref_Usuario'] : null;
$fechaSeleccionada = $_GET['fecha'] ?? date('Y-m-d'); // formato Y-m-d

// Ruta al config.ini
$config = parse_ini_file(__DIR__ . '/../../src/config/config.ini', true);
// Terminal seleccionado (si no hay, terminal 1 por defecto)
$terminalId = isset($config['config']['Ref_Terminal']) ? (int)$config['config']['Ref_Terminal'] : 1;

$conn = conectarPDO();
$error = null;

try {
    // 1) Obtener horarios del terminal (una única consulta)
    $stmt = $conn->prepare("
        SELECT Agenda_Hora_Ini, Agenda_Hora_Fin, Agenda_Hora_Ini_Tarde, Agenda_Hora_Fin_Tarde
        FROM Terminales_Venta
        WHERE Ref_Terminal = ?
    ");
    $stmt->execute([$terminalId]);
    $horarios = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    // Variables por defecto si vienen vacías
    $horaIniManana  = !empty($horarios['Agenda_Hora_Ini']) ? (int)$horarios['Agenda_Hora_Ini'] : 7;
    $horaFinManana  = !empty($horarios['Agenda_Hora_Fin']) ? (int)$horarios['Agenda_Hora_Fin'] : 23;
    $horaIniTarde   = isset($horarios['Agenda_Hora_Ini_Tarde']) ? (int)$horarios['Agenda_Hora_Ini_Tarde'] : null;
    $horaFinTarde   = isset($horarios['Agenda_Hora_Fin_Tarde']) ? (int)$horarios['Agenda_Hora_Fin_Tarde'] : null;

    // Ajustamos el inicio y fin total del día
    $horaInicioDia = $horaIniManana;
    // Si no hay horario de tarde, usamos fin mañana; si hay tarde y fin tarde > fin mañana, usamos fin tarde
    $horaFinDia = $horaFinManana;
    if ($horaFinTarde !== null && $horaFinTarde > $horaFinManana) {
        $horaFinDia = $horaFinTarde;
    }

    // 2) Citas sin usuario asignado
    $stmt = $conn->prepare("
        SELECT Ref_Agenda, Fecha, Hora, Hora_Fin, Cliente, Concepto, Notas
        FROM Agenda
        WHERE Usuario = 0
        ORDER BY Fecha, Hora
    ");
    $stmt->execute();
    $citasSinUsuario = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // 3) Usuarios disponibles para la agenda 
    $stmt = $conn->query("SELECT Ref_Usuario, Usuario FROM Usuarios WHERE PV_Agenda = 1 AND Fecha_Baja IS NULL ORDER BY Usuario");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Si modo 'individual' y no viene Ref_Usuario, elegimos el primero disponible
    if ($usuarioSeleccionado === 'individual' && !$usuarioFiltro && !empty($usuarios)) {
        $usuarioFiltro = (int)$usuarios[0]['Ref_Usuario'];
    }

    // 4) Obtener citas (filtradas por usuarios válidos y terminal)
    $refUsuariosValidos = array_map('intval', array_column($usuarios, 'Ref_Usuario'));
    if (empty($refUsuariosValidos)) {
        $citas = []; // No hay usuarios válidos
    } else {
        // Construimos SQL dinámico solo si es necesario (IN con los usuarios válidos)
        $placeholders = implode(',', array_fill(0, count($refUsuariosValidos), '?'));
        $sql = "SELECT Ref_Agenda, Fecha, Hora, Hora_Fin, Cliente, Concepto, Usuario, Notas
                FROM Agenda
                WHERE CONVERT(date, Fecha, 23) = CONVERT(date, ?, 23)
                  AND Usuario IN ($placeholders)
                  AND Terminal = ?";

        $params = array_merge([$fechaSeleccionada], $refUsuariosValidos, [$terminalId]);

        if ($usuarioFiltro) {
            $sql .= " AND Usuario = ?";
            $params[] = $usuarioFiltro;
        }

        $sql .= " ORDER BY Hora";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $citas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // 5) UsuariosConCitas: siempre devuelve un array (vacío o con filas)
    if ($usuarioSeleccionado === 'todos') {
        $stmt = $conn->prepare("
            SELECT DISTINCT a.Usuario AS Ref_Usuario, u.Usuario
            FROM Agenda a
            JOIN Usuarios u ON a.Usuario = u.Ref_Usuario
            WHERE CONVERT(date, a.Fecha, 23) = CONVERT(date, :fecha, 23)
            ORDER BY u.Usuario
        ");
        $stmt->execute(['fecha' => $fechaSeleccionada]);
        $usuariosConCitas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else if ($usuarioSeleccionado === 'individual') {
        $stmt = $conn->prepare("
            SELECT DISTINCT a.Usuario AS Ref_Usuario, u.Usuario
            FROM Agenda a
            JOIN Usuarios u ON a.Usuario = u.Ref_Usuario
            WHERE CONVERT(date, a.Fecha, 23) = CONVERT(date, :fecha, 23)
              AND a.Usuario = :Ref_Usuario
        ");
        $stmt->execute([
            'fecha' => $fechaSeleccionada,
            'Ref_Usuario' => $usuarioFiltro
        ]);
        // fetchAll para garantizar array
        $usuariosConCitas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        $usuariosConCitas = [];
    }

} catch (PDOException $ex) {
    $error = "Error de base de datos: " . $ex->getMessage();
    // Preferimos valores vacíos a fallar más adelante
    $citas = $citas ?? [];
    $citasSinUsuario = $citasSinUsuario ?? [];
    $usuarios = $usuarios ?? [];
    $usuariosConCitas = $usuariosConCitas ?? [];
}

// 6) Normalizar/formatear Fecha, Hora y Hora_Fin en todas las citas (d/m/Y) y (H:i)
$citas = formatearCitas($citas);
$citasSinUsuario = formatearCitas($citasSinUsuario);

// Asegurar variables definidas para el template
$usuarios = $usuarios ?? [];
$usuariosConCitas = $usuariosConCitas ?? [];
$citas = $citas ?? [];
$citasSinUsuario = $citasSinUsuario ?? [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/style_base.css">
    <link rel="stylesheet" href="../css/agenda_citas.css">
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../../partials/header.php'; ?>

    <h2 class="text-center mb-4">Agenda</h2>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="agenda-layout">
        <div class="container-white flex-4">
            <form method="GET" action="agendaCitas.php" class="filtros-agenda d-flex align-items-center justify-content-center gap-3">
                <input type="hidden" name="usuario" value="<?= $usuarioSeleccionado ?>" />
                <div class="d-flex align-items-center gap-2">
                    <div class="selector-fecha d-flex align-items-center gap-2">
                        <button type="button" id="prevBtn">&#9664;</button>
                        <input type="date" name="fecha" id="fecha" value="<?= htmlspecialchars($fechaSeleccionada) ?>">
                        <button type="button" id="nextBtn">&#9654;</button>
                    </div>
                    <?php if($usuarioSeleccionado === "individual"): ?>
                    <div class="selector-usuario d-flex align-items-center gap-2">
                        <label for="usuario" class="mb-0">Usuario:</label>
                        <select name="Ref_Usuario" id="Ref_Usuario" required>
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?= htmlspecialchars($u['Ref_Usuario']) ?>" <?= ($usuarioFiltro == $u['Ref_Usuario'] ? 'selected' : '') ?>>
                                    <?= htmlspecialchars($u['Usuario']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <?php endif ?>
                    <button type="submit" class="btn btn-primary ms-2">Filtrar</button>
                </div>
            </form>
            
            <!-- Mensajes de éxito o error en $_SESSION['success_msg'] o $_SESSION['error_msg'] -->
            <?php mostrarMensajesSESSION() ?>

            <div class="agenda-container">
                <div class="agenda" style="--cols: <?= count($usuarios) ?>;">
                    <!-- Fila superior: nombres de usuarios -->
                    <div id="header-usuarios" class="header-usuarios"></div>
                    <div class="zona-cuerpo">
                        <div id="zona-calendario" class="zona-calendario">
                            <?php for ($h = $horaInicioDia; $h <= $horaFinDia; $h++): ?>
                            <div class="fila-hora">
                                <div class="hora-label"><?= sprintf('%02d:00', $h) ?></div>
                                <div class="zona-calendario-fila" data-hora="<?= $h ?>"></div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="container-white citas-sin-usuario flex-1" >
            <h3 class="text-center mb-3">Citas sin usuario</h3>
            <?php if (!empty($errorSinUsuario)): ?>
                <div class="alert alert-danger text-center"><?= htmlspecialchars($errorSinUsuario) ?></div>
            <?php else: ?>
                <?php if(empty($citasSinUsuario)): ?>
                <p>No hay citas pendientes de asignar usuario</p>
                <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($citasSinUsuario as $c): ?>
                        <li class="list-group-item">
                            <span>
                                <b><?= htmlspecialchars($c['Fecha_formateada']) ?></b>
                                <i class="bi bi-arrow-right-square"></i>
                                <?= htmlspecialchars($c['Cliente']) ?> - <?= htmlspecialchars($c['Concepto']) ?> 
                                (<?= htmlspecialchars($c['Hora_formateada']) ?> - <?= htmlspecialchars($c['Hora_Fin_formateada']) ?>)
                            </span>
                            <button type="button" class="btn-asignar btn btn-warning" data-id="<?= $c['Ref_Agenda'] ?>">Asignar</button>
                        </li>
                    <?php endforeach ?>
                </ul>
                <?php endif ?>
            <?php endif ?>
        </div>
    </div>

    <!-- Footer -->
    <?php include __DIR__ . '/../../partials/footer.php'; ?>

    <!-- Modal para ver detalles de la cita -->
    <div class="modal fade" id="detalleCitaModal" tabindex="-1" aria-labelledby="detalleCitaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detalleCitaLabel">Detalle de la cita</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p><strong>Cliente:</strong> <span id="modalCliente"></span></p>
                <p><strong>Concepto:</strong> <span id="modalConcepto"></span></p>
                <p><strong>Fecha:</strong> <span id="modalFecha"></span></p>
                <p><strong>Hora:</strong> <span id="modalHora"></span></p>
                <p><strong>Notas:</strong> <span id="modalNotas"></span></p>
                <div id="modalSelectUsuario" class="mb-3 d-none">
                    <label for="selectUsuario" class="form-label">Asignar a usuario:</label>
                    <select id="selectUsuario" class="form-select">
                        <!-- opciones cargadas dinámicamente desde JS -->
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnGuardar" class="btn btn-primary d-none">Guardar</button>
                <button type="button" id="btnEditar" class="btn btn-warning d-none">Editar</button>
                <button type="button" id="btnEliminar" class="btn btn-danger d-none">Eliminar</button>
                <button type="button" id="btnCerrar" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
    </div>

    <!-- Modal para enviar confirmación de cita -->
    <div class="modal fade" id="confirmacionCitaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-confirmacion">
        <div class="modal-header">
            <h5 class="modal-title">Enviar confirmación de cita</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
            <p>Selecciona el canal por el que deseas enviar la confirmación:</p>
            <div class="form-check">
            <input class="form-check-input" type="radio" name="canalConfirmacion" id="canalEmail" value="email" checked>
            <label class="form-check-label" for="canalEmail">Email</label>
            </div>
            <div class="form-check">
            <input class="form-check-input" type="radio" name="canalConfirmacion" id="canalSMS" value="sms">
            <label class="form-check-label" for="canalSMS">SMS</label>
            </div>
            <div class="form-check">
            <input class="form-check-input" type="radio" name="canalConfirmacion" id="canalWhatsApp" value="whatsapp">
            <label class="form-check-label" for="canalWhatsApp">WhatsApp</label>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" id="btnEnviarConfirmacion" class="btn btn-primary">Enviar</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
        </div>
    </div>
    </div>

    <script>
    // Pasamos datos a JS. Usamos flags en json_encode para evitar que caracteres especiales aparezcan
    // tal cual en JSON y se conviertan en códigos hexadecimales seguros
    const citas = <?= json_encode($citas, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
    const usuarioSeleccionado = <?= json_encode($usuarioSeleccionado) ?>;
    const fechaSeleccionada = <?= json_encode($fechaSeleccionada) ?>;;
    const usuarios = <?= json_encode($usuarios) ?>;
    const usuariosConCitas = <?= json_encode($usuariosConCitas) ?>;
    const citasSinUsuario = <?= json_encode($citasSinUsuario) ?>;
    const horaInicioDia = <?= json_encode($horaInicioDia) ?>;
    </script>
    <script src="../js/agenda_citas.js"></script>
    <script src="../js/autoCloseAlerts.js"></script>
</body>
</html>
