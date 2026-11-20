> Versión inicial: alcance actualizado en `implementacion-integrada.md`. Consultar esa guía para proveedores HTTP, pólizas persistentes, flujo integrado, copagos y conexión OSRM verificada.

# S03 — Consulta de sedes y ubicación

## Identificación y clasificación en capas

**S03 — Geolocalización de sedes**, implementado en este incremento como consulta de ubicaciones y cálculo de distancia aproximada. Servicio de consulta/utilidad ubicado en la capa de servicios. Su consumidor es `citas.html` y `js/citas.js`, en clientes y canales. El proveedor implementado es un catálogo local de sedes ficticias (`php/sedes_catalogo.php`), no Google Maps Platform. El nombre de S03 mantiene trazabilidad con el APF2, pero la API de rutas y tiempos estimados propuesta allí no está implementada.

## Tipo, formato y responsabilidad

API HTTP de estilo REST, con JSON. PHP 8.2 implementa las operaciones y JavaScript/fetch las consume. Enfoque code-first y comunicación directa; sin ESB. Su responsabilidad es devolver sedes habilitadas, su dirección descriptiva, horario y coordenadas. Puede calcular la distancia geodésica aproximada desde un punto ingresado voluntariamente. No solicita la geolocalización del navegador y no asigna una sede a las citas existentes.

El catálogo y los puntos son inventados para una demostración académica: no representan establecimientos reales de Clínica Internacional. La base de datos solo se usa para la autenticación común, sin nuevas tablas ni almacenamiento del origen.

## Operaciones y parámetros de entrada

| Operación | Método y ruta | Entrada | Salida |
|---|---|---|---|
| consultarSedes | GET `/php/sedes.php` | Cookie de sesión de paciente | 200, arreglo de sedes habilitadas |
| consultarUbicacion | GET `/php/sedes.php?id=1` | id entero positivo | 200, objeto de sede y mapaUrl |
| calcularDistancia | GET `/php/sedes.php?id=1&latitud=-12.1211&longitud=-77.0297` | id y ambos componentes del origen | 200, detalle con distanciaKm y tipoDistancia |

Los campos opcionales de coordenadas se deben enviar juntos y únicamente al consultar una sede específica. Latitud: -90 a 90; longitud: -180 a 180. Se rechazan valores no numéricos, arreglos y valores no finitos. Solo se admiten GET; no hay creación ni actualización de sedes en este incremento.

## Datos intercambiados y respuesta generada

Solicitud:

```http
GET /php/sedes.php?id=1
Cookie: PHPSESSID=<sesión autenticada>
```

Respuesta HTTP 200 de ejemplo:

```json
{"success":true,"datosDemostracion":true,"proveedor":"CATALOGO_LOCAL_SEDES","message":"Ubicacion ficticia. La distancia opcional es en linea recta, no una ruta ni un tiempo de viaje.","datos":{"id":1,"nombre":"Sede Centro Demo","direccion":"Punto de referencia Centro, Lima (ubicacion de prueba)","latitud":-12.0464,"longitud":-77.0428,"horario":"Lunes a sabado, 08:00 a 17:00 (demostracion)","habilitada":true,"mapaUrl":"https://www.google.com/maps/search/?api=1&query=-12.0464%2C-77.0428"}}
```

Si se proporciona origen, se agregan `distanciaKm` (número con hasta tres decimales) y `tipoDistancia: LINEA_RECTA_APROXIMADA`. Se utiliza Haversine con radio terrestre de 6371 km. El punto idéntico a la sede produce cero; el punto sur del ejemplo está entre 8 y 9 km de la sede centro. No se devuelven ruta, duración ni información de tránsito.

## Validaciones y manejo de errores

| HTTP | Código | Significado y tratamiento |
|---|---|---|
| 400 | SEDE_INVALIDA | Identificador no entero positivo; corregir entrada |
| 400 | SEDE_REQUERIDA | Se enviaron coordenadas sin seleccionar sede |
| 400 | COORDENADAS_INVALIDAS | Falta una coordenada, formato inválido o fuera de rango |
| 401 | SESION_REQUERIDA | Iniciar sesión |
| 403 | ACCESO_DENEGADO | Perfil paciente no habilitado |
| 404 | SEDE_NO_ENCONTRADA | Sede inexistente o inactiva; elegir otra |
| 405 | METODO_NO_PERMITIDO | Usar GET |
| 500 | ERROR_INTERNO | Falla local; detalle técnico en el registro del servidor |

Ejemplo HTTP 404:

```json
{"success":false,"datosDemostracion":true,"proveedor":"CATALOGO_LOCAL_SEDES","error":{"codigo":"SEDE_NO_ENCONTRADA","mensaje":"La sede no existe o no esta habilitada."},"message":"La sede no existe o no esta habilitada."}
```

Pruebas de catálogo: IDs 1, 2 y 3 habilitados; ID 4 inactivo y 999 inexistente. La sede inactiva no aparece en el listado ni permite consultar sus detalles.

## Seguridad y uso de mapas

Aplicado: autenticación mediante sesión PHP, autorización de perfil paciente, cookies HttpOnly/SameSite y salida de texto con textContent. Las operaciones son consultas GET sin modificación de estado de negocio; no requieren token CSRF. El origen no se persiste en tablas, aunque las coordenadas pueden aparecer en la URL y en registros HTTP: para la demostración usar puntos ficticios.

El backend genera un enlace HTTPS a Google Maps con las coordenadas de la sede; el frontend restringe su origen a https://www.google.com y abre una pestaña con noopener/noreferrer. Solo al pulsarlo se navega al mapa externo. No se envía el origen del usuario en ese enlace. **No se consume una API de Google Maps, no se utiliza clave de API y no hay una respuesta de rutas de Google en el servicio.** El servicio funciona sin solicitar mapas externos durante la carga.

HTTPS del backend sigue pendiente: se ejecuta en HTTP local (127.0.0.1:8085). Un enlace HTTPS externo no demuestra HTTPS en la aplicación. El servicio tampoco implementa auditoría ni límite propio de consultas.

## Evidencias y alcance pendiente

Ejecutar `python tests/sedes.py`. Pasaron 24 comprobaciones HTTP reales: autenticación, listado, detalle, cálculo de distancia, exclusión de sedes inactivas, datos inválidos, método y cierre de sesión. Resultados: `tests/S03-ultima-ejecucion.json`. Se ejecutaron además las 25 pruebas de regresión S04 con resultado aprobado.

La colección `docs/S03.postman_collection.json` permite repetir las consultas; no se ha ejecutado Postman ni realizado una captura visual nueva en este incremento. El consumidor está integrado en la pantalla, pendiente de la comprobación manual del usuario.

Falta vincular la sede a la disponibilidad médica y a la reserva en la orquestación; reemplazar puntos ficticios por información verificada si el alcance lo requiere; configurar HTTPS. Rutas viales y tiempos de llegada quedan fuera de esta implementación y requerirían un proveedor de enrutamiento.
