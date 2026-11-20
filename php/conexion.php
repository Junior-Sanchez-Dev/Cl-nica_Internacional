<?php
// Valores predeterminados exclusivos para XAMPP local. Configurar variables en otros entornos.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conexion = new mysqli(
    getenv('DB_HOST') ?: '127.0.0.1',
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASSWORD') ?: '',
    getenv('DB_NAME') ?: 'clinica_labendecida',
    (int)(getenv('DB_PORT') ?: 3307)
);
$conexion->set_charset('utf8mb4');
return $conexion;
