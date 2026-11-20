> Versión inicial: alcance actualizado en `implementacion-integrada.md`. Consultar esa guía para proveedores HTTP, pólizas persistentes, flujo integrado, copagos y conexión OSRM verificada.

# S01 — Validación de identidad (proveedor simulado)

## Nombre, identificador y ubicación

**S01 — Validación de identidad.** Servicio de integración ubicado en la capa de servicios de la arquitectura propuesta. En esta implementación su adaptador consulta un catálogo ficticio incluido en PHP; no consulta RENIEC. La pantalla `citas.html` pertenece a clientes y canales. MariaDB almacena únicamente el control de frecuencia de consultas por cuenta; no almacena resultados de identidad de este módulo.

## Tipo, formato y responsabilidad

API HTTP de estilo REST, mediante **POST `/php/identidad.php`**, con cuerpo y respuesta JSON. Enfoque code-first en este incremento. PHP implementa el servicio y JavaScript lo consume con fetch. El catálogo ficticio representa al proveedor externo para probar la interacción entre aplicaciones. No hay ESB ni conexión institucional habilitada.

Su responsabilidad es comprobar formato de DNI y consultar la existencia y el estado de un registro del catálogo de demostración. Una respuesta correcta **no acredita identidad real** ni verifica que el usuario sea el titular del documento. No se modifica el perfil del paciente con los datos inventados.

## Operación y parámetros

`consultarIdentidadSimulada`: requiere sesión de paciente, cabecera `X-CSRF-Token` y cuerpo JSON.

| Campo | Tipo | Regla |
|---|---|---|
| dni | string, obligatorio | Exactamente ocho dígitos ASCII; se conservan ceros iniciales |
| escenario | string, opcional | `normal` por defecto, o `no_disponible` para provocar un 503 simulado |

POST evita incluir el documento en la URL de consulta; el método se utiliza como operación de verificación del prototipo. No crea una identidad ni una validación persistente.

```http
POST /php/identidad.php
Content-Type: application/json
X-CSRF-Token: <token de sesion.php>
Cookie: PHPSESSID=<sesión autenticada>
```

```json
{"dni":"00000001","escenario":"normal"}
```

Respuesta HTTP 200:

```json
{"success":true,"simulado":true,"proveedor":"CATALOGO_FICTICIO","message":"Documento encontrado en el catalogo ficticio. No acredita identidad real.","datos":{"dni":"00000001","nombres":"Ana Maria","apellidoPaterno":"Torres","apellidoMaterno":"Demo","estado":"ACTIVO"}}
```

## Datos para la demostración

| DNI ficticio | Resultado |
|---|---|
| 00000001 | Ana Maria Torres Demo, activo, 200 |
| 00000002 | Luis Alberto Ramos Demo, activo, 200 |
| 00000003 | Documento ficticio inactivo, 422 |
| 00000099 | No existe en el catálogo, 404 |

Los identificadores y nombres fueron inventados para pruebas; no se obtuvieron de una fuente de datos personales. La simulación no permite inferir la existencia o estado real de ningún documento.

## Validaciones y errores

| HTTP | Código | Causa y tratamiento |
|---|---|---|
| 400 | FORMATO_DNI_INVALIDO | Tipo incorrecto o longitud/contenido inválido. Corregir entrada, sin reintento automático. |
| 400 | ESCENARIO_INVALIDO | Escenario no admitido. Corregir entrada. |
| 401 | SESION_REQUERIDA | Iniciar sesión. |
| 403 | CSRF_INVALIDO / ACCESO_DENEGADO | Actualizar token o revisar autorización. |
| 404 | IDENTIDAD_FICTICIA_NO_ENCONTRADA | No hay registro de prueba. No equivale a una respuesta de RENIEC. |
| 422 | DOCUMENTO_FICTICIO_INACTIVO | Estado inactivo en el catálogo inventado. |
| 429 | LIMITE_CONSULTAS | Máximo 10 consultas por minuto por cuenta. Esperar el tiempo de Retry-After. |
| 503 | PROVEEDOR_SIMULADO_NO_DISPONIBLE | Falla provocada voluntariamente en el simulador. No describe una caída de RENIEC. |
| 405 | METODO_NO_PERMITIDO | Usar POST. |
| 500 | ERROR_INTERNO | Falla local inesperada; detalle solo en el log del servidor. |

Ejemplo HTTP 404:

```json
{"success":false,"simulado":true,"proveedor":"CATALOGO_FICTICIO","error":{"codigo":"IDENTIDAD_FICTICIA_NO_ENCONTRADA","mensaje":"El DNI no existe en el catalogo ficticio."},"message":"El DNI no existe en el catalogo ficticio."}
```

El control de frecuencia usa una ventana fija por minuto y cuenta solicitudes válidas de consulta, incluso las que terminan en 404, 422 o 503. Las entradas con formato inválido se rechazan antes de consultar. El límite se conserva entre sesiones de la misma cuenta mediante `identidad_limites`. No equivale a un límite global por IP ni previene la creación de múltiples cuentas.

## Seguridad aplicada

Autenticación por sesión PHP; autorización de perfil paciente; CSRF; cookie HttpOnly/SameSite; consultas SQL preparadas y bloqueo transaccional para el contador; respuesta y elementos de pantalla identificados como simulados. El cliente muestra texto con textContent. Los errores técnicos no incluyen SQL en la respuesta. No se escriben DNI consultados en la tabla de frecuencia.

**HTTPS sigue pendiente:** este entorno funciona por HTTP en 127.0.0.1. No presentar HTTPS, OAuth/JWT ni conectividad real con RENIEC como implementados. No hay credenciales de RENIEC en el proyecto. Para integrar un proveedor autorizado en el futuro, se debe sustituir el catálogo por un adaptador de backend con contrato, credenciales y permisos válidos; no basta con cambiar un texto de la pantalla.

## Consumo y relación con S04

La pantalla autenticada de citas incluye un formulario exclusivo de S01, ejemplos y la respuesta JSON. Este incremento demuestra S01 y S04 de manera independiente; todavía no se ha implementado la orquestación que condicione una reserva a la validación de identidad y cobertura. S04 continúa identificando al paciente por la sesión real, sin asociarle un DNI ficticio.

## Evidencia de pruebas

Ejecutar `python tests/identidad.py` con el servidor PHP y MySQL activos. Los resultados reales se guardan en `tests/S01-ultima-ejecucion.json`. Se comprobaron respuestas correctas, entradas inválidas, documentos inexistentes/inactivos, falla simulada, autorización, CSRF, límite de frecuencia entre sesiones y ausencia de modificación del DNI del paciente. Las cuentas temporales se eliminan al terminar. Se ejecutó también la regresión de S04 (25 pruebas HTTP aprobadas).

La colección `docs/S01.postman_collection.json` permite repetir el flujo en Postman. No se ha ejecutado Postman ni tomado una captura nueva de la interfaz en este incremento; las evidencias entregadas son resultados HTTP de las pruebas automatizadas.
