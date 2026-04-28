<?php
    require_once __DIR__ . '/../../src/auth/checkSession.php';
    require_once __DIR__ . '/../../src/config/database.php';
    //require_once __DIR__ . '/../../src/lib/utils.php';

    $idCita = $_POST['idCita'] ?? null;
    $usuario = $_POST['usuario'] ?? null;
    $horaInicioNueva = $_POST['horaInicio'];
    $horaFinNueva = $_POST['horaFin'];
    $ignorarConflicto = isset($_POST['ignorarConflicto']) ? (bool)$_POST['ignorarConflicto'] : false;

    if ($idCita && $usuario) {
        try {
            $conn = conectarPDO();

            // Comprobar conflictos si no se fuerza con ignorarConflicto
            if (!$ignorarConflicto) {
                $stmt = $conn->prepare("
                    SELECT Ref_Agenda, Fecha, Hora, Hora_Fin, Cliente, Concepto, Usuario FROM Agenda
                    WHERE Usuario = ?
                    AND Ref_Agenda != ?
                    AND Hora < ?          -- La cita existente empieza **antes** de que termine la nueva
                    AND Hora_Fin > ?      -- La cita existente termina **después** de que empiece la nueva
                ");
                $stmt->execute([$usuario, $idCita, $horaFinNueva, $horaInicioNueva]);
                $conflictos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach($conflictos as &$conflicto){
                    $conflicto['Fecha_formateada'] = date('d/m/Y', strtotime($conflicto['Fecha']));
                    $conflicto['Hora_formateada'] = date('H:i', strtotime($conflicto['Hora']));
                    $conflicto['Hora_Fin_formateada'] = date('H:i', strtotime($conflicto['Hora_Fin']));
                }

                if (!empty($conflictos)) {
                    echo json_encode(['status' => 'conflicto', 'mensaje' => $conflictos]);
                    exit;
                }
            }

            $stmt = $conn->prepare('UPDATE Agenda SET Usuario = ? WHERE Ref_Agenda = ?');
            $stmt->execute([$usuario, $idCita]);

            echo json_encode(['status' => 'ok', 'mensaje' => 'Usuario asignado correctamente.']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'mensaje' => 'Error con la base de datos asignando usuario: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'mensaje' => 'Faltan datos para asignar usuario a la cita.']);
    }