<?php
require_once __DIR__ . '/../src/config/paths.php';

// Header común para toda la app. Incluir lógica, si es necesario, para mostrar botones según rol
// Definir variable opcional $mostrarMenu desde la página que incluye el header
$mostrarMenu = $mostrarMenu ?? (!empty($_SESSION['Usuario']));
// Ruta al config.ini
$config = parse_ini_file(__DIR__ . '/../src/config/config.ini', true);
$nombreEmpresa = $config['config']['Nombre_Empresa'];

// $basePath es la ruta base del proyecto desde la raíz del servidor
// Se genera en /src/config/paths.php
?>
<header class="top-bar">
    <div class="logo">
        <a href="<?= $basePath . 'index.php' ?>" class="logo-link">
            <img src="<?= $basePath ?>img/eclipse.ico" alt="Eclipse" class="logo-img" width="40" height="40">
            <span class="logo-text"><?= $nombreEmpresa ?></span>
        </a>
    </div>
    <?php if ($mostrarMenu): // Sólo será false en páginas como index.php, porque no queremos mostrar botones de usuario (no logueado)?> 
        <nav class="menu" id="menu">
            <button id="btn-panel">Panel</button>
            <button id="btn-citas">Gestionar citas</button>

            <!-- Dropdown Agenda -->
            <div class="dropdown">
                <button class="dropdown-toggle" id="btn-agenda" data-bs-toggle="dropdown" aria-expanded="false">Agenda</button>
                <ul class="dropdown-menu" aria-labelledby="btn-agenda">
                    <li><a class="dropdown-item" href="<?= $basePath ?>citas/agendaCitas.php?usuario=individual">Usuario individual</a></li>
                    <li><a class="dropdown-item" href="<?= $basePath ?>citas/agendaCitas.php?usuario=todos">Todos los usuarios</a></li>
                </ul>
            </div>
            <!-- Dropdown Configuración -->
            <div class="dropdown">
                <button class="dropdown-toggle" id="btn-configuracion" data-bs-toggle="dropdown" aria-expanded="false">Configuración</button>
                <ul class="dropdown-menu" aria-labelledby="btn-configuracion">
                    <li><a class="dropdown-item" href="<?= $basePath ?>citas/plantillasRecordatorios.php">Plantillas</a></li>
                    <li><a class="dropdown-item" href="<?= $basePath ?>configuracionCitas/preferencias.php">Preferencias</a></li>
                </ul>
            </div>

            <button id="btn-cerrar">Cerrar sesión</button>
        </nav>
    <?php else: ?>
        <nav class="menu" id="menu">
            <button id="btn-login">Iniciar sesión</button>
        </nav>
    <?php endif;?>
    <!-- Botón hamburguesa -->
    <button class="menu-toggle" id="menu-toggle" aria-label="Mostrar menú">&#9776;</button>
</header>

<!-- Activa el dropdown porque contiene Popper.js, que Bootstrap necesita para los menús desplegables -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Función helper para comprobar si existe elemento y no dé error   
    function addListener(id, event, callback) {
        const el = document.getElementById(id);
        if (el) el.addEventListener(event, callback);
    }

    addListener('btn-panel', 'click', () => {
        location.href = '<?= $basePath ?>panel.php';
    });

    addListener('btn-citas', 'click', () => {
        location.href = '<?= $basePath ?>citas/calendarioCitas.php';
    });

    addListener('btn-cerrar', 'click', () => {
        location.href = '<?= $basePath ?>logout.php';
    });

    addListener('menu-toggle', 'click', () => {
        document.getElementById('menu').classList.toggle('active');
    });

    addListener('btn-login', 'click', () => {
        location.href = '<?= $basePath ?>login.php';
    });

</script>

