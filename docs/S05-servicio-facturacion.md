> Versión inicial: alcance actualizado en `implementacion-integrada.md`. Consultar esa guía para proveedores HTTP, pólizas persistentes, flujo integrado, copagos y conexión OSRM verificada.

# S05 — Facturación de prueba

## Identificación, clasificación y capas

**S05 — Facturación Electrónica**, implementado en este incremento como generación y consulta de documentos académicos. Servicio de negocio con adaptador de proveedor simulado en la capa de servicios; consumidor `citas.html` en clientes y canales; MariaDB y tabla `comprobantes_demo` en sistemas. El simulador local representa respuestas de aceptación, rechazo e indisponibilidad. No existe conexión con SUNAT y los documentos no tienen validez tributaria.

La responsabilidad es guardar un documento de prueba vinculado a una cita propia, calcular el importe en el servidor, consultar su estado y permitir reenviarlo al simulador conservando su identidad. No cobra dinero ni acredita una atención realizada. No genera XML UBL, firma digital, CDR ni PDF fiscal. El código de documento usa prefijo DEMO y las respuestas incluyen validezTributaria=false y pagoRealizado=false.

## Propuesta técnica y operaciones

API HTTP de estilo REST con JSON; PHP 8.2/mysqli, sesiones PHP y consumidor JavaScript/fetch. Enfoque code-first. Comunicación directa; no ESB ni orquestación integral todavía.

| Operación | Método y ruta | Entrada | Resultado |
|---|---|---|---|
| Generar comprobante | POST `/php/comprobantes.php` | operacion=generar, idCita, escenario opcional | 201 documento guardado; 503 si queda pendiente |
| Consultar mis comprobantes | GET `/php/comprobantes.php` | Sesión | 200 arreglo propio |
| Consultar documento | GET `/php/comprobantes.php?id=1` | id entero positivo | 200 detalle propio; 404 para inexistente o ajeno |
| Reenviar documento | POST `/php/comprobantes.php` | operacion=reenviar, idComprobante, escenario | 200 mismo documento actualizado; 503 si continúa pendiente |

Escenarios de prueba: `aceptado` por defecto, `rechazado` y `no_disponible`. El escenario lo elige el usuario únicamente para demostrar el comportamiento del simulador. No es una respuesta de SUNAT ni un permiso de edición libre de estado.

## Parámetros y datos intercambiados

```http
POST /php/comprobantes.php
Content-Type: application/json
Cookie: PHPSESSID=<sesión autenticada>
X-CSRF-Token: <token de sesion.php posterior al login>
```

```json
{"operacion":"generar","idCita":6,"escenario":"aceptado"}
```

Respuesta ilustrativa HTTP 201 (identificadores e importes dependen de la cita):

```json
{"success":true,"simulado":true,"proveedor":"SIMULADOR_LOCAL_SUNAT","message":"Documento de prueba aceptado por el simulador local. Sin validez tributaria ni cobro.","datos":{"idComprobante":1,"codigo":"DEMO-000001","idCita":6,"tipo":"COMPROBANTE_DE_PRUEBA","cliente":"Paciente Demo","concepto":"Consulta de prueba - Cardiologia","moneda":"PEN","montoTotal":150,"modalidad":"PARTICULAR_DEMO","estado":"ACEPTADO_SIMULADO","intentos":1,"fechaEmision":"2026-11-18 12:00:00","fechaActualizacion":"2026-11-18 12:00:00","zonaHoraria":"America/Lima","simulado":true,"validezTributaria":false,"pagoRealizado":false}}
```

No se ingresa monto en el formulario. La tarifa particular ficticia proviene de la especialidad de la cita: Medicina General S/ 100, Cardiología S/ 150 y Pediatría S/ 120. Se almacena en céntimos enteros. El copago de S02 no se aplica en este incremento: falta guardar e integrar la modalidad de atención. El nombre del paciente y concepto se conservan como instantánea en el documento.

## Validaciones y seguridad

Sesión autenticada y perfil paciente; propiedad de la cita/documento comprobada en el backend; CSRF en POST; consultas preparadas; importes calculados por servidor. Se rechazan campos como montoTotal, copago, paciente_id y estado. Se valida operación, escenario e identificadores enteros positivos. Una especialidad sin tarifa configurada devuelve 422.

El permiso implementado es una **demostración de autoservicio de documentos propios**, no el rol Caja/Facturación previsto en APF2. Se debe implementar ese rol para reproducir la emisión institucional. Los registros de otras cuentas se ocultan mediante 404. La interfaz presenta texto con textContent y no solicita DNI/RUC para este documento académico.

**HTTPS sigue pendiente:** el servidor usa HTTP local en 127.0.0.1:8085. No hay OAuth/JWT, auditoría completa, firma ni credenciales SUNAT. Los valores root sin contraseña siguen siendo exclusivos del XAMPP local.

## Persistencia, duplicados y estados

La base impone UNIQUE(cita_id); además, la generación bloquea la cita mediante SELECT FOR UPDATE dentro de una transacción. Repetir la generación devuelve 409 con el documento ya existente. El reenvío bloquea y actualiza ese registro; conserva código, importe y fecha inicial e incrementa intentos. Un documento aceptado no admite reenvío. No se generan registros nuevos en la operación de reenvío.

Estados: ACEPTADO_SIMULADO, RECHAZADO_SIMULADO y PENDIENTE_SIMULADO. La indisponibilidad guarda primero el documento pendiente y después devuelve 503 con sus datos. Por tanto, un 503 no implica ausencia de documento: el consumidor consulta y reenvía el existente. No hay reintentos automáticos. Un rechazo simulado devuelve 201 al crearse el documento o 200 al reenviarlo: success=true expresa procesamiento de la operación, no aceptación; revisar datos.estado. El reenvío de un rechazo con escenario aceptado demuestra recuperación, pero no modela corrección tributaria real.

## Manejo de errores

| HTTP | Código | Tratamiento |
|---|---|---|
| 400 | IDENTIFICADOR_INVALIDO / COMPROBANTE_INVALIDO | Corregir identificador |
| 400 | ESCENARIO_INVALIDO / OPERACION_INVALIDA / CAMPO_NO_PERMITIDO | Corregir solicitud |
| 401/403 | SESION_REQUERIDA / ACCESO_DENEGADO / CSRF_INVALIDO | Autenticar o renovar token |
| 404 | CITA_NO_ENCONTRADA / COMPROBANTE_NO_ENCONTRADO | Revisar recurso propio |
| 409 | COMPROBANTE_YA_GENERADO | Consultar o reenviar documento devuelto |
| 409 | COMPROBANTE_YA_ACEPTADO | No reenviar; consultar estado |
| 422 | TARIFA_NO_CONFIGURADA | Configurar tarifa ficticia antes de generar |
| 503 | PROVEEDOR_SIMULADO_NO_DISPONIBLE | Documento persistido pendiente; consultar y reenviar mismo ID |
| 500 | ERROR_INTERNO | Consultar antes de repetir si el resultado no está confirmado |

## Evidencias y límites

`python tests/facturacion.py` ejecutó 30 comprobaciones HTTP reales, incluidos generación, persistencia, duplicados, aislamiento entre cuentas, rechazo, pendiente, reenvío y cálculo protegido de importes. Evidencia: `tests/S05-ultima-ejecucion.json`. Las cuentas y documentos temporales de la prueba se eliminan al terminar. También pasaron las 25 pruebas de regresión S04.

La colección `docs/S05.postman_collection.json` permite repetir un flujo con sesión, cita, generación pendiente, conflicto y reenvío. No se ha ejecutado Postman ni tomado una captura nueva de interfaz en este incremento. El usuario puede probar el panel S05 y desplegar su respuesta JSON.

Instalación existente: importar `database/s05.sql`; instalación nueva: `database/clinica.sql` ya incluye la tabla. Pendiente: orquestación de servicios, modalidad/copago integrado, roles institucionales, HTTPS y documentación de los apartados de procesos/BPM/B2B. Tener cinco servicios de demostración no significa que toda la rúbrica ni el diseño APF2 estén completos.
