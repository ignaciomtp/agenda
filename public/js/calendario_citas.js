let fechaSeleccionada = null;

function generarCalendario(mes, anio) {
    const container = document.getElementById('calendar-container');
    container.innerHTML = ''; // limpiar

    const primerDia = new Date(anio, mes, 1);
    const ultimoDia = new Date(anio, mes + 1, 0);

    const tabla = document.createElement('table');
    tabla.classList.add('calendar');

    const filaCabezera = document.createElement('tr');
    const diasSemana = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
    diasSemana.forEach(d => {
        const th = document.createElement('th');
        th.textContent = d;
        filaCabezera.appendChild(th);
    });
    tabla.appendChild(filaCabezera);

    let fila = document.createElement('tr');
    let diaSemana = (primerDia.getDay() + 6) % 7; // lunes=0
    for (let i = 0; i < diaSemana; i++) {
        const td = document.createElement('td');
        fila.appendChild(td);
    }

    for (let d = 1; d <= ultimoDia.getDate(); d++) {
        const fecha = new Date(anio, mes, d);
        const fechaStr = fecha.getFullYear() + '-' +
                        String(fecha.getMonth() + 1).padStart(2,'0') + '-' +
                        String(fecha.getDate()).padStart(2,'0');

        const td = document.createElement('td');
        td.textContent = d;
        if (diasConCitas.includes(fechaStr)) td.classList.add('has-appointment');

        td.addEventListener('click', () => {
            document.querySelectorAll('td.selected').forEach(e => e.classList.remove('selected'));
            td.classList.add('selected');
            // redirigir a mostrarCitas.php con ruta absoluta
            window.location.href = `${basePath}citas/mostrarCitas.php?fecha=${fechaStr}`;
        });

        fila.appendChild(td);

        if ((d + diaSemana) % 7 === 0) {
            tabla.appendChild(fila);
            fila = document.createElement('tr');
        }
    }
    tabla.appendChild(fila);
    container.appendChild(tabla);
}


// Regenerar calendario al cambiar mes o año
document.getElementById('mes').addEventListener('change', () => {
    const mes = parseInt(document.getElementById('mes').value);
    const anio = parseInt(document.getElementById('anio').value);
    generarCalendario(mes, anio);
});
document.getElementById('anio').addEventListener('change', () => {
    const mes = parseInt(document.getElementById('mes').value);
    const anio = parseInt(document.getElementById('anio').value);
    generarCalendario(mes, anio);
});

// Inicializar calendario con mes y año actuales
generarCalendario(mesActual, anioActual);