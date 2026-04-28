<?php
require_once __DIR__ . '/../../src/auth/checkSession.php';
require_once __DIR__ . '/../../src/config/paths.php'; // para $basePath
include_once __DIR__ . "/../../src/config/database.php";
include_once __DIR__ . "/../../src/lib/utils.php";

$diasConCitas = [];

$mesActual = (int)date('m') - 1;
$anioActual = (int)date('Y');

// Conectar y obtener citas 
try {
    $conn = conectarPDO();
    $stmt = $conn->query("SELECT DISTINCT CONVERT(varchar(10), Fecha, 120) AS dia FROM Agenda");
    $diasConCitas = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $error = "Error al obtener días con citas: " . $e->getMessage();
} 

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario de citas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/style_base.css">
    <link rel="stylesheet" href="../css/calendario.css">
</head>

<body>

    <?php include __DIR__ . '/../../partials/header.php'; ?>
    
    <h2 class="text-center mb-4">Calendario de citas</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    
    <div class="row justify-content-center mb-3">
        <div class="col-auto d-flex align-items-center me-3">
            <label for="mes" class="me-2 mb-0">Mes:</label>
            <select id="mes" class="form-select">
            <?php
            $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']; 
            foreach ($meses as $i => $nombre) {
                $sel = $i === $mesActual ? 'selected' : '';
                echo "<option value='$i' $sel>$nombre</option>";
            }
            ?>
            </select>
        </div>
        <div class="col-auto d-flex align-items-center">
            <label for="anio" class="me-2 mb-0">Año:</label>
            <select id="anio" class="form-select">
                <?php
                for ($a = $anioActual; $a <= $anioActual + 3; $a++) {
                    echo "<option value='$a'>$a</option>";
                }
                ?>
            </select>
        </div>
    </div>
    
    <div class="container-white">
        <!-- Mensajes de éxito o error en $_SESSION['success_msg'] o $_SESSION['error_msg'] -->
        <?php mostrarMensajesSESSION() ?>
        
        <div id="calendar-container" class="calendar-wrapper"></div>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../../partials/footer.php'; ?>
    
    <script>
        const diasConCitas = <?= json_encode($diasConCitas) ?>;
        const basePath = <?= json_encode($basePath) ?>;
        const mesActual = <?= json_encode($mesActual) ?>;
        const anioActual = <?= json_encode($anioActual) ?>;
    </script>
    <script src="../js/autoCloseAlerts.js"></script>            
    <script src="../js/calendario_citas.js"></script>

</body>
</html>