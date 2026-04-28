<?php
require_once __DIR__ . '/../../src/auth/checkSession.php';
require_once __DIR__ . '/../../src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plantillaSeleccionada = $_POST['plantilla'] ?? '';
    $plantillaPersonalizada = trim($_POST['plantilla_personalizada'] ?? '');

    $plantillas = [
        'plantilla1' => "¡Hola! Te esperamos para tu cita el {{fecha}} a las {{hora}}. Si no puedes asistir, agradecemos que nos lo comuniques con 24h de antelación.",
        'plantilla2' => "Recordatorio: Tienes una cita en nuestro establecimiento el {{fecha}} a las {{hora}}. En caso de no poder asistir, por favor, avísanos con 24h de antelación.",
        'plantilla3' => "¡Hola! No olvides tu cita el {{fecha}} a las {{hora}}. Si necesitas cancelar o cambiar, por favor, avísanos con 24h de antelación."
    ];

    $variablesValidas = ['{{fecha}}', '{{hora}}', '{{concepto}}', '{{usuario}}'];

    // Determinar plantilla final
    if ($plantillaSeleccionada === 'personalizada') {
        if (empty($plantillaPersonalizada)) {
            $_SESSION['error_msg'] = "⚠️ La plantilla personalizada no puede estar vacía.";
            header("Location: plantillasRecordatorios.php?success=false");
            exit;
        }

        // Validación: máximo 159 caracteres. mb_strlen cuenta caracteres Unicode
        if (mb_strlen($plantillaPersonalizada, 'UTF-8') > 159) {
            $_SESSION['error_msg'] = "⚠️ La plantilla personalizada no puede superar 159 caracteres.";
            header("Location: plantillasRecordatorios.php?success=false");
            exit;
        }

        preg_match_all('/{{(.*?)}}/', $plantillaPersonalizada, $matches);
        $variablesEncontradas = array_map(fn($v) => '{{' . trim($v) . '}}', $matches[1]);
        $variablesInvalidas = array_diff($variablesEncontradas, $variablesValidas);

        if (!empty($variablesInvalidas)) {
            $_SESSION['error_msg'] = "❌ Has usado variables no válidas: " . implode(', ', $variablesInvalidas);
            header("Location: plantillasRecordatorios.php?success=false");
            exit;
        }

        if (!in_array('{{fecha}}', $variablesEncontradas) || !in_array('{{hora}}', $variablesEncontradas)) {
            $_SESSION['error_msg'] = "⚠️ Las variables {{fecha}} y {{hora}} son obligatorias en la plantilla.";
            header("Location: plantillasRecordatorios.php?success=false");
            exit;
        }

        $plantillaFinal = $plantillaPersonalizada;
    } else {
        $plantillaFinal = $plantillas[$plantillaSeleccionada] ?? $plantillas['plantilla1'];
    }

    // Guardar en BD
    try {
        $conn = conectarPDO();
        $stmt = $conn->prepare("UPDATE Configuracion2 SET Plantilla_Recordatorio = :plantilla");
        $stmt->execute([':plantilla' => $plantillaFinal]);

        // Guardar plantilla WhatsApp. Si se escoge personalizada se establece plantilla1 por defecto (no admite personalizada)
        $plantillaWA = $plantillaSeleccionada === "personalizada" ? "plantilla1" : $plantillaSeleccionada;
        $stmt = $conn->prepare("UPDATE Configuracion2 SET Plantilla_RecordatorioWA = :plantillaWA");
        $stmt->execute([':plantillaWA' => $plantillaWA]);

        $_SESSION['success_msg'] = "✅ Plantilla guardada correctamente.";
        header("Location: plantillasRecordatorios.php?success=true");
        exit;
    } catch (Exception $e) {
        $_SESSION['error_msg'] = "❌ Error guardando la plantilla: " . $e->getMessage();
        header("Location: plantillasRecordatorios.php?success=false");
        exit;
    }
}
// Si no es POST redirige
header("Location: plantillasRecordatorios.php?success=false");
exit;
