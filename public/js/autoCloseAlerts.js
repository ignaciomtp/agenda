// Cierra automáticamente los alerts de sesión (éxito o error) después de 4 segundos
document.addEventListener("DOMContentLoaded", () => {
    const alerts = document.querySelectorAll('.alert-success, .alert-danger');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 4000); // 4 segundos
    });
});
