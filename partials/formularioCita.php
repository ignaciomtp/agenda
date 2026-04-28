<div class="form-header">
    <h1><?= $tituloFormulario ?? 'Cita' ?></h1>
    <button type="submit"><?= $botonTexto ?? 'Guardar' ?></button>
</div>

<!-- Mostrar errores -->
<?php if (!empty($errores)): ?>
    <?php foreach ($errores as $error): ?>
        <div class="alert alert-danger" role="alert">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Cliente -->
<div class="form-row">
    <label>Cliente:</label>
    <input type="text" name="cliente" required value="<?= $cliente ?>">
</div>

<!-- Teléfono -->
<div class="form-row">
    <label>Teléfono:</label>
    <input type="tel" name="telefono" required placeholder="9 dígitos" value="<?= $telefono ?>">
</div>

<!-- Email -->
<div class="form-row">
    <label>Email:</label>
    <input type="email" name="email" required placeholder="ejemplo@dominio.com" value="<?= $emailCliente ?>">
</div>

<!-- Servicio -->
<div class="form-row">
    <label>Servicio:</label>
    <select name="servicio" required>
        <option value="" disabled <?= $servicio === '' ? 'selected' : '' ?>>Selecciona un servicio</option>
        <?php foreach ($servicios as $serv): ?>
            <option value="<?= htmlspecialchars($serv) ?>" <?= $serv === $servicio ? 'selected' : '' ?>><?= htmlspecialchars($serv) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Fecha (sólo permite seleccionar fechas a partir de la actual) -->
<div class="form-row">
    <label>Fecha:</label>
    <input type="date" name="fecha" required min="<?= date('Y-m-d') ?>" value="<?= $fecha ?>">
</div>

<!-- Hora inicio -->
<div class="form-row">
    <label>Hora inicio:</label>
    <select name="horaInicioHora" class="select-time" required>
        <option value="" disabled <?= $horaInicioHora === '' ? 'selected' : '' ?>>Hora</option>
        <?php for ($h = 7; $h <= 23; $h++):
            $hora = str_pad($h, 2, '0', STR_PAD_LEFT); ?>
            <option value="<?= $hora ?>" <?= $hora === $horaInicioHora ? 'selected' : '' ?>><?= $hora ?></option>
        <?php endfor; ?>
    </select>
    <select name="horaInicioMinutos" class="select-time" required>
        <option value="" disabled <?= $horaInicioMinutos === '' ? 'selected' : '' ?>>Min</option>
        <?php foreach (['00','15','30','45'] as $m): ?>
            <option value="<?= $m ?>" <?= $m === $horaInicioMinutos ? 'selected' : '' ?>><?= $m ?></option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Usuario (sólo se muestra si hay iniciada sesión, si es el dueño) -->
<?php if ($mostrarUsuario): ?>
<div class="form-row">
    <label>Usuario:</label>
    <select name="usuario" required>
        <option value="" disabled <?= ($usuario === '' || $usuario == 0) ? 'selected' : '' ?>>Selecciona una opción</option>
        <?php foreach ($usuarios as $u): ?>
            <option value="<?= htmlspecialchars($u['Ref_Usuario']) ?>" <?= $u['Ref_Usuario'] == $usuario ? 'selected' : '' ?>>
                <?= htmlspecialchars($u['Usuario']) ?>
            </option>
        <?php endforeach ?>
    </select>
</div>
<?php endif; ?>

<!-- Notas -->
<div class="form-row">
    <label>Notas:</label>
    <textarea name="notas" placeholder="Escribe tus notas aquí..."><?= $notas ?></textarea>
</div>
