<?php
// Ejecutar desde CLI. Guarda secretos y datos fuera del directorio web.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
$path=dirname(__DIR__,2).'/proveedores-config.json';
if (!is_file($path)) file_put_contents($path,json_encode(['token'=>bin2hex(random_bytes(32))]));
echo "Configuracion de proveedores preparada.\n";
