<?php
    require_once __DIR__ . '/../src/auth/checkSession.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel principal</title>  
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/style_base.css">
    <link rel="stylesheet" href="css/panel.css">
</head>
<body>

    <!-- Header -->
    <?php include __DIR__ . '/../partials/header.php'; ?>

    <!-- Contenedor principal -->
    <main class="container-white">
        <h2 class="text-center">¡Hola, <?= htmlspecialchars($_SESSION['Usuario']) ?>! Bienvenida al panel principal.</h2>
        <div class="header-panel">    
            <p class="texto-panel">Aquí puedes visualizar datos importantes sobre las citas.</p>
            <!-- Filtro por año -->
            <div class="filtro-anio">
                <label for="select-anio">Año:</label>
                <select id="select-anio"></select>
            </div>
        </div>

         <!-- Clientes con más citas -->
        <section>
            <h3>Clientes con más citas</h3>
            <div id="estadistica-clientes"></div>
        </section>

        <!-- Días con más citas -->
        <section>
            <h3>Días con más citas</h3>
            <div id="estadistica-dias"></div>
        </section>

        <!-- Servicios más solicitados -->
        <section>
            <h3>Servicios más solicitados</h3>
            <div id="estadistica-servicios"></div>
        </section>

        <!-- Horas con más demanda -->
        <section>
            <h3>Horas con más demanda</h3>
            <div id="estadistica-horas"></div>
        </section>

        <!-- Citas por mes (gráfico) -->
        <section>
            <h3>Citas por mes</h3>
            <canvas id="grafico-citas-mes" width="400" height="200"></canvas>
        </section>
    </main>

    <!-- Footer -->
    <?php include __DIR__ . '/../partials/footer.php'; ?>
    <!-- Librería Chart.js para hacer el gráfico -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/panel_estadisticas.js"></script>

</body>
</html>
