<?php
    require_once __DIR__ . '/../../src/auth/checkSession.php';
    require_once __DIR__ . '/../../src/config/database.php';
    require_once __DIR__ . '/../../src/lib/utils.php';

    try {
        $conn = conectarPDO();

        $sql = "SELECT 
                    Recordatorios_Auto,
                    Recordatorios_Auto_Tipo,
                    Recordatorio_EnviarModo,
                    Recordatorio_HorasMismoDia,
                    Recordatorio_DiasAnticipacion,
                    Recordatorio_HoraEnvio
                FROM Configuracion2";

        $stmt = $conn->query($sql);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($config) {
            $modoEnvio = (int)$config['Recordatorios_Auto'];
            $tipoEnvio = $config['Recordatorios_Auto_Tipo'] ?? 'email';
            $cuandoEnviar = $config['Recordatorio_EnviarModo'] ?? 'mismoDia';
            $horasMismoDia = $config['Recordatorio_HorasMismoDia'] ?? 1;
            $diasAnticipacion = $config['Recordatorio_DiasAnticipacion'] ?? 1;
            $horaEnvio = $config['Recordatorio_HoraEnvio'] ?? '20:00';
            if (!empty($horaEnvio)) {
                // Convierte la hora a formato HH:MM
                $horaEnvio = date('H:i', strtotime($horaEnvio));
            }
        } else {
            // Valores por defecto si no existe ninguna fila
            $modoEnvio = 1;
            $tipoEnvio = 'email';
            $cuandoEnviar = 'mismoDia';
            $horasMismoDia = 1;
            $diasAnticipacion = 1;
            $horaEnvio = '20:00';
        }

    } catch (PDOException $e) {
        $_SESSION['load_error_msg'] = "Error al cargar configuración: " . $e->getMessage();
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preferencias</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/formulario_login.css">
    <link rel="stylesheet" href="../css/style_base.css">
    <style>
        fieldset .form-check-input {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
        }
    </style>

</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../../partials/header.php'; ?>

    <!-- Contenedor principal -->
       <div class="container-white">
            <div class="mb-4 text-center">
                <h1>Preferencias</h1>
            </div>
            <!-- Mensajes de éxito o error en $_SESSION['success_msg'] o $_SESSION['error_msg'] -->
            <?php mostrarMensajesSESSION() ?>
            <?php
            if(!empty($_SESSION['load_error_msg'])){
                echo '<div class="alert alert-danger">❌ ' . $_SESSION['load_error_msg'] . '</div>';
                unset($_SESSION['load_error_msg']);
            }
            ?>
            <h3>Configuración de recordatorios masivos</h3>
            <form action="guardarPreferencias.php" method="POST" class="form-preferencias">
                <!-- Fieldset Modo de envío -->
                <h5 class="fw-bold">Modo de envío</h5>
                <fieldset class="mb-3 p-3 border rounded">
                    <div class="d-flex align-items-center">
                        <div class="form-check form-check-inline me-3">
                            <input class="form-check-input" type="radio" name="modo_envio" id="automatico" value="1" <?= $modoEnvio ? 'checked' : '' ?>>
                            <label class="form-check-label" for="automatico">Automático</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="modo_envio" id="manual" value="0" <?= !$modoEnvio ? 'checked' : '' ?>>
                            <label class="form-check-label" for="manual">Manual</label>
                        </div>
                    </div>
                </fieldset>
                <!-- Fieldset Tipo de envío -->
                <h5 class="fw-bold">Tipo de envío (sólo para envío Automático)</h5>
                <fieldset class="mb-3 p-3 border rounded">
                    <div class="d-flex align-items-center">
                        <div class="form-check form-check-inline me-3">
                            <input class="form-check-input" type="radio" name="tipo_envio" id="email" value="email" <?= $tipoEnvio === 'email' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="email">Email</label>
                        </div>
                        <div class="form-check form-check-inline me-3">
                            <input class="form-check-input" type="radio" name="tipo_envio" id="sms" value="sms" <?= $tipoEnvio === 'sms' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="sms">SMS</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo_envio" id="whatsapp" value="whatsapp"
                                <?= $tipoEnvio === 'whatsapp' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="whatsapp">Whatsapp</label>
                        </div>
                    </div>
                </fieldset>
                <!-- Fieldset Cuándo enviar -->
                <h5 class="fw-bold">Cuándo enviar (sólo para envío Automático)
                    <i class="bi bi-info-circle ms-1 text-primary"
                        data-bs-toggle="tooltip"
                        data-bs-placement="right"
                        data-bs-html="true"
                        title="
                            <strong>MISMO DÍA:</strong> el recordatorio se envía unas horas antes de la cita.<br>
                            <strong>DÍAS DE ANTELACIÓN:</strong> el recordatorio se envía varios días antes, según el número indicado.">
                    </i>
                </h5>
                <div class="d-flex gap-3 flex-wrap">
                    <!-- Fieldset El mismo día -->
                    <fieldset class="mb-3 p-3 border rounded flex-grow-1">
                        <div class="d-flex align-items-center gap-2">
                            <input class="form-check-input" type="radio" name="cuando_enviar" id="mismoDia" value="mismoDia" <?= $cuandoEnviar === 'mismoDia' ? 'checked' : '' ?> style="margin:0;">
                            <label class="form-check-label" for="mismoDia">El mismo día</label>
                            <label for="hora" class="ms-3">Horas de antelación:</label>
                            <input type="number" id="horas_mismo_dia" name="horas_mismo_dia" value="<?= htmlspecialchars($horasMismoDia) ?>" min="1" max="23" class="form-control form-control-sm" style="width:70px; margin:0;">
                        </div>
                    </fieldset>
                    <!-- Fieldset Días de antelación -->
                    <fieldset class="mb-3 p-3 border rounded flex-grow-1">
                        <div class="d-flex align-items-center gap-2">
                            <input class="form-check-input" type="radio" name="cuando_enviar" id="diasAnticipacion" value="diasAnticipacion" <?= $cuandoEnviar === 'diasAnticipacion' ? 'checked' : '' ?> style="margin:0;">
                            <label class="form-check-label" for="diasAnticipacion">Días de antelación</label>
                            <input type="number" id="dias_anticipacion" name="dias_anticipacion" class="form-control form-control-sm" value="<?= htmlspecialchars($diasAnticipacion) ?>" min="1" max="30" style="width:70px; margin:0;">
                            <label for="hora">Hora de envío:</label>
                            <input type="time" id="horas_dias_anticipacion" name="horas_dias_anticipacion" value="<?= htmlspecialchars($horaEnvio) ?>" class="form-control form-control-sm" style="width:120px; margin:0;">
                        </div>
                    </fieldset>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">Guardar preferencias</button>
                    <a href="<?= $basePath ?>panel.php" class="btn btn-secondary">Volver al panel</a>
                </div>
            </form>
        </div>

    <!-- Footer -->
    <?php include __DIR__ . '/../../partials/footer.php'; ?>
    <!-- Eliminar alert después del tiempo indicado -->
    <script src="../js/autoCloseAlerts.js"></script> 
    <script src="../js/preferencias.js"></script>
</body>
</html>