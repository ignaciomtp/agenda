<?php
    require_once __DIR__ . '/../../src/auth/checkSession.php';
    require_once __DIR__ . '/../../src/config/paths.php';
    include_once __DIR__ . "/../../src/config/database.php";
    include_once __DIR__ . "/../../src/lib/utils.php";

    // Recuperar fecha de la URL
    $fecha = $_GET['fecha'] ?? null;
    if (!$fecha) {
        $_SESSION['error_msg'] = "📅 Fecha no especificada";
        header("Location: calendarioCitas.php");
    exit;
    }
    // Obtener citas
    $citas = [];
    try {
        $conn = conectarPDO();
        $stmt = $conn->prepare("SELECT Ref_Agenda, Cliente, Concepto, Hora, Hora_Fin, Usuario, Notas, Recordatorio_Email, Recordatorio_Sms, Recordatorio_WA 
                                FROM Agenda 
                                WHERE CONVERT(varchar(10), Fecha, 120) = :fecha
                                ORDER BY Hora");
        $stmt->execute([':fecha' => $fecha]);
        $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error al obtener citas: " . $e->getMessage());
    }

    // Consultar saldo SMS
    $saldoSMS = null;
    if (!empty($citas)) { // Solo si hay citas
        try {
            $stmt = $conn->prepare("SELECT SMS_Envio_Usuario, SMS_Envio_Contra FROM Configuracion2");
            $stmt->execute();
            $credenciales = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($credenciales['SMS_Envio_Usuario'] && $credenciales['SMS_Envio_Contra']) {
                require_once __DIR__ . '/../../src/lib/altiriaSMS.php';
                $saldoSMS = consultarSaldoAltiria($credenciales['SMS_Envio_Usuario'], $credenciales['SMS_Envio_Contra']);
            }
        } catch (Exception $e) {
            $saldoSMS = "Error consultando saldo";
        }
    }

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citas del <?= htmlspecialchars(date('d-m-Y', strtotime($fecha))) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/style_base.css">
    <link rel="stylesheet" href="../css/mostrar_citas.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="d-flex flex-column min-vh-100">
    <?php include __DIR__ . '/../../partials/header.php'; ?>

    <div class="container-white flex-grow-1">
        <h2 class="mb-4 text-center">Citas del <?= htmlspecialchars(date('d-m-Y', strtotime($fecha))) ?></h2>

    <!-- Mensajes de éxito o error en $_SESSION['success_msg'] o $_SESSION['error_msg'] -->
    <?php mostrarMensajesSESSION() ?>

        <?php if (empty($citas)): ?>
            <div class="alert alert-info">No hay citas para este día.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Cliente</th>
                            <th>Servicio</th>
                            <th>Usuario</th>
                            <th>Teléfono</th>
                            <th class="table-acciones">Acciones</th>
                            <th>Recordatorio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($citas as $cita): 

                            // Extraer teléfono de Notas
                            preg_match('/Telefono:\s*([\d\+\-\s]+)/', $cita['Notas'], $matches);
                            $telefono = $matches[1] ?? 'No disponible';

                            $nombreUsuario = obtenerNombreUsuario($conn, $cita['Usuario']);
                            $fechaFormateada = date('Y-m-d', strtotime($fecha));
                            $redirectURL = urlencode('mostrarCitas.php?fecha=' . $fechaFormateada);
                        ?>

                        <tr>
                            <td><?= date('H:i', strtotime($cita['Hora'])) ?> - <?= date('H:i', strtotime($cita['Hora_Fin'])) ?></td>
                            <td><?= htmlspecialchars($cita['Cliente']) ?></td>
                            <td><?= htmlspecialchars($cita['Concepto']) ?></td>
                            <td><?= htmlspecialchars($nombreUsuario) ?></td>
                            <td><?= htmlspecialchars($telefono) ?></td>
                            <td>
                                <!-- Botón Ver abre modal -->
                                <button class="btn btn-sm btn-info me-1 btn-ver-cita"
                                    data-bs-toggle="modal"
                                    data-bs-target="#detalleCitaModal"
                                    data-cliente="<?= htmlspecialchars($cita['Cliente']) ?>"
                                    data-servicio="<?= htmlspecialchars($cita['Concepto']) ?>"
                                    data-hora="<?= date('H:i', strtotime($cita['Hora'])) ?>"
                                    data-hora-fin="<?= date('H:i', strtotime($cita['Hora_Fin'])) ?>"
                                    data-usuario="<?= htmlspecialchars($nombreUsuario) ?>"
                                    data-telefono="<?= htmlspecialchars($telefono) ?>"
                                    data-notas="<?= htmlspecialchars($cita['Notas']) ?>"
                                    title="Ver">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                                <a href="editarCita.php?id=<?= $cita['Ref_Agenda'] ?>&redirect=<?= $redirectURL ?>" class="btn btn-sm btn-warning me-1" title="Editar">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                                <a href="eliminarCita.php?id=<?= $cita['Ref_Agenda'] ?>&redirect=<?= $redirectURL ?>" class="btn btn-sm btn-danger btn-eliminar" title="Eliminar">
                                    <i class="bi bi-trash-fill"></i>
                                </a>
                            </td>
                            <td class="text-center">
                                <!-- Email -->
                                <button class="btn btn-sm btn-link enviar-recordatorio"
                                    data-tipo="email"
                                    data-cliente="<?= htmlspecialchars($cita['Cliente']) ?>"
                                    data-id="<?= $cita['Ref_Agenda'] ?>"
                                    data-fecha="<?= $fecha ?>"
                                    title="<?= $cita['Recordatorio_Email'] ? 'Email enviado' : 'Email no enviado' ?>">
                                    <?php if($cita['Recordatorio_Email'] == 1): ?>
                                        <i class="bi bi-check-square-fill text-primary" style="font-size:1.2rem;"></i>
                                    <?php else: ?>
                                        <i class="bi bi-x-square-fill text-secondary" style="font-size:1.2rem;"></i>
                                    <?php endif; ?>
                                </button>

                                <!-- SMS -->
                                <button class="btn btn-sm btn-link enviar-recordatorio"
                                    data-tipo="sms"
                                    data-cliente="<?= htmlspecialchars($cita['Cliente']) ?>"
                                    data-id="<?= $cita['Ref_Agenda'] ?>"
                                    data-fecha="<?= $fecha ?>"
                                    title="<?= $cita['Recordatorio_Sms'] ? 'SMS enviado' : 'SMS no enviado' ?>">
                                    <?php if($cita['Recordatorio_Sms'] == 1): ?>
                                        <i class="bi bi-check-square-fill text-danger" style="font-size:1.2rem;"></i>
                                    <?php else: ?>
                                        <i class="bi bi-x-square-fill text-secondary" style="font-size:1.2rem;"></i>
                                    <?php endif; ?>
                                </button>

                                <!-- WhatsApp -->
                                <button class="btn btn-sm btn-link enviar-recordatorio"
                                    data-tipo="whatsapp"
                                    data-cliente="<?= htmlspecialchars($cita['Cliente']) ?>"
                                    data-id="<?= $cita['Ref_Agenda'] ?>"
                                    data-fecha="<?= $fecha ?>"
                                    title="<?= $cita['Recordatorio_WA'] ? 'WhatsApp enviado' : 'WhatsApp no enviado' ?>">
                                    <?php if($cita['Recordatorio_WA'] == 1): ?>
                                        <i class="bi bi-check-square-fill text-success" style="font-size:1.2rem;"></i>
                                    <?php else: ?>
                                        <i class="bi bi-x-square-fill text-secondary" style="font-size:1.2rem;"></i>
                                    <?php endif; ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Botones de recordatorio -->
            <div class="botones-recordatorio text-center mt-4">
                <input type="hidden" id="fecha-dia" value="<?= $fecha ?>">
                <button id="btn-email" class="btn btn-primary me-2">Enviar por Email</button>
                <button id="btn-sms" class="btn btn-danger me-2">
                    Enviar por SMS 
                    <?php if ($saldoSMS !== null): ?>
                        (<?= is_numeric($saldoSMS) ? $saldoSMS : htmlspecialchars($saldoSMS) ?> disponibles)
                    <?php endif; ?>
                </button>
                <button id="btn-whatsapp" class="btn btn-success">Enviar por WhatsApp</button>
            </div>
        <?php endif; ?>
        
        <div class="collapse-button">
            <a class="btn btn-secondary" data-bs-toggle="collapse" href="#infoRecordatorio" role="button" aria-expanded="false" aria-controls="infoRecordatorio">
                ℹ️ Cómo enviar recordatorios
            </a>
                    </div>
        <div class="collapse" id="infoRecordatorio">
            <div class="card card-body mt-2">
                <ul class="mb-0">
                    <li>Envío masivo de recordatorios (a todas las citas que no haya sido ya enviado), pulsar botones de la parte inferior.</li>
                    <li>Envío individual a una cita concreta, pulsar el botón correspondiente en la columna Recordatorio (Columnas: <b>1</b>-Email | <b>2</b>-SMS | <b>3</b>-WhatsApp).</li>
                </ul>
            </div>
        </div>


        <div id="form-cita" class="mt-3">
            <a href="calendarioCitas.php" class="btn btn-secondary">Volver al calendario</a>
        </div>
    </div>

    <!-- Modal para ver cita -->
    <div class="modal fade" id="detalleCitaModal" tabindex="-1" aria-labelledby="modalCitaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="modalCitaLabel">Detalles de la cita</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
            <p><strong>Cliente:</strong> <span id="modal-cliente"></span></p>
            <p><strong>Servicio:</strong> <span id="modal-servicio"></span></p>
            <p><strong>Hora:</strong> <span id="modal-hora"></span> - <span id="modal-hora-fin"></span></p>
            <p><strong>Usuario:</strong> <span id="modal-usuario"></span></p>
            <p><strong>Teléfono:</strong> <span id="modal-telefono"></span></p>
            <p><strong>Notas:</strong> <span id="modal-notas"></span></p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
        </div>
    </div>
    </div>


    <?php include __DIR__ . '/../../partials/footer.php'; ?>

    <!-- Eliminar alert después del tiempo indicado -->
    <script src="../js/autoCloseAlerts.js"></script> 
    <script src="../js/mostrar_citas.js"></script>

    
</body>
</html>
