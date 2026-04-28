<?php
session_start();

if(empty($_SESSION['Usuario'])){
    $mostrarMenu = false; // así el header no mostrará los botones
}

// Ruta al config.ini
$config = parse_ini_file(__DIR__ . '/../src/config/config.ini', true);
$nombreEmpresa = $config['config']['Nombre_Empresa'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $nombreEmpresa ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/style_base.css">
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../partials/header.php'; ?>

    <div class="container-white">
            <h1>Página principal</h1>
    </div>
    <!-- Footer -->
    <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>


