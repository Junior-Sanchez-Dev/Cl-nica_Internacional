<?php
// Servidor interno de servicios. Nunca sirve archivos arbitrarios del repositorio.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$allowed=['sesion','procesar_login','procesar_registro','cerrar_sesion','estado_integraciones',
    'identidad','seguros','sedes','doctores','disponibilidad','admision','reservar_cita','obtener_citas','comprobantes'];
if(!is_string($path)||!preg_match('~^/php/([a-z_]+)\.php$~D',$path,$match)||!in_array($match[1],$allowed,true)) {
    http_response_code(404);header('Content-Type: application/json');echo '{"success":false,"message":"Recurso interno no disponible."}';return true;
}
require dirname(__DIR__).$path;
return true;
