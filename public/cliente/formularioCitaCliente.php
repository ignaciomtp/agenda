<?php
session_start();
// Incluye paths.php para tener $basePath correctamente configurado
require_once __DIR__ . '/../../src/config/paths.php'; 
include_once __DIR__ . "/../../src/lib/utils.php";
include_once __DIR__ . "/../../src/config/database.php";

$mostrarMenu = false; // así el header no mostrará los botones

// Recuperar errores guardados en sesión si somos redirigidos
$errores = $_SESSION['errores'] ?? [];
$formulario = $_SESSION['formulario'] ?? [];

// Limpiar sesión después de recuperar los datos
unset($_SESSION['errores'], $_SESSION['formulario']);

// Filtrar campos usando función (en lib/utils.php)
$campos = filtrarCamposCita($formulario);
extract($campos); // crea variables $cliente, $telefono, $emailCliente, etc.

try {
    $conn = conectarPDO();
    // Consulta los servicios del establecimiento
    $stmt = $conn->query("SELECT nombre FROM Articulos WHERE web = 1 AND borrado = 0 ORDER BY nombre");
    $servicios = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Recoger referencia del usuario y nombre
    $stmt = $conn->query("SELECT Ref_Usuario, Usuario FROM Usuarios WHERE PV_Agenda = 1 AND Fecha_Baja IS NULL ORDER BY Nombre_Usuario");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de conexión o consulta: " . $e->getMessage());
} finally{
    $conn = null;
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
    <link rel="stylesheet" href="../css/style_base.css"> <!-- Incluir después de Bootstrap para que no sobrescriba fondo gris claro -->
    <link rel="stylesheet" href="../css/formulario_citas.css"> 
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../../partials/header.php'; ?>
    
    <div class="container-white">
        <form id="form-cita" action="procesarFormularioCitaCliente.php" method="POST">
            <?php 
            // Incluir formulario para cliente
            $tituloFormulario = 'Nueva cita';
            $botonTexto = 'Solicitar';
            $mostrarUsuario = false; // no mostrar select de usuario
            include __DIR__ . '/../../partials/formularioCita.php' ?>
        </form>
    </div>
    <!-- Footer -->
    <?php include __DIR__ . '/../../partials/footer.php'; ?>
</body>
</html>
