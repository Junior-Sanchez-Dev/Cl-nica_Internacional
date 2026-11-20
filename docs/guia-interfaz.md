# Nueva interfaz de atención

La aplicación conserva la URL `http://127.0.0.1:8085/citas.html`. Actualizar con Ctrl+F5 para renovar HTML, CSS y JavaScript.

## Recorrido del paciente

1. **Paciente:** ingresar documento y consultar. Se muestra una tarjeta con nombre y documento. Factiliza se utiliza de forma predeterminada cuando hay token configurado; no se consulta automáticamente al abrir la página.
2. **Cobertura:** elegir especialidad y consultar. El documento se transfiere desde el paso anterior, sin volver a ingresarlo. La pantalla muestra tarjetas de modalidad y su importe.
3. **Sede y horario:** seleccionar sede, profesional y fecha. Elegir un horario de la grilla y escribir el motivo. El mapa y las rutas se abren opcionalmente; no se solicitan teselas al cargar la página.
4. **Confirmación:** revisar el resumen y confirmar. Tras registrar la cita, generar el comprobante académico si se desea.

La barra lateral permite consultar citas y comprobantes. JSON, destino de servicios, estado HTTP, proveedor de identidad y herramientas de fallas se concentran en **Evidencias técnicas**. El resumen permanece visible durante los pasos.

## Alcance y datos de exposición

Se conserva una etiqueta discreta “Entorno académico de pruebas” y un diálogo que explica qué proveedores son externos y cuáles académicos. Los documentos siguen identificados como académicos sin validez tributaria. Se eliminaron las repeticiones técnicas del recorrido; no se alteró la procedencia real de los servicios.

Para una exposición sin datos personales ni consumo de cuota: Evidencias técnicas → Usar caso académico de cobertura integral. El sistema introduce el identificador sintético `81000021`. También existen `81000022` (póliza vencida), `81000023` (documento inactivo), `81000024` (cobertura solo Medicina General) y `81000025` (sin póliza). Los nombres e identificadores son construidos para el proyecto y no deben presentarse como DNI/identidades verificados oficialmente ni consultarse en Factiliza. Los identificadores anteriores se mantienen para compatibilidad de pruebas.

## Verificación realizada

- Las regresiones HTTP de S01, S02, S04 y S05 y el flujo integrado pasaron tras el rediseño.
- Se agregó una prueba de DOM en `tests/interfaz.cjs` que utiliza HTTP real contra los servidores locales, crea una cuenta temporal y recorre consulta → cobertura → horarios → reserva → comprobante → evidencias → nueva reserva. Se ejecutó en tres casos: cobertura de S/ 30, póliza vencida con tarifa particular S/ 100 y ausencia de póliza con tarifa particular S/ 100. Los registros temporales de la clínica se eliminan al finalizar; las pruebas no llaman a Factiliza.
- La prueba DOM no es una revisión visual de navegador ni verifica carga de teselas. La automatización visual se detuvo porque la herramienta no pudo verificar la URL de la pestaña nueva. Se requiere revisión manual del aspecto en escritorio y móvil.

Para reproducir la prueba DOM desde la raíz del repositorio:

```powershell
npm install --prefix ../ui-check --no-audit --no-fund jsdom
node tests/interfaz.cjs
$env:UI_DNI='81000022'; node tests/interfaz.cjs
$env:UI_DNI='81000025'; node tests/interfaz.cjs
Remove-Item Env:UI_DNI
```

Los servicios, sus validaciones, sesión, controles de propiedad y restricciones del proceso permanecen en el backend. El cambio principal es de navegación y presentación; no configura HTTPS ni sustituye los proveedores pendientes del alcance original.
