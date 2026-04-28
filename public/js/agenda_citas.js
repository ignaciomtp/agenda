let citaSeleccionada = null; // variable global para guardar la cita actual

document.addEventListener("DOMContentLoaded", () => {
    
    dibujarAgenda();

  // --- Botones cambio de día ---
  prevBtn.addEventListener("click", () => cambiarDia(-1));
  nextBtn.addEventListener("click", () => cambiarDia(1));

  // ===== LISTENERS GLOBALES =====
  // Movimiento de ratón
  document.addEventListener("mousemove", () => {
    document.querySelectorAll(".usuario-nombre.show-tooltip").forEach(el => {
      el.classList.remove("show-tooltip");
    });
  });
  //  Uno o más puntos táctiles se mueven a lo largo de la superficie táctil
  document.addEventListener("touchmove", () => {
    document.querySelectorAll(".usuario-nombre.show-tooltip").forEach(el => {
      el.classList.remove("show-tooltip");
    });
  });

  document.addEventListener("click", (e) => {
    if (!e.target.classList.contains("usuario-nombre")) {
      document.querySelectorAll(".usuario-nombre.show-tooltip").forEach(el => {
        el.classList.remove("show-tooltip");
      });
    }
  });

  // Para citas sin usuario (li)
  document.querySelectorAll('.citas-sin-usuario li').forEach((li, index) => {
      li.addEventListener('click', () => mostrarModalCita("asignar", citasSinUsuario[index]));
  });

});

// --- Recalcular agenda al redimensionar ---
window.addEventListener('resize', dibujarAgenda);

/**
 * Dibuja la agenda donde se muestran las citas de cada usuario
 */
function dibujarAgenda() {
    
    const headerUsuarios = document.getElementById("header-usuarios");
    const zona = document.getElementById("zona-calendario");
    
    // Limpiar contenido previo por si se redimensiona. Limpiar citas y líneas divisorias existentes
    document.querySelectorAll('#zona-calendario .cita').forEach(cita => cita.remove());
    document.querySelectorAll('#zona-calendario .linea-division').forEach(linea => linea.remove());
    
  const HORA_INICIO = horaInicioDia;
  const PIXELS_POR_HORA = 60;

  // --- 1. Encabezado de usuarios ---
  headerUsuarios.innerHTML = '';

  // Columna vacía sobre la hora
  const firstCell = document.createElement('div');
  firstCell.className = 'usuario-nombre encabezado-col-horas';
  firstCell.textContent = '';
  firstCell.style.flex = '0 0 80px';
  firstCell.style.width = '80px';
  headerUsuarios.appendChild(firstCell);

  if (usuariosConCitas.length > 0) {
    const zonaWidth = zona.clientWidth - 80; // resto sin contar columna de horas
    const anchoColumna = zonaWidth / usuariosConCitas.length;

    usuariosConCitas.forEach((u, i) => {
      const div = document.createElement("div");
      div.className = "usuario-nombre";
      // Añadimos el atributo data-nombre para el tooltip
      div.setAttribute('data-nombre', u.Usuario);
      // Creamos el span interno para recortar texto
      const span = document.createElement("span");
      span.textContent = u.Usuario;
      div.appendChild(span);
      // Fijar ancho de columna dinámicamente
      div.style.flex = '0 0 ' + anchoColumna + 'px';
      div.style.width = anchoColumna + 'px';
      headerUsuarios.appendChild(div);

      // línea divisoria entre columnas
      if (i > 0) {
        const linea = document.createElement("div");
        linea.className = "linea-division";
        linea.style.left = `${80 + i * anchoColumna -1}px`;
        zona.appendChild(linea);
      }

      // tooltip (ver nombre de usuario al pasar ratón o pulsar)
      div.addEventListener("click", (e) => {
        // primero quitamos show-tooltip de todos
        document.querySelectorAll(".usuario-nombre.show-tooltip").forEach(el => {
          if (el !== div) el.classList.remove("show-tooltip");
        });

        // alternar solo en este div
        div.classList.toggle("show-tooltip");

        // evitar que el click se propague y cierre inmediatamente si hay listener global
        e.stopPropagation();
      });
    });
  }

  // --- 2. Dibujar citas en el horario ---
  const agrupadas = agruparCitasPorUsuario(citas);

  // Recorremos cada usuario en el MISMO orden que el encabezado
  usuariosConCitas.forEach((u, idxUsuario) => {
    const usuarioId = u.Ref_Usuario;
    const citasUsuario = agrupadas[usuarioId] || []; // si no tiene citas, array vacío
    const zonaWidth = zona.clientWidth - 80;
    const anchoColumna = zonaWidth / usuariosConCitas.length;
    const margen = 5;

    // Generamos grupos de citas solapadas
    const grupos = generarBloquesSolapados(citasUsuario);

    grupos.forEach(grupo => {
        grupo.forEach((cita, index) => {
            const inicio = new Date(cita.Hora.replace(" ", "T"));
            const fin = new Date(cita.Hora_Fin.replace(" ", "T"));
            const minutosInicio = (inicio.getHours() - HORA_INICIO) * 60 + inicio.getMinutes();
            const minutosFin = (fin.getHours() - HORA_INICIO) * 60 + fin.getMinutes();
            const duracion = minutosFin - minutosInicio;

            const top = (minutosInicio / 60) * PIXELS_POR_HORA;
            const height = (duracion / 60) * PIXELS_POR_HORA;

            const div = document.createElement("div");
            div.className = "cita";
            div.style.top = `${top}px`;
            div.style.height = `${height}px`;

          // Guardar datos para recalculo
            div.dataset.idxUsuario = idxUsuario;
            div.dataset.indexGrupo = index;
            div.dataset.totalGrupo = grupo.length;

            // Calculamos left y width según solapamientos
            const margenEntreCitas = 2; // separación entre citas
            const total = grupo.length;
            const anchoIndividual = (anchoColumna - 2 * margen - (total - 1) * margenEntreCitas) / total;
            div.style.left = `${80 + idxUsuario * anchoColumna + margen + index * (anchoIndividual + margenEntreCitas)}px`;
            div.style.width = `${anchoIndividual}px`;

            const colores = ["#FFB74D", "#64B5F6", "#81C784", "#BA68C8", "#E57373"];
            div.style.backgroundColor = colores[idxUsuario % colores.length];

            div.textContent = `(${inicio.getHours()}:${inicio.getMinutes().toString().padStart(2,'0')} - ${fin.getHours()}:${fin.getMinutes().toString().padStart(2,'0')}) ${cita.Cliente} - ${cita.Concepto}`;

            div.dataset.id = cita.Ref_Agenda;
            div.addEventListener("click", () => mostrarModalCita("editar", cita));

            zona.appendChild(div);
        });
    });
  });
}

 // Función para abrir modal con los datos
function mostrarModalCita(tipo, cita) {
  // tipo = 'asignar' o 'editar'
    citaSeleccionada = cita; // guardamos la cita actual
    const btnGuardar = document.getElementById('btnGuardar');
    const btnEditar = document.getElementById('btnEditar');
    const btnEliminar = document.getElementById('btnEliminar');
    const modalSelectUsuario = document.getElementById('modalSelectUsuario');
    const idCita = citaSeleccionada.Ref_Agenda;

     // Reiniciamos posibles listeners antiguos de botones Editar, Eliminar y Guardar
    const nuevoBtnEditar = btnEditar.cloneNode(true);
    btnEditar.parentNode.replaceChild(nuevoBtnEditar, btnEditar);

    const nuevoBtnEliminar = btnEliminar.cloneNode(true);
    btnEliminar.replaceWith(nuevoBtnEliminar);

    const nuevoBtnGuardar = btnGuardar.cloneNode(true);
    btnGuardar.parentNode.replaceChild(nuevoBtnGuardar, btnGuardar);
    
    const redirectURL = `agendaCitas.php?usuario=${usuarioSeleccionado}&fecha=${fechaSeleccionada}`;

    nuevoBtnEditar.addEventListener('click', () =>{
        // Al tener que mandar varios "?" en parámetro redirect codificamos la URL
        // Así se envía algo como editarCita.php?id=45&redirect=agendaCitas.php%3Fusuario%3Dtodos%26fecha%3D2025-11-13 evitando que rompa la URL 
        window.location.href = `editarCita.php?id=${idCita}&redirect=${encodeURIComponent(redirectURL)}`;
    });

    nuevoBtnEliminar.addEventListener('click', () => {
        if(confirm("¿Está seguro de eliminar la cita seleccionada?")){
            window.location.href = `eliminarCita.php?id=${idCita}&redirect=${encodeURIComponent(redirectURL)}`;
        }
    });

    // Procesar cuando se asigna un Usuario a una cita
    nuevoBtnGuardar.addEventListener('click', () => {
      if (!citaSeleccionada) return; // seguridad por si no hay cita

      const citaId = document.getElementById('btnGuardar').dataset.id;
      const usuarioId = document.getElementById('selectUsuario').value;
      const horaInicio = citaSeleccionada.Hora; 
      const horaFin = citaSeleccionada.Hora_Fin;

      // Contenedor para alertas
        let alertContainer = document.getElementById('alertCita');
        if (!alertContainer) {
            alertContainer = document.createElement('div');
            alertContainer.id = 'alertCita';
            alertContainer.classList.add('mt-2');
            document.getElementById('detalleCitaModal').querySelector('.modal-body').appendChild(alertContainer);
        }
        alertContainer.innerHTML = '';

      fetch('asignarUsuario.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: `idCita=${citaId}&usuario=${usuarioId}&horaInicio=${horaInicio}&horaFin=${horaFin}`
      })
      .then(r => r.json())
       .then(res => {
        if (res.status === 'conflicto') {
            let textoConflictos = res.mensaje.map(c => `${c.Cliente} (${c.Hora_formateada} - ${c.Hora_Fin_formateada})`).join('\n');
            if (!confirm(`Esta cita coincide con otra(s):\n${textoConflictos}\n¿Deseas asignarla de todos modos?`)) return;
            // re-asignar ignorando conflictos
            return fetch('asignarUsuario.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `idCita=${citaId}&usuario=${usuarioId}&horaInicio=${horaInicio}&horaFin=${horaFin}&ignorarConflicto=1`
            }).then(r => r.json())
            .then(res2 => {
              const modalDetalle = bootstrap.Modal.getInstance(document.getElementById('detalleCitaModal'));
              modalDetalle.hide();

              setTimeout(() => {
                if(confirm("¿Deseas enviar la confirmación de la cita?")) {
                  mostrarModalConfirmacion(citaId);
                } else {
                  window.location.reload();
                }
              }, 300);
            });
        }  else {
            const modalDetalle = bootstrap.Modal.getInstance(document.getElementById('detalleCitaModal'));
            modalDetalle.hide();
            // No hay conflicto, vamos directo a confirmación
            // Esperamos un pequeño tiempo para evitar cortes visuales
            setTimeout(() => {
              if(confirm("¿Deseas enviar la confirmación de la cita?")) {
                mostrarModalConfirmacion(citaId);
              } else {
                window.location.reload();
              }
            }, 300);
        }    
    })
    .catch(() => {
        alertContainer.innerHTML = `<div class="alert alert-danger">Error al asignar la cita.</div>`;
    });
  }); 

    if (tipo === 'editar') {
      nuevoBtnEditar.classList.remove('d-none');
      nuevoBtnEliminar.classList.remove('d-none');
      nuevoBtnGuardar.classList.add('d-none');
    } else if (tipo === 'asignar') {
      modalSelectUsuario.classList.remove('d-none');
      nuevoBtnEditar.classList.add('d-none');
      nuevoBtnEliminar.classList.add('d-none');
      nuevoBtnGuardar.classList.remove('d-none');
      cargarUsuariosEnSelect();
    }

    document.getElementById('modalCliente').textContent = cita.Cliente || '';
    document.getElementById('modalConcepto').textContent = cita.Concepto || '';
    document.getElementById('modalFecha').textContent = cita.Fecha_formateada || '';
    document.getElementById('modalHora').textContent = cita.Hora_formateada 
    ? `${cita.Hora_formateada} - ${cita.Hora_Fin_formateada || ''}` 
    : '';
    document.getElementById('modalNotas').textContent = cita.Notas || '';

    // Guardar el ID internamente
    nuevoBtnGuardar.dataset.id = cita.Ref_Agenda;
    nuevoBtnEditar.dataset.id = cita.Ref_Agenda;
    nuevoBtnEliminar.dataset.id = cita.Ref_Agenda;
    
    const modal = new bootstrap.Modal(document.getElementById('detalleCitaModal'));
    modal.show();
}

// Función para mostrar el modal de confirmación
function mostrarModalConfirmacion(citaId) {
    const modalEl = document.getElementById('confirmacionCitaModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    // Si se cierra el modal (botón cancelar o la X), recargar página
    modalEl.addEventListener('hidden.bs.modal', () => {
        window.location.reload();
    }, { once: true });

    const btnEnviar = modalEl.querySelector('#btnEnviarConfirmacion');
    const nuevoBtnEnviar = btnEnviar.cloneNode(true);
    btnEnviar.parentNode.replaceChild(nuevoBtnEnviar, btnEnviar);

    // Contenedor para mostrar alert de bootstrap
    let alertContainer = modalEl.querySelector('#resultadoConfirmacion');
    if (!alertContainer) {
        alertContainer = document.createElement('div');
        alertContainer.id = 'resultadoConfirmacion';
        alertContainer.classList.add('mt-2'); // margen top
        modalEl.querySelector('.modal-body').appendChild(alertContainer);
    }

    nuevoBtnEnviar.addEventListener('click', () => {
        const canal = modalEl.querySelector('input[name="canalConfirmacion"]:checked')?.value;
        if (!canal) {
            alertContainer.innerHTML = `<div class="alert alert-warning">Selecciona un canal para enviar la confirmación.</div>`;
            return;
        }

        fetch('enviarConfirmacion.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `idCita=${citaId}&canal=${canal}`
        })
        .then(r => r.json())
        .then(res => {
            // Mostrar alerta de bootstrap 
            alertContainer.innerHTML = res.mensaje;

            // Cerrar modal y recargar agenda tras 1.5s para que el usuario vea el mensaje
            setTimeout(() => {
                modal.hide();
                window.location.reload();
            }, 3000);
        })
        .catch(err => {
            alertContainer.innerHTML = `<div class="alert alert-danger">Error al enviar la confirmación.</div>`;
        });
    }, { once: true });
}



function cambiarDia(direccion) {
  const inputFecha = document.getElementById('fecha');
  const fechaActual = new Date(inputFecha.value);
  fechaActual.setDate(fechaActual.getDate() + direccion);
  const nuevaFecha = fechaActual.toISOString().split('T')[0];

  const params = new URLSearchParams(window.location.search);
  params.set('fecha', nuevaFecha);

  window.location.search = params.toString();
}

function cargarUsuariosEnSelect() {
    const select = document.getElementById('selectUsuario');
    select.innerHTML = '';
    usuarios.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.Ref_Usuario;
        opt.textContent = c.Usuario;
        select.appendChild(opt);
    });
}

/**
 *   Crea un objeto. Cada clave es un Usuario y el valor es un array con sus citas: {1:[cita1, cita2], 2:[cita3]...}
 * @param {*} citas 
 * @returns Objeto formado por arrays (en cada array están las citas de cada usuario)
 */
function agruparCitasPorUsuario(citas) {
    const agrupadas = {};
    citas.forEach(cita => {
        if (!agrupadas[cita.Usuario]) agrupadas[cita.Usuario] = [];
        agrupadas[cita.Usuario].push(cita);
    });
    return agrupadas;
}

/**
 * Ordena las citas por hora de inicio para revisar secuencialmente
 * Recorre cada cita mirando grupos existentes. Si cita empieza antes de terminar la última del grupo (se solapa) se añade al grupo
 * Si no encaja en un grupo, se crea nuevo grupo y se añade
 * 
 * @param {*} citasUsuario Array con las citas de un usuario
 * @returns Array de arrays (cada subarray contiene citas solapadas) 
 * Ej: [ 
 *        [citaA, citaB] -> se solapan
 *        [citaC] -> no se solapa con ninguna
 *     ]
 */ 
function generarBloquesSolapados(citasUsuario) {
    citasUsuario.sort((a, b) => new Date(a.Hora) - new Date(b.Hora));
    const grupos = [];

    citasUsuario.forEach(cita => {
        let agregado = false;
        for (const grupo of grupos) {
            const ultima = grupo[grupo.length - 1];
            if (new Date(cita.Hora) < new Date(ultima.Hora_Fin)) {
                grupo.push(cita);
                agregado = true;
                break;
            }
        }
        if (!agregado) {
            grupos.push([cita]);
        }
    });

    return grupos;
}