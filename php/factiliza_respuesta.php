<?php
// Normaliza variantes del contrato sin convertir un rechazo en un resultado exitoso.
function interpretarFactiliza(int $http,string $raw,string $dni): array {
    $v=json_decode($raw,true);
    $estado=is_array($v) ? ($v['status'] ?? null) : null;
    $estadoNumerico=is_int($estado) || (is_string($estado) && ctype_digit($estado));
    $reportado=$estadoNumerico ? (int)$estado : null;
    $rechazo=is_array($v) && array_key_exists('success',$v) && $v['success']!==true;
    if ($http===401 || $http===403 || in_array($reportado,[401,403],true)) return ['error'=>'CREDENCIAL','http'=>$http,'estado'=>$reportado];
    if ($http===429 || $reportado===429) return ['error'=>'LIMITE','http'=>$http,'estado'=>$reportado];
    if ($http===404 || $reportado===404) return ['error'=>'NO_ENCONTRADO','http'=>$http,'estado'=>$reportado];
    if ($http!==200 || !is_array($v) || $rechazo || ($reportado!==null && $reportado!==200)) return ['error'=>'RECHAZADA','http'=>$http,'estado'=>$reportado];
    // Algunas versiones omiten success y publican status=200.
    if (!array_key_exists('success',$v) && $reportado!==200) return ['error'=>'CONTRATO','http'=>$http,'estado'=>$reportado];
    $persona=$v['data'] ?? null;
    if (!is_array($persona)) return ['error'=>'CONTRATO','http'=>$http,'estado'=>$reportado];
    foreach (['numero','nombres','apellido_paterno','apellido_materno'] as $campo) {
        if (!isset($persona[$campo]) || !is_string($persona[$campo]) || ($campo!=='apellido_materno' && trim($persona[$campo])==='')) return ['error'=>'CONTRATO','http'=>$http,'estado'=>$reportado];
    }
    if ($persona['numero']!==$dni) return ['error'=>'DOCUMENTO_INCONSISTENTE','http'=>$http,'estado'=>$reportado];
    // Solo se conservan los atributos necesarios; se descartan domicilios y otros datos.
    return ['datos'=>['dni'=>$dni,'nombres'=>$persona['nombres'],'apellidoPaterno'=>$persona['apellido_paterno'],'apellidoMaterno'=>$persona['apellido_materno'],'estado'=>'DATOS_CONSULTADOS']];
}
