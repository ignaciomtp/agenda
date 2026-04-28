<?php
// 1. Versión de PHP
echo "<h3>PHP Version: " . phpversion() . "</h3>";

// 2. Extensiones cargadas relacionadas con SQL Server
echo "<h3>Extensiones SQL Server cargadas:</h3>";
$extensions = ['sqlsrv', 'pdo_sqlsrv', 'pdo'];
foreach ($extensions as $ext) {
    echo "$ext: " . (extension_loaded($ext) ? '✅ Cargada' : '❌ NO cargada') . "<br>";
}

// 3. Ruta al php.ini que está usando XAMPP
echo "<h3>php.ini en uso:</h3>";
echo php_ini_loaded_file() . "<br>";

// 4. Drivers PDO disponibles
echo "<h3>Drivers PDO disponibles:</h3>";
print_r(PDO::getAvailableDrivers());

echo phpinfo(INFO_GENERAL);
?>