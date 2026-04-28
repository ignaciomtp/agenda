 document.addEventListener('DOMContentLoaded', () => {
        // Referencias a radios
        const modoAutomatico = document.getElementById('automatico');
        const modoManual = document.getElementById('manual');
        const tipoEnvioRadios = document.querySelectorAll('input[name="tipo_envio"]');
        const cuandoEnviarRadios = document.querySelectorAll('input[name="cuando_enviar"]');
        
        const horasMismoDia = document.getElementById('horas_mismo_dia');
        const diasAnticipacion = document.getElementById('dias_anticipacion');
        const horasDiasAnticipacion = document.getElementById('horas_dias_anticipacion');

        function actualizarDisponibilidad() {
            const automatico = modoAutomatico.checked;

            // Habilitar/deshabilitar Tipo de envío
            tipoEnvioRadios.forEach(radio => radio.disabled = !automatico);

            // Habilitar/deshabilitar radios Cuándo enviar
            cuandoEnviarRadios.forEach(radio => radio.disabled = !automatico);

            // Inputs de El mismo día
            horasMismoDia.disabled = !automatico || document.getElementById('mismoDia').checked === false;

            // Inputs de Días de antelación
            diasAnticipacion.disabled = !automatico || document.getElementById('diasAnticipacion').checked === false;
            horasDiasAnticipacion.disabled = !automatico || document.getElementById('diasAnticipacion').checked === false;
        }

        // Eventos
        modoAutomatico.addEventListener('change', actualizarDisponibilidad);
        modoManual.addEventListener('change', actualizarDisponibilidad);
        cuandoEnviarRadios.forEach(radio => radio.addEventListener('change', actualizarDisponibilidad));

        // Inicializar estado
        actualizarDisponibilidad();
    });
    // Activar los tooltips
    document.addEventListener('DOMContentLoaded', () => {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(el => new bootstrap.Tooltip(el));
    });