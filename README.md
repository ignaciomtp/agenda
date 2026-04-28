# Sistema de gestión de citas de peluquería – Versión 3

## 1. Datos del proyecto
- **Nombre del proyecto:** Sistema de gestión de citas de peluquería
- **Autor:** Andrés Reino Guerra
- **Versión:** 3 (v3)
- **Fecha:** 19/11/2025
- **Herramientas utilizadas:**
  - PHP 8.3.26
  - SQL Server
  - HTML / CSS / Bootstrap
  - PHPMailer (v6.11.1)

---

## 2. Objetivo
Permitir a los clientes solicitar citas a través de un formulario web. Los datos de dicha solicitud se envían por correo electrónico tanto al cliente como al dueño usando PHPMailer. Se registran los siguientes datos:

- Cliente
- Teléfono
- Email del cliente
- Servicio
- Fecha
- Hora de inicio
- Hora de finalización (sólo aparece en correo de confirmación)
- Duración del servicio (sólo aparece en correo de confirmación)
- Notas

Gestionar las citas existentes en la base de datos, configurando el usuario aspectos como el envío de recordatorio y la plantilla del mismo. 

---

## 3. Funcionalidades de la versión 3
- Formulario web de registro de citas.
- Validación básica de campos en HTML y validación en servidor del nombre, teléfono y email.
- Envío de correo a cliente y dueño con datos ampliados (hora de finalización y duración del servicio).
- Confirmación visual en pantalla tras enviar la cita y correos, si no ha habido ningún error.
- Servicios se recogen de la base de datos de entre los disponibles (marcados con botón "web" en el ERP): tabla Artículos.
- Peluqueros disponibles se recogen de la base de datos: tabla Usuarios.
- Redirección a formulario en caso de error/es, mostrando los mismos.
- Autocompletado de campos con valores originales en caso de ser redirigido por existir algún error.
- Inserción de los datos de la cita creada en la tabla Agendas. El correo y el teléfono se añaden en la columna "notas" de la tabla Agendas al no haber específicas al efecto.
- Comprobación de solapamiento de citas al crear una nueva. Si no hay disponibilidad, redirige a formulario indicando este hecho y manteniendo los datos introducidos por el usuario.
- Selección de plantilla para el envío de recordatorio de entre 3 preconfiguradas o creación de una personalizada.
- Envío de recordatorio de cita manual: a través de email, sms y whatsapp desde un calendario donde cliente selecciona las citas por días y decide el tipo de envío.
- Envío de recordatorio de cita automático mediante una tarea programada. Registro de logs (recordatorios_auto.log).
- Agenda de citas, filtrando por día y por un usuario individual o todos los usuarios. 
- Columna que muestra las citas sin usuario asignado al lado de la agenda e implementación de botón para asignar usuario, con posibilidad de enviar confirmación de cita por 3 canales: email, sms o whatsapp.
- Modales para visualización de los detalles de una cita concreta al pulsar sobre ella. Botones para editar o eliminar la cita desde el modal.
- Panel principal con datos estadísticos (seleccionados por año) de varias categorías en forma de tabla y gráfico de barras con el número total de citas por mes.

### 📌 Ejecución automática de recordatorios
- **Archivo:** `scripts/enviarRecordatoriosAuto.php`  
- **Función:** Envía recordatorios de citas automáticamente según la configuración del usuario (email, SMS o WhatsApp).  
- **Configuración de envío:**  
  - **Mismo día:** Se envía a las citas que faltan menos de `Recordatorio_HorasMismoDia` horas.  
  - **Días de anticipación:** Se envía a las citas con `Recordatorio_DiasAnticipacion` días de antelación, a la hora `Recordatorio_HoraEnvio`.  
- **Log de actividad:**  
  - Archivo: `logs/recordatorios_auto.log`  
  - Registra envíos realizados ✅, ausencia de citas 💤 y errores ❌. 

**Ejemplo de entradas del log:**
```
[2025-10-27 13:09:25] Aún no es hora de enviar recordatorios programados 4 días antes.
[2025-10-28 09:30:00] Error general: Failed to parse time string (2025-10-28 00:00:00.000 2025-10-28 15:15:00.000) at position 24 (2): Double date specification
[2025-10-28 09:43:39] ❌ Error enviando correo a arcadium22@yahoo.es :Invalid address:  (From): 
[2025-10-28 09:48:56] ✅ Recordatorio enviado a arcadium22@yahoo.es
[2025-10-28 09:51:02] No hay citas pendientes para enviar recordatorio automático.
[2025-10-28 11:00:02] ✅ WhatsApp enviado a 34625678998 — SID: SM1502fd70d7f9d1f8124ac58d117a3819
```
---

## 4. Estructura del proyecto
```
peluqueria_v3/
│
├── logs/
│   └── recordatorios_auto.log        📜 Log de envíos automáticos de recordatorios
│
├── partials/
│   ├── header.php                    🧩 Cabecera común de la aplicación
│   ├── footer.php                    🧩 Pie de página común
│   └── formularioCita.php            📝 Formulario parcial reutilizable de cita
│
├── public/
│   ├── index.php                     📝 Login de usuario
│   ├── logout.php                    🔐 Cierre de sesión
│   ├── panel.php                     🖥 Panel de usuario después del login
│   ├── plantillaPagina.php           📝 Página genérica para crear otras
│   │
│   ├── citas/                         ## Área privada del dueño (acceso con login) ##
│   │   ├── agendaCitas.php             📋 Agenda con las citas por día (usuario individual o todos los usuarios)
│   │   ├── asignarUsuario.php          👤 Asignar cita a un usuario
│   │   ├── calendarioCitas.php         📆 Calendario de días con citas reservadas
│   │   ├── editarCita.php              ✏️ Editar cita existente
│   │   ├── eliminarCita.php            🗑 Eliminar cita
│   │   ├── enviarRecordatoriosManual.php 📧 Procesa el envío manual de recordatorios
│   │   ├── guardarPlantilla.php        💾 Guardar plantilla de recordatorio
│   │   ├── mostrarCitas.php            🖥 Mostrar listado de citas
│   │   ├── plantillasRecordatorios.php 📝 Selección de plantilla de recordatorio
│   │   └── procesarEdicionCita.php     📧 Procesa la edición de cita
│   │
│   ├── cliente/
│   │   ├── formularioCitaCliente.php 📝 Formulario de cita para cliente
│   │   └── procesarFormularioCitaCliente.php 📧 Procesa el formulario de cita cliente
│   │
│   ├── configuracionCitas/
│   │   ├── guardarPreferencias.php   💾 Guardar preferencias de envío de recordatorio
│   │   └── preferencias.php          ⚙️ Seleccionar preferencias de envío de recordatorio
│   │
│   ├── css/
│   │   ├── agenda_citas.css             🎨 Estilos de la agenda de citas
│   │   ├── calendario.css               🎨 Estilos del calendario
│   │   ├── formulario_citas.css         🎨 Estilos del formulario de citas
│   │   ├── formulario_login.css         🎨 Estilos del formulario de login
│   │   ├── header.css                   🎨 Estilos de la cabecera
│   │   ├── mostrar_citas.css            🎨 Estilos del listado de citas
│   │   ├── panel.css                    🎨 Estilos del panel principal
│   │   ├── plantillas_recordatorios.css 🎨 Estilos de la selección de plantillas
│   │   └── style_base.css               🎨 Estilos comunes a toda la app
│   │
│   ├── estadisticas/                 ## endpoints ##
│   │   ├── citasMes.php              📈 Devuelve JSON con el número de citas por mes
│   │   ├── clientes.php              🧑‍🤝‍🧑 Devuelve JSON con los clientes con más citas
│   │   ├── dias.php                  📅 Devuelve JSON con los días con más citas
│   │   ├── horas.php                 ⏰ Devuelve JSON con las horas con más demanda
│   │   └── servicios.php             💼 Devuelve JSON con los servicios más solicitados   
│   │
│   ├── img/
│   │   └── eclipse.ico               🖼 Icono de la empresa
│   │
│   └── js/
│       ├── agenda_citas.js           ⚙️ Lógica interactiva de la agenda de citas
│       ├── autoCloseAlerts.js        ⚙️ Cierra automáticamente alerts de éxito/error de Bootstrap
│       ├── calendario_citas.js       📅 Genera el calendario con las citas existentes del mes y año seleccionados
│       ├── mostrar_citas.js          👀 Control de visualización de citas
│       ├── panel_estadisticas.js     📊 Funciones JS para cargar y actualizar el gráfico y estadísticas del panel     
│       └── preferencias.js           ⚙️ Manejo JS de preferencias de envío
│
├── scripts/
│   └── enviarRecordatoriosAuto.php   ⏰ Script de envío automático de recordatorios con logs
│
├── src/
│   ├── auth/
│   │   └── checkSession.php          🔐 Comprobación de sesión activa
│   │
│   ├── config/
│   │   ├── config.ini                ⚙️ Configuración de base de datos, email y Twilio
│   │   ├── database.php              💾 Conexión a la base de datos mediante PDO
│   │   └── paths.php                 ⚙️ Define rutas base del proyecto para uso interno
│   │
│   └── estadisticas/
│       └── funcionesEstadisticas.php 📊 Funciones para generar estadísticas del panel principal          
│   │
│   └── lib/
│       ├── altiriaSMS.php            📱 Función para enviar SMS mediante la API de Altiria
│       ├── recordatorioService.php   📧 Funciones para enviar recordatorios (email, sms, whatsapp)
│       ├── smsSender.inc.php         📱 Clase para enviar SMS mediante la API de Dinahosting
│       └── utils.php                 ⚙️ Funciones generales para la aplicación
│
├── vendor/                           📚 Librerías instaladas mediante Composer
│   └── phpmailer/                    📧 Librería PHPMailer
│
├── composer.json                      📦 Configuración de dependencias PHP
├── composer.lock                      🔒 Registro exacto de dependencias instaladas
├── README.md                          📖 Documentación del proyecto
├── web.config                         ⚙️ Configuración para servidores IIS
└── .vscode/                           🛠 Configuración de Visual Studio Code
    
```
---

## 5. Base de datos
Conexión a la base de datos SQLServer mediante PDO.

---

## 6. Configuración y requisitos
- Servidor web: Apache / PHP
- PHPMailer instalado mediante Composer
- Configuración SMTP para envío de correos:
  - Host: `mail.eclipse.es`
  - Usuario: `fct@eclipse.es`
  - Puerto: 465, SSL implícito
  - Codificación: UTF-8

---

## 7. Instrucciones de uso
1. Abrir `login.php` en el navegador.
2. Rellenar el formulario con los datos del usuario.
3. Pulsar **Guardar**.
4. Si login es correcto redirige a `panel.php`.

---

## 8. Problemas conocidos y mejoras pendientes
- Comprobar que la fecha de inicio de la cita no excede el horario del establecimiento.
- Comprobar que la hora final (con la duración del servicio) no excede el horario de cierre del establecimiento.
- Implementar sistema de envío de recordatorio de cita a través de whatsapp con API de WA (actualmente funciona con Twilio).

---