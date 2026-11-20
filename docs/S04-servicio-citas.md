> Versión inicial: alcance actualizado en `implementacion-integrada.md`. Consultar esa guía para proveedores HTTP, pólizas persistentes, flujo integrado, copagos y conexión OSRM verificada.

# Implementación SOA: S04 — Agendamiento de citas

## Alcance y clasificación

Servicio de negocio en la **capa de servicios** de la arquitectura propuesta. El consumidor implementado es `citas.html` con `js/citas.js`, ubicado en la capa de clientes y canales. MySQL/MariaDB constituye el repositorio de datos en la capa de sistemas. Registro, login y sesión son servicios auxiliares de seguridad; no sustituyen los cinco servicios del inventario.

En este incremento se implementan consulta de médicos, consulta de disponibilidad, creación de reserva y consulta de citas propias. Reprogramación, cancelación, idempotencia por identificador de solicitud y conexiones con S01/S02 se encuentran pendientes. No hay integración real con RENIEC, aseguradoras ni SUNAT. Los médicos son ficticios y las franjas horarias son una agenda fija de demostración, no el horario real de una clínica.

## Propuesta técnica

- Modelo: API HTTP de estilo REST, con recursos expuestos mediante scripts PHP y formato JSON; no se utiliza SOAP.
- Enfoque de este incremento: **code-first**; se documenta el contrato implementado. El contrato previo del APF2 es una referencia conceptual y debe actualizarse para reflejar estas rutas y parámetros.
- Tecnologías: PHP 8.2 con mysqli y sesiones, MariaDB 10.4, HTML/CSS/JavaScript con fetch.
- Integración actual: comunicación directa del consumidor con el servicio. No se ha implementado un ESB ni un orquestador de S01/S02/S04; esos componentes continúan como propuesta arquitectónica.
- Base local de prueba: `http://127.0.0.1:8085`.

## Operaciones implementadas

| Operación | Ruta y método | Entrada | Respuesta correcta |
|---|---|---|---|
| Consultar médicos activos | GET `/php/doctores.php` | Cookie de sesión | 200, arreglo `datos` con id, nombre y especialidad |
| Consultar disponibilidad | GET `/php/disponibilidad.php?doctor_id=1&fecha=AAAA-MM-DD` | Médico entero positivo y fecha | 200, horarios libres, duración de 30 minutos |
| Reservar cita | POST `/php/reservar_cita.php` | Cookie, cabecera X-CSRF-Token y JSON con doctor_id, fecha, hora y motivo | 201, idCita, codigoReserva, estado, médico, fecha y hora |
| Consultar mis citas | GET `/php/obtener_citas.php` | Cookie de sesión | 200, citas exclusivamente del paciente autenticado |

El paciente se determina desde la sesión. La API de reserva rechaza `paciente_id` y `usuario_id` enviados por el consumidor. La consulta de citas no permite cambiar de paciente mediante parámetros de URL.

### Datos intercambiados

Solicitud de reserva (fecha ilustrativa; seleccionar una fecha futura dentro de 90 días):

```http
POST /php/reservar_cita.php
Content-Type: application/json
X-CSRF-Token: <token obtenido de sesion.php>
Cookie: PHPSESSID=<sesion autenticada>
```

```json
{"doctor_id":1,"fecha":"2026-11-25","hora":"10:00","motivo":"Consulta de prueba"}
```

Respuesta de ejemplo; el identificador lo genera la base de datos:

```json
{"success":true,"message":"Cita reservada correctamente.","datos":{"idCita":5,"codigoReserva":"CITA-000005","estado":"RESERVADA","doctor_id":1,"fecha":"2026-11-25","hora":"10:00"}}
```

## Validaciones y responsabilidades

1. Sesión autenticada con rol paciente y perfil de paciente existente.
2. Médico existente y activo; fecha válida entre hoy y los siguientes 90 días, con zona horaria America/Lima; momento de atención estrictamente futuro.
3. Agenda de demostración: bloques de 30 minutos de 08:00 a 16:30, todos los días. Esta grilla debe reemplazarse por agenda médica real en una siguiente versión.
4. Motivo obligatorio de máximo 500 caracteres.
5. La restricción única `(doctor_id, fecha, hora)` en la base de datos impide dos reservas del mismo turno, incluso si dos solicitudes compiten. Las horas admitidas siempre coinciden con la grilla de 30 minutos.
6. Registro transaccional de usuario, información personal, rol y paciente: se confirma todo o se revierte todo. Correo único y contraseña de 8 a 72 bytes, almacenada con password_hash y verificada con password_verify.

## Seguridad aplicada y pendiente

Implementado: autenticación mediante sesión PHP; autorización por rol y propiedad de citas; regeneración del identificador de sesión al iniciar sesión; cookie HttpOnly y SameSite=Lax; token CSRF para registro, login, reserva y logout; consultas preparadas; respuestas sin detalles SQL; salida de textos de usuario con textContent en la interfaz. La cookie activa Secure cuando el servidor recibe HTTPS.

**El servidor de pruebas usa HTTP local, por lo que HTTPS todavía no está implementado ni demostrado.** Para presentar HTTPS como medida aplicada se debe configurar y verificar un servidor HTTPS. Esta demostración no cumple aún toda la seguridad de producción propuesta en el APF2: quedan TLS, límites de intentos de login, auditoría, expiración explícita por inactividad y revisión de configuración de despliegue. La cuenta root sin contraseña corresponde exclusivamente al XAMPP local; antes de un despliegue debe crearse un usuario de base de datos con permisos limitados y usar variables de entorno.

## Manejo de errores

| HTTP | Código | Tratamiento del consumidor |
|---|---|---|
| 400 | DATOS_INVALIDOS, FECHA_INVALIDA, FECHA_FUERA_DE_RANGO, FECHA_PASADA, HORA_INVALIDA, CAMPO_NO_PERMITIDO | Corregir la entrada; no reintentar automáticamente |
| 401 | SESION_REQUERIDA o CREDENCIALES_INVALIDAS | Iniciar sesión o corregir credenciales |
| 403 | CSRF_INVALIDO o ACCESO_DENEGADO | Actualizar sesión o revisar permisos |
| 404 | MEDICO_NO_ENCONTRADO | Seleccionar otro médico |
| 409 | HORARIO_NO_DISPONIBLE o CORREO_REGISTRADO | Elegir otro turno o usar el correo registrado |
| 405 | METODO_NO_PERMITIDO | Usar el método documentado |
| 500 | ERROR_INTERNO | Informar al usuario; detalle técnico solo en el registro del servidor |

Ejemplo de conflicto:

```json
{"success":false,"error":{"codigo":"HORARIO_NO_DISPONIBLE","mensaje":"El turno acaba de ser reservado. Consulte otro horario."},"message":"El turno acaba de ser reservado. Consulte otro horario."}
```

No hay reintentos automáticos de creación. Si se pierde una respuesta se debe consultar el listado de citas antes de volver a reservar; falta implementar el identificador de idempotencia del APF2.

## Evidencias y estado de la entrega

`tests/integracion.py` ejecuta solicitudes HTTP reales contra la base de prueba. La ejecución realizada validó 25 casos, incluidos registro, login, disponibilidad, reserva 201, conflicto 409, CSRF 403, sesión 401 y aislamiento de citas entre dos usuarios. El archivo `tests/ultima-ejecucion.json` contiene esas respuestas; no corresponde a una ejecución de Postman. Las cuentas temporales de la prueba se eliminan al finalizar.

La interfaz también se probó mediante registro, login y reserva de una cita ficticia, observando el código de confirmación, el listado y la respuesta JSON 201. La captura se entrega como `S04-reserva-verificada.jpg`.

La colección `docs/S04.postman_collection.json` se entrega para reproducir manualmente el flujo en Postman. Las evidencias actuales cubren un servicio de negocio S04 y sus auxiliares. Todavía faltan implementar y documentar S01, S02, S03 y S05, completar HTTPS y los demás apartados de la rúbrica.
