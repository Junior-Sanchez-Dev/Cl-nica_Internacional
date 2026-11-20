<?php
declare(strict_types=1);
date_default_timezone_set('America/Lima');
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require __DIR__.'/esb_guard.php';
session_set_cookie_params(['httponly'=>true, 'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite'=>'Lax', 'path'=>'/']);
session_start();
function responder(int $status, array $datos): never {
    http_response_code($status);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fallo(int $status, string $codigo, string $mensaje): never {
    responder($status, ['success'=>false, 'error'=>['codigo'=>$codigo, 'mensaje'=>$mensaje], 'message'=>$mensaje]);
}
set_exception_handler(function(Throwable $e): void {
    error_log('Clinica API: '.$e->getMessage());
    fallo(500, 'ERROR_INTERNO', 'No se pudo procesar la solicitud. Intente nuevamente.');
});
function metodo(string $esperado): void {
    if ($_SERVER['REQUEST_METHOD'] !== $esperado) {
        header('Allow: '.$esperado);
        fallo(405, 'METODO_NO_PERMITIDO', 'Metodo no permitido.');
    }
}
function entrada(): array {
    if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        try { $valor = json_decode(file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR); }
        catch (JsonException $e) { fallo(400, 'JSON_INVALIDO', 'El cuerpo JSON no es valido.'); }
        if (!is_array($valor) || array_is_list($valor)) fallo(400, 'JSON_INVALIDO', 'Se requiere un objeto JSON.');
        return $valor;
    }
    return $_POST;
}
function texto(array $datos, string $campo): string {
    return isset($datos[$campo]) && is_string($datos[$campo]) ? trim($datos[$campo]) : '';
}
function csrf(): void {
    $recibido = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $recibido)) {
        fallo(403, 'CSRF_INVALIDO', 'Actualice la pagina e intente nuevamente.');
    }
}
function paciente(): int {
    if (empty($_SESSION['user_id'])) fallo(401, 'SESION_REQUERIDA', 'Inicie sesion para continuar.');
    if (($_SESSION['user_rol'] ?? '') !== 'paciente' || empty($_SESSION['paciente_id'])) {
        fallo(403, 'ACCESO_DENEGADO', 'La cuenta no tiene un perfil de paciente habilitado.');
    }
    return (int)$_SESSION['paciente_id'];
}
function fechaValida(string $valor): DateTimeImmutable {
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    if (!$fecha || $fecha->format('Y-m-d') !== $valor) fallo(400, 'FECHA_INVALIDA', 'Use una fecha valida con formato AAAA-MM-DD.');
    $hoy = new DateTimeImmutable('today');
    if ($fecha < $hoy || $fecha > $hoy->modify('+90 days')) fallo(400, 'FECHA_FUERA_DE_RANGO', 'Seleccione una fecha entre hoy y los proximos 90 dias.');
    return $fecha;
}
function horarios(): array {
    $lista=[];
    for ($h=8; $h<17; $h++) foreach ([0,30] as $m) $lista[]=sprintf('%02d:%02d', $h, $m);
    return $lista;
}
require __DIR__.'/conexion.php';
