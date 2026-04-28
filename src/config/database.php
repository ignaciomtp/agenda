<?php 

/**
 * Devuelve la conexión a la base de datos por PDO
 * 
 * @return PDO  Conexión a la BD
 * @throws PDOException si hay un error al conectar
 */
function conectarPDO()
{
    $configFile = __DIR__ . '/config.ini';

    // Verificar si existe el archivo de configuración
    if (!file_exists($configFile)) {
        die("❌ ERROR: No se encuentra el archivo de configuración de la base de datos en: $configFile");
    }

    // Cargar la configuración desde archvo config.ini. Devuelve un array asociativo con secciones ([database] y otras si hubiera):
    $config = parse_ini_file($configFile, true);

    if ($config === false || !isset($config['database'])) {
        die("❌ ERROR: El archivo de configuración está dañado o incompleto. Verifique el bloque [database].");
    }

    // Obtener los datos
    $serverName = $config['database']['server'] ?? '';
    $database   = $config['database']['name'] ?? '';
    $username   = $config['database']['user'] ?? '';
    $password   = $config['database']['password'] ?? '';

    if (!$serverName || !$database || !$username) {
        die("❌ ERROR: Faltan datos obligatorios en config.ini (server, name o user).");
    }

    try {
        $conn = new PDO("sqlsrv:Server=$serverName;Database=$database", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch (PDOException $e) {
        die("❌ ERROR: No se pudo conectar a la base de datos. Revise config.ini.<br>Detalles técnicos: " . htmlspecialchars($e->getMessage()));
    }
}

?>