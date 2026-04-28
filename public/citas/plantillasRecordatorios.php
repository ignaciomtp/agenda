<?php 
    require_once __DIR__ . '/../../src/auth/checkSession.php';
    require_once __DIR__ . '/../../src/lib/utils.php';
 ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar plantilla</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/style_base.css">
    <link rel="stylesheet" href="../css/plantillas_recordatorios.css">
</head>
<body>
<?php include __DIR__ . '/../../partials/header.php'; ?>

<main class="container mt-4">
    <div class="container-white">
        <h2>Plantillas de recordatorio</h2>

        <!-- Mensajes de éxito o error en $_SESSION['success_msg'] o $_SESSION['error_msg'] -->
        <?php mostrarMensajesSESSION() ?>
                
        <p>Selecciona una plantilla o crea una propia. Estas plantillas se usarán para enviar recordatorios a tus clientes.</p>
        <form method="POST" action="guardarPlantilla.php">
            <div class="mb-3">
                <!-- Plantilla 1 -->
                <div class="form-check plantilla-card">
                    <input class="form-check-input" type="radio" name="plantilla" id="plantilla1" value="plantilla1" required>
                    <label for="plantilla1">
                        ¡Hola! Te esperamos para tu cita el {{fecha}} a las {{hora}}. Si no puedes asistir, agradecemos que nos lo comuniques con 24h de antelación.
                    </label>
                </div>

                <!-- Plantilla 2 -->
                <div class="form-check plantilla-card">
                    <input class="form-check-input" type="radio" name="plantilla" id="plantilla2" value="plantilla2">
                    <label for="plantilla2">
                        Recordatorio: Tienes una cita en nuestro establecimiento el {{fecha}} a las {{hora}}. En caso de no poder asistir, por favor, avísanos con 24h de antelación.
                    </label>
                </div>

                <!-- Plantilla 3 -->
                <div class="form-check plantilla-card">
                    <input class="form-check-input" type="radio" name="plantilla" id="plantilla3" value="plantilla3">
                    <label for="plantilla3">
                        ¡Hola! No olvides tu cita el {{fecha}} a las {{hora}}. Si necesitas cancelar o cambiar, por favor, avísanos con 24h de antelación.
                    </label>
                </div>

                <!-- Plantilla personalizada -->
                <div class="form-check plantilla-card">
                    <input class="form-check-input" type="radio" name="plantilla" id="personalizada" value="personalizada">
                    <label for="personalizada"><strong>Crear plantilla personalizada (sólo disponible para EMAIL y SMS):</strong></label>
                    <textarea name="plantilla_personalizada" class="form-control mt-2" rows="4" placeholder="Ejemplo: ¡Hola! Te recordamos tu cita el {{fecha}} a las {{hora}}."></textarea>
                    <div class="form-text mt-2 text-muted">
                        ⚠️ Es obligatorio incluir <strong>{{fecha}}</strong> y <strong>{{hora}}</strong> en la plantilla. 
                        Asegúrate de escribirlas exactamente entre <code>{{ }}</code> como en los ejemplos. 
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Guardar plantilla</button>
            <a href="<?= $basePath ?>panel.php" class="btn btn-secondary">Volver al panel</a>    
        </form>
    </div>
</main>

<?php include __DIR__ . '/../../partials/footer.php'; ?>

    <script>
        // Resalta la tarjeta seleccionada
        document.querySelectorAll('.form-check-input').forEach(input => {
            input.addEventListener('change', () => {
                document.querySelectorAll('.plantilla-card').forEach(card => card.classList.remove('selected'));
                input.closest('.plantilla-card').classList.add('selected');
            });
        });
    </script>
    <!-- Eliminar alert después del tiempo indicado -->
    <script src="../js/autoCloseAlerts.js"></script> 

</body>
</html>
