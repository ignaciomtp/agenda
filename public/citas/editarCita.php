<?php
require_once __DIR__ . '/../../src/auth/checkSession.php';
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/paths.php';
include_once __DIR__ . "/../../src/lib/utils.php";

// Recuperar ID de la cita a editar
$idCita = $_GET['id'] ?? null;
$fechaActual = date('Y-m-d');

// Obtener redirect desde GET si existe
if (!empty($_GET['redirect'])) {
    // Decodificamos la URL pasada como parámetro
    $redirect = urldecode($_GET['redirect']);
} else {
    // Redirect por defecto a mostrarCitas.php con fecha actual
    $redirect = "mostrarCitas.php?fecha=$fechaActual";
}

if (!$idCita) {
    $_SESSION['error_msg'] = "❌ No se ha especificado ninguna cita.";
    header("Location:" . $redirect);
    exit;
}

try {
    $conn = conectarPDO();

    // Recuperar datos de la cita
    $stmt = $conn->prepare("SELECT * FROM Agenda WHERE Ref_Agenda = :id");
    $stmt->execute([':id' => $idCita]);
    $cita = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cita) {
        $_SESSION['error_msg'] = "⚠️ La cita no existe o ya fue eliminada.";
        header("Location: $redirect");
        exit;
    }

    // Recuperar servicios disponibles
    $stmt = $conn->query("SELECT Nombre FROM Articulos WHERE web = 1 AND borrado = 0 ORDER BY Nombre");
    $servicios = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Recuperar usuarios
    $stmt = $conn->query("SELECT Ref_Usuario, Usuario FROM Usuarios WHERE PV_Agenda = 1 AND Fecha_Baja IS NULL ORDER BY Usuario");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Extraer teléfono y email de Notas si existen
    preg_match('/Telefono:\s*([\d\+\-\s]+)/', $cita['Notas'], $matchesTel);
    $telefono = $matchesTel[1] ?? '';

    preg_match('/Email:\s*([^\s]+)/', $cita['Notas'], $matchesEmail);
    $emailCliente = $matchesEmail[1] ?? '';

    // Formatear datos para el formulario
    $fecha = (new DateTime($cita['Fecha']))->format('Y-m-d');
    $hora = new DateTime($cita['Hora']);
    $cliente = $cita['Cliente'] ?? '';
    $servicio = $cita['Concepto'] ?? '';
    $horaInicioHora = $hora->format('H');
    $horaInicioMinutos = $hora->format('i');
    $usuario = $cita['Usuario'] ?? '';
    $notas = $cita['Notas'] ?? '';

} catch (PDOException $e) {
    $_SESSION['error_msg'] = "❌ Error de conexión o consulta: " . $e->getMessage();
    header("Location: $redirect");
    exit;
} finally {
    $conn = null;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar cita</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/style_base.css">
    <link rel="stylesheet" href="../css/formulario_citas.css">
</head>
<body>
    <?php include __DIR__ . '/../../partials/header.php'; ?>

    <div class="container-white">
        <form id="form-cita" action="procesarEdicionCita.php" method="POST">
            <?php 
                $tituloFormulario = 'Editar cita';
                $botonTexto = 'Guardar cambios';
                $mostrarUsuario = true; // mostrar select de usuario en formulario
            ?>

            <!-- Mostrar alert de error si existe -->
            <?php if (!empty($_SESSION['errores'])): ?>
                <?php foreach ($_SESSION['errores'] as $error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>
                <?php unset($_SESSION['errores']); ?>
            <?php endif; ?>

            <!-- Campo oculto para enviar ID de la cita -->
            <input type="hidden" name="id" value="<?= htmlspecialchars($idCita) ?>">
            <input type="hidden" name="redirect" value="<?= $redirect ?>">

            <!-- Variables para el formulario parcial -->
            <?php
                if (!empty($_SESSION['formulario'])) {
                    // Si hubo errores previos, rellenar con los datos enviados
                    extract($_SESSION['formulario']);
                    unset($_SESSION['formulario']);
                }
            ?>

            <!-- Incluir formulario parcial -->
            <?php include __DIR__ . '/../../partials/formularioCita.php'; ?>
        </form>
        <div id="form-cita" class="mt-3">
            <a href="<?= $redirect ?>" class="btn btn-secondary">Volver a las citas</a>
        </div>
    </div>

    <?php include __DIR__ . '/../../partials/footer.php'; ?>
</body>
</html>
