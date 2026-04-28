document.addEventListener('DOMContentLoaded', () => {
    // Cargar todas las estadísticas al cargar la página
    generarSelectAnios();
    cargarTodo();

    // Al cambiar el año, recargar todas las estadísticas
    document.getElementById("select-anio").addEventListener("change", cargarTodo);
});

// Función genérica para reutilizar fetch y procesar JSON
async function obtenerDatos(endpoint, anio = null) {
    try {
        if (anio !== null) {
            endpoint += `?anio=${anio}`;
        }

        const res = await fetch(endpoint);
        if (res.status === 401) {
            window.location.href = '/login.php';
            return;
        }
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Error desconocido');
        return data.data;

    } catch (error) {
        console.error(`Error en ${endpoint}:`, error);
        return [];
    }
}

// --- Función para Clientes con más citas ---
async function cargarClientes() {
    const contenedor = document.getElementById('estadistica-clientes');
    const anio = document.getElementById("select-anio").value;

    const datos = await obtenerDatos('estadisticas/clientes.php', anio);

    if (datos.length === 0) {
        contenedor.innerHTML = '<p>No hay datos disponibles.</p>';
        return;
    }

    let html = '<table><thead><tr><th>Cliente</th><th>Citas</th><th>Última cita</th></tr></thead><tbody>';
    datos.forEach(d => {
        html += `<tr>
            <td>${d.Cliente}</td>
            <td>${d.Citas}</td>
            <td>${formatearFecha(d.UltimaCita)}</td>
        </tr>`;
    });
    html += '</tbody></table>';
    contenedor.innerHTML = html;
}

// --- Función para Días con más citas ---
async function cargarDias() {
    const contenedor = document.getElementById('estadistica-dias');
    const anio = document.getElementById("select-anio").value;

    const datos = await obtenerDatos('estadisticas/dias.php', anio);

    if (datos.length === 0) {
        contenedor.innerHTML = '<p>No hay datos disponibles.</p>';
        return;
    }

    let html = '<table><thead><tr><th>Fecha</th><th>Citas</th></tr></thead><tbody>';
    datos.forEach(d => {
        html += `<tr>
            <td>${formatearFecha(d.Fecha)}</td>
            <td>${d.Citas}</td>
        </tr>`;
    });
    html += '</tbody></table>';
    contenedor.innerHTML = html;
}

// --- Función para Servicios más solicitados ---
async function cargarServicios() {
    const contenedor = document.getElementById('estadistica-servicios');
    const anio = document.getElementById("select-anio").value;

    const datos = await obtenerDatos('estadisticas/servicios.php', anio);

    if (datos.length === 0) {
        contenedor.innerHTML = '<p>No hay datos disponibles.</p>';
        return;
    }

    let html = '<table><thead><tr><th>Servicio</th><th>Cantidad</th></tr></thead><tbody>';
    datos.forEach(d => {
        html += `<tr>
            <td>${d.Servicio}</td>
            <td>${d.Cantidad}</td>
        </tr>`;
    });
    html += '</tbody></table>';
    contenedor.innerHTML = html;
}

// --- Función para Horas con más demanda ---
async function cargarHoras() {
    const contenedor = document.getElementById('estadistica-horas');
    const anio = document.getElementById("select-anio").value;

    const datos = await obtenerDatos('estadisticas/horas.php', anio);

    if (datos.length === 0) {
        contenedor.innerHTML = '<p>No hay datos disponibles.</p>';
        return;
    }

    let html = '<table><thead><tr><th>Hora</th><th>Citas</th></tr></thead><tbody>';
    datos.forEach(d => {
        html += `<tr>
            <td>${d.Hora}</td>
            <td>${d.Citas}</td>
        </tr>`;
    });
    html += '</tbody></table>';
    contenedor.innerHTML = html;
}

// Variable global para guardar la instancia del gráfico (si no, se intenta crear un nuevo gráfico sobre uno que ya existe)
let graficoCitasMes = null;

// --- Función para Citas por mes (gráfico) ---
async function cargarCitasMes() {
    const ctx = document.getElementById('grafico-citas-mes').getContext('2d');
    const anio = document.getElementById("select-anio").value;

    const datos = await obtenerDatos('estadisticas/citasMes.php', anio);

    if (datos.length === 0) {
        ctx.canvas.parentNode.innerHTML = '<p>No hay datos disponibles para el gráfico.</p>';
        return;
    }

    const nombresMeses = [
        "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
        "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
    ];

    const colores = [
        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
        '#9966FF', '#FF9F40', '#C9CBCF', '#FF6666',
        '#66FF66', '#6666FF', '#FFCC99', '#99CCFF'
    ];

    // datos es un objeto: { "1": 2, "2": 0, ... }, el json que devuelve el endpoint citasMes.php
    // Chart.js espera dos arrays: uno para las etiquetas (labels) y otro para los valores (data).
    const labels = Object.keys(datos).map(m => nombresMeses[parseInt(m) - 1]); // Convierte "1" → índice 0 Resultado: [Enero, Febrero...]
    const valores = Object.values(datos);       // Devuelve array con todos los valores del objeto: [2, 0, 0, ...]

    // Destruye el gráfico anterior si existe al recargar los datos
    if (graficoCitasMes) {
        graficoCitasMes.destroy();
    }

    // Plugin para dibujar línea vertical al final del eje X
    const pluginLineaFinal = {
        id: 'lineaFinal',
        afterDraw: (chart) => {
            const xAxis = chart.scales.x;
            const yAxis = chart.scales.y;
            const ctx = chart.ctx;

            ctx.save();
            ctx.strokeStyle = '#acacacff';   // color de la línea
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(xAxis.right, yAxis.top);
            ctx.lineTo(xAxis.right, yAxis.bottom);
            ctx.stroke();
            ctx.restore();
        }
    };

    // Crear nueva instancia
    // labels son los nombres que se muestran en el eje X del gráfico y valores son las alturas de las barras
    graficoCitasMes = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Citas',
                data: valores,
                backgroundColor: colores, // un color por barra
                borderColor: 'rgba(7, 7, 7, 1)',
                borderWidth: 1,
                borderSkipped: false // asegura que se dibujen todos los bordes
            }]
        },
        options: {
            responsive: true,
            scales: { 
                x: {
                    offset: true, // deja espacio al principio y final
                    grid: {
                        drawTicks: true,
                        drawBorder: true, // asegura la línea del borde
                        color: '#ccc',    // color de las líneas verticales
                    },
                    // Ajusta las barras para que no queden pegadas al borde
                    categoryPercentage: 0.8, 
                    barPercentage: 0.9
                },
                y: { 
                    beginAtZero: true,
                    grid: {
                        color: '#eee'
                    } 
                } 
            }
        },
        plugins: [pluginLineaFinal]
    });
}

// Formatear la fecha a d/m/Y
function formatearFecha(fechaString) {
    const fecha = new Date(fechaString);
    if (isNaN(fecha)) return ''; // Si la fecha es inválida

    const dia = String(fecha.getDate()).padStart(2, '0');
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const año = fecha.getFullYear();

    return `${dia}/${mes}/${año}`;
}

// Formatear la hora a H:i (al final no será necesaria, ya que se formatea desde la consulta a la BD)
// SELECT CONVERT(VARCHAR(5), Hora, 108) AS Hora
function formatearHora(fechaString) {
    const fecha = new Date(fechaString);
    if (isNaN(fecha)) return '';

    const horas = String(fecha.getHours()).padStart(2, '0');
    const minutos = String(fecha.getMinutes()).padStart(2, '0');

    return `${horas}:${minutos}`;
}

// Generar años dinámicamente
function generarSelectAnios() {
    const select = document.getElementById("select-anio");

    const anioActual = new Date().getFullYear();
    const anioInicial = anioActual - 15; // mostrar 15 años hacia atrás

    for (let a = anioActual; a >= anioInicial; a--) {
        const option = document.createElement("option");
        option.value = a;
        option.textContent = a;
        select.appendChild(option);
    }

    select.value = anioActual; // seleccionar el año por defecto
}

// Función global para cargar todo
function cargarTodo() {
    cargarClientes();
    cargarDias();
    cargarServicios();
    cargarHoras();
    cargarCitasMes();
}