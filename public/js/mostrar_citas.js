document.addEventListener('DOMContentLoaded', () => {
// Botones de recordatorio
    document.getElementById('btn-email').addEventListener('click', () => enviarRecordatorio('email'));
    document.getElementById('btn-sms').addEventListener('click', () => enviarRecordatorio('sms'));
    document.getElementById('btn-whatsapp').addEventListener('click', () => enviarRecordatorio('whatsapp'));

    // Llenar modal con datos de la cita
    document.querySelectorAll('.btn-ver-cita').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('modal-cliente').textContent = this.dataset.cliente;
            document.getElementById('modal-servicio').textContent = this.dataset.servicio;
            document.getElementById('modal-hora').textContent = this.dataset.hora;
            document.getElementById('modal-hora-fin').textContent = this.dataset.horaFin;
            document.getElementById('modal-usuario').textContent = this.dataset.usuario;
            document.getElementById('modal-telefono').textContent = this.dataset.telefono;
            document.getElementById('modal-notas').textContent = this.dataset.notas;
        });
    });

    // Confirmar eliminación con addEventListener
    document.querySelectorAll('.btn-eliminar').forEach(btn => {
        btn.addEventListener('click', function (event) {
            if (!confirm('¿Seguro que quieres eliminar esta cita?')) {
                event.preventDefault(); // Cancela la navegación a borrarCita.php si el usuario pulsa "Cancelar"
            }
        });
    });

    // Confirmar antes de envío individual de recordatorio
    document.querySelectorAll('.enviar-recordatorio').forEach(btn => {
        btn.addEventListener('click', function() {
            const tipo = this.dataset.tipo;
            const cliente = this.dataset.cliente;
            const idCita = this.dataset.id;
            const fecha = this.dataset.fecha;

            if (confirm(`¿Enviar recordatorio por ${tipo.toUpperCase()} a ${cliente}?`)) {
                // Redirige a enviarRecordatorios.php pasando tipo, idCita y fecha
                window.location.href = `enviarRecordatoriosManual.php?tipo=${tipo}&id=${idCita}&fecha=${fecha}`;
            }
        });
    });

    
})

function enviarRecordatorio(tipo) {
    const fecha = document.getElementById('fecha-dia').value;
    let tipoTexto = tipo.toUpperCase(); // "EMAIL", "SMS" o "WHATSAPP"
    if (confirm(`¿Enviar recordatorio por ${tipoTexto} a todas las citas pendientes de este día?`)) {
        // Para envío masivo (botones grandes parte inferior) sólo redirige con tipo y fecha
        window.location.href = `enviarRecordatoriosManual.php?tipo=${tipo}&fecha=${fecha}`;
    } 
}