> Versión inicial: alcance actualizado en `implementacion-integrada.md`. Consultar esa guía para proveedores HTTP, pólizas persistentes, flujo integrado, copagos y conexión OSRM verificada.

# S02 — Acreditación de seguros (proveedor simulado)

## Identificación, clasificación y capas

**S02 — Acreditación de Seguros de Salud.** Servicio de integración y reglas de negocio en la capa de servicios de la arquitectura propuesta. La página `citas.html` es su consumidor en clientes y canales. `seguros_catalogo.php` representa mediante datos inventados a una aseguradora externa. No se conecta a EPS, SIS ni compañías reales. MariaDB se utiliza para autenticar la cuenta, pero la consulta de cobertura no crea registros de pagos, afiliaciones ni reservas.

## Propuesta técnica y responsabilidad

API HTTP de estilo REST, operación **POST `/php/seguros.php`**, con datos JSON. Implementación en PHP 8.2 y consumo con JavaScript/fetch. Enfoque code-first en este incremento. Comunicación directa, sin ESB ni orquestación todavía.

La responsabilidad de la operación `consultarCobertura` es consultar un catálogo ficticio para determinar vigencia, cobertura de una prestación y copago fijo en soles. No acredita afiliación real ni cobra dinero. Las tarifas se calculan en el backend a partir de valores enteros en céntimos; el consumidor no define copagos ni importes.

## Operación y entrada

Requiere sesión de paciente y token CSRF obtenido de GET `/php/sesion.php` después del login.

| Parámetro | Tipo y validación |
|---|---|
| dni | Texto obligatorio, exactamente ocho dígitos ASCII |
| aseguradora | Texto obligatorio: `ASEGURADORA_DEMO` |
| tipoAtencion | `medicina_general`, `cardiologia` o `pediatria` |
| escenario | Opcional: `normal` o `no_disponible` |

```http
POST /php/seguros.php
Content-Type: application/json
X-CSRF-Token: <token de la sesión>
Cookie: PHPSESSID=<sesión autenticada>
```

```json
{"dni":"00000001","aseguradora":"ASEGURADORA_DEMO","tipoAtencion":"medicina_general","escenario":"normal"}
```

## Formato y respuesta generada

HTTP 200:

```json
{"success":true,"simulado":true,"proveedor":"ASEGURADORA_DEMO","message":"Consulta del catalogo de seguros ficticio completada. No acredita cobertura real.","datos":{"dni":"00000001","aseguradora":"ASEGURADORA_DEMO","plan":"Plan Integral Demo","tipoAtencion":"medicina_general","vigente":true,"cobertura":true,"resultado":"COBERTURA_APROBADA","moneda":"PEN","tarifaParticular":100,"copago":30,"importeAseguradora":70,"ofrecerParticular":false}}
```

Los valores monetarios son números expresados en soles, no cadenas formateadas. Se cumple tarifa = copago + importeAseguradora cuando hay cobertura. Cuando no hay cobertura, copago e importeAseguradora son null; la tarifa particular se informa por separado. Un copago null no significa atención gratuita.

**`success: true` significa que la consulta se realizó correctamente, no que el seguro cubra la atención.** El consumidor debe revisar `datos.cobertura` y `datos.resultado`. Seguro vencido o atención no cubierta son resultados de negocio con HTTP 200; una falla de proveedor es HTTP 503 sin decisión de cobertura.

## Casos y datos de demostración

| DNI ficticio | Atención | Resultado | Copago / tarifa particular |
|---|---|---|---|
| 00000001 | Medicina General | Cobertura aprobada | Copago S/ 30; tarifa S/ 100 |
| 00000001 | Cardiología | Cobertura aprobada | Copago S/ 50; tarifa S/ 150 |
| 00000001 | Pediatría | Cobertura aprobada | Copago S/ 40; tarifa S/ 120 |
| 00000002 | Cualquiera admitida | Seguro vencido | Sin copago; ofrece tarifa particular |
| 00000004 | Medicina General | Plan básico cubierto | Copago S/ 20; tarifa S/ 100 |
| 00000004 | Cardiología o Pediatría | Atención no cubierta | Ofrece tarifa particular |
| 00000099 | Cualquiera admitida | Afiliación ficticia no encontrada | 404, sin decisión de cobertura |

La vigencia se representa como un estado fijo del caso de prueba, no se verifica contra fechas reales de una póliza. Las tarifas y prestaciones son ficticias. El DNI 00000004 no está en el catálogo S01: ambos módulos se prueban por separado; los catálogos deberán armonizarse al implementar la orquestación.

## Validaciones y manejo de errores

| HTTP | Código | Acción |
|---|---|---|
| 400 | FORMATO_DNI_INVALIDO | Corregir DNI; se rechazan números JSON y arreglos |
| 400 | ASEGURADORA_INVALIDA / ATENCION_INVALIDA / ESCENARIO_INVALIDO | Usar valores admitidos |
| 401 | SESION_REQUERIDA | Volver a iniciar sesión |
| 403 | CSRF_INVALIDO / ACCESO_DENEGADO | Actualizar token o revisar permisos |
| 404 | AFILIACION_FICTICIA_NO_ENCONTRADA | Revisar datos de prueba; no asumir resultado real |
| 405 | METODO_NO_PERMITIDO | Usar POST |
| 503 | ASEGURADORA_SIMULADA_NO_DISPONIBLE | Reintentar más adelante; no asumir cobertura aprobada ni denegada |
| 500 | ERROR_INTERNO | Informar la falla; detalle técnico solo en log local |

Ejemplo HTTP 503:

```json
{"success":false,"simulado":true,"proveedor":"ASEGURADORA_DEMO","error":{"codigo":"ASEGURADORA_SIMULADA_NO_DISPONIBLE","mensaje":"Falla simulada. No es posible determinar la cobertura; no se debe asumir que esta aprobada."},"message":"Falla simulada. No es posible determinar la cobertura; no se debe asumir que esta aprobada."}
```

## Seguridad y consumo

Aplicado: autenticación por sesión, autorización de perfil paciente, token CSRF, cookies HttpOnly/SameSite y presentación de datos mediante textContent. La identidad del usuario sigue siendo la de su sesión; consultar un DNI de prueba no modifica su perfil. No hay credenciales de aseguradoras ni API keys. El endpoint devuelve etiquetas `simulado` y `proveedor`.

**HTTPS sigue pendiente.** La demostración funciona por HTTP en 127.0.0.1:8085. No presenta autorización institucional, OAuth/JWT, controles de cobertura reales ni una política de seguridad productiva. S02 no tiene limitador de consultas propio en este incremento.

La pantalla ofrece aceptar o rechazar atención particular únicamente ante un resultado de póliza vencida o atención no cubierta. Esa elección es local e informativa: no se persiste, no ejecuta S04 ni genera un pago. Cambiar los datos del formulario invalida el resultado anterior. La solicitud del formulario tiene un límite de espera de ocho segundos y muestra un error si no obtiene respuesta.

## Evidencias y trabajo pendiente

`python tests/seguros.py` ejecutó 24 comprobaciones HTTP con resultado aprobado, entre ellas autenticación, CSRF, formatos, copagos de tres prestaciones, póliza vencida, prestación no cubierta, afiliación inexistente, falla simulada y rechazo de importes manipulados por el consumidor (el servidor conserva su tarifa). `tests/S02-ultima-ejecucion.json` contiene respuestas reales de esa ejecución. También pasaron las regresiones de S01 (26 comprobaciones) y S04 (25 pruebas HTTP).

La colección `docs/S02.postman_collection.json` permite repetir las consultas desde Postman; no constituye evidencia de que Postman haya sido ejecutado. La interfaz se integró en `citas.html`, pero en este incremento no se ha realizado una prueba visual automatizada ni una captura nueva del navegador.

Pendiente: orquestar S01 + S02 + S04 con identidad y prestación coherentes, persistir la decisión de modalidad, aplicar HTTPS, e implementar S03 y S05. Para un proveedor real se requiere sustituir el catálogo por un adaptador autorizado y verificar su contrato, sin afirmar que la simulación acredita cobertura.
