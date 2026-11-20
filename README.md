# Clinica Internacional — servicios SOA

Portal academico en PHP, HTML, CSS y JavaScript. MariaDB almacena usuarios, citas y comprobantes. Apache Synapse 3.0.2 actua como ESB real, en un proceso independiente.

## Preparar un equipo nuevo

1. Instalar XAMPP. Iniciar MariaDB en el puerto 3307, segun php/conexion.php. Los valores root sin clave corresponden exclusivamente al laboratorio local.
2. Importar database/clinica.sql y despues database/s01.sql, database/s05.sql y database/integracion.sql. Revisar cada script antes de aplicarlo a una base existente. No se incluyen cuentas personales ni una copia de la base en uso.
3. Ejecutar ./configurar-esb.ps1 desde PowerShell. Descarga Apache Synapse y Java compatible y genera la credencial interna fuera del repositorio.
4. Ejecutar ./iniciar-esb.ps1. Prepara proveedores academicos y los servicios internos en 8090 y 8086; el ESB escucha en 8280, solo en este equipo.
5. Configurar Apache para servir esta carpeta en https://localhost:8443 con un certificado local propio generado con mkcert. El certificado de otro integrante no debe copiarse. La configuracion HTTPS de Apache no se incluye en el repositorio. Para preparar este paso se requiere configurar el VirtualHost, DocumentRoot, SSL y las restricciones de carpetas privadas.
6. Abrir el portal, registrar una cuenta e iniciar sesion. En cada inicio posterior activar Apache y MariaDB y ejecutar ./iniciar-esb.ps1.

## Proveedores

- S01 consulta Factiliza por HTTPS con el token personal configurado mediante ./configurar-factiliza.ps1. Cada integrante debe usar su propia cuenta y revisar las condiciones y limites del proveedor. Nunca poner el token en JavaScript, GitHub o capturas. Tambien existe un proveedor academico de prueba.
- S02 consulta seguros academicos con datos sinteticos, no aseguradoras reales.
- S03 combina sedes de demostracion, mapas OpenStreetMap/Leaflet y rutas OSRM. Los servicios publicos tienen politicas y disponibilidad propias; no incluyen trafico en tiempo real.
- S04 registra citas reales en la base local, valida disponibilidad e impide duplicar el turno del medico.
- S05 registra documentos academicos y se comunica mediante SOAP/XML con un receptor propio. No envia a SUNAT, no tiene validez tributaria ni realiza cobros.

El portal utiliza sesiones PHP, autorizacion, CSRF y HTTPS local. El puente php/esb.php comunica el navegador con Synapse; no sustituye al ESB. Las API PHP rechazan accesos directos sin la credencial interna.

## Configuracion privada

factiliza-config.json, esb-config.json, proveedores-config.json y las bases SQLite de los proveedores se guardan en la carpeta superior a este proyecto, fuera de la raiz web. No deben publicarse. .gitignore tambien evita incluirlos accidentalmente si se copian dentro del repositorio. Las claves y certificados locales permanecen fuera del proyecto.

## Pruebas y documentacion

Los scripts en tests/ corresponden a diferentes etapas del desarrollo. Algunos usan rutas o dependencias locales que deben adaptarse en otro equipo. Sus resultados JSON, sesiones y registros no se publican. Las pruebas con datos sinteticos no requieren consultar Factiliza. No se utilizo Postman en la demostracion actual.

Los documentos en docs/ conservan detalles e incrementos anteriores y pueden describir rutas previas al ESB. Para el estado vigente seguir este README y el codigo actual. No se implementaron UDDI, JWT, cancelacion ni reprogramacion de citas ni un motor BPM independiente.

Leaflet conserva su licencia en vendor/leaflet/LICENSE. Mantener la atribucion a OpenStreetMap.
