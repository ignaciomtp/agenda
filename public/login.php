<?php
session_start();

// Si el usuario ya ha iniciado sesión, redirigir al panel directamente
if (!empty($_SESSION['Usuario'])) {
    header("Location: panel.php");
    exit;
}
// Ruta al config.ini. Lee archivo .ini y lo convierte en array asociativo
$config = parse_ini_file(__DIR__ . '/../src/config/config.ini', true);
$nombreEmpresa = $config['config']['Nombre_Empresa'];
$mostrarMenu = false; // así el header no mostrará los botones
include_once __DIR__ . "/../src/config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario = trim($_POST['usuario']);
    $password = trim($_POST['password']);

    try {
        $conn = conectarPDO(); 
        $stmt = $conn->prepare("SELECT Ref_Usuario, Usuario, Contra, PV_Agenda FROM Usuarios WHERE usuario = ? AND Contra = ?");
        $stmt->execute([$usuario, $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);


        // Comparación directa, 'Contra' está en texto plano en la BD
        if ($user && $password === $user['Contra']) {
            if ($user['PV_Agenda'] == 1) {
                $_SESSION['Ref_Usuario'] = $user['Ref_Usuario'];
                $_SESSION['Usuario'] = $user['Usuario'];
                header("Location: panel.php");
                exit;
            } else {
                $error = "No tienes permisos para acceder a la agenda.";
            }
        } else {
            $error = "Usuario o contraseña incorrectos.";
        }
    } catch (PDOException $e) {
        $error = "Error de conexión: " . $e->getMessage();
    } finally {
        $conn = null;
    }
}

/* ### IMPLEMENTACIÓN DE CÓDIGO SI CONTRASEÑA SE GUARDARA EN BD CON HASH ### 
    (Sustituir el bloque if/else dentro del try)
    if ($user && password_verify($password, $user['Contra'])) {
        if ($user['PV_Agenda'] == 1) {
            // Usuario válido con acceso a la agenda
            $_SESSION['Ref_Usuario'] = $user['Ref_Usuario'];
            $_SESSION['Usuario'] = $user['Usuario'];
            header("Location: panel.php");
            exit;
        } else {
            // Usuario sin permiso PV_Agenda
            $error = "No tienes permisos para acceder a la agenda.";
        }
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }

*/
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= $nombreEmpresa ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/style_base.css">
    <link rel="stylesheet" href="css/formulario_login.css">
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../partials/header.php'; ?>

    <div class="container-white">
        <div class="form-header">
            <h1>Iniciar Sesión</h1>
        </div>

        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-row">
                <label for="usuario">Usuario</label>
                <input type="text" name="usuario" id="usuario" required>
            </div>
            <div class="form-row">
                <label for="password">Contraseña</label>
                <input type="password" name="password" id="password" required>
            </div>
            <div class="form-row">
                <button type="submit">Entrar</button>
            </div>
        </form>
    </div>
    <!-- Footer -->
    <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>


