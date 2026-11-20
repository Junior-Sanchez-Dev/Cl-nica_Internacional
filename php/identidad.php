<?php
require __DIR__.'/api.php';
metodo('POST'); paciente(); csrf();
function errorIdentidad(int $status, string $codigo, string $mensaje): never {
    responder($status,['success'=>false,'simulado'=>true,'proveedor'=>'CATALOGO_FICTICIO',
        'error'=>['codigo'=>$codigo,'mensaje'=>$mensaje],'message'=>$mensaje]);
}
$d=entrada(); unset($_SESSION['identidad_validada'],$_SESSION['cobertura_validada']);
// Debe enviarse como texto para conservar ceros iniciales.
$dni=$d['dni'] ?? null;
if (!is_string($dni) || !preg_match('/^[0-9]{8}$/D', $dni)) {
    errorIdentidad(400,'FORMATO_DNI_INVALIDO','El DNI debe ser un texto de exactamente 8 digitos.');
}
$escenario=$d['escenario'] ?? 'normal';
if (!is_string($escenario) || !in_array($escenario,['normal','no_disponible'],true)) {
    errorIdentidad(400,'ESCENARIO_INVALIDO','Use normal o no_disponible en este simulador.');
}
$modo=$d['proveedor'] ?? 'academico';
if (!is_string($modo) || !in_array($modo,['academico','factiliza'],true)) fallo(400,'PROVEEDOR_INVALIDO','Seleccione academico o factiliza.');
// Limite por cuenta, compartido entre sesiones. Ventana fija de un minuto.
$usuario=(int)$_SESSION['user_id']; $minuto=intdiv(time(),60);
$conexion->begin_transaction();
try {
    $s=$conexion->prepare('INSERT INTO identidad_limites(usuario_id,minuto,consultas) VALUES (?,?,0) ON DUPLICATE KEY UPDATE consultas=consultas');
    $s->bind_param('ii',$usuario,$minuto); $s->execute();
    $s=$conexion->prepare('SELECT consultas FROM identidad_limites WHERE usuario_id=? AND minuto=? FOR UPDATE');
    $s->bind_param('ii',$usuario,$minuto); $s->execute(); $conteo=(int)$s->get_result()->fetch_assoc()['consultas'];
    if ($conteo>=10) {
        $conexion->rollback(); header('Retry-After: '.(60-time()%60));
        errorIdentidad(429,'LIMITE_CONSULTAS','Maximo 10 consultas por minuto por cuenta.');
    }
    $s=$conexion->prepare('UPDATE identidad_limites SET consultas=consultas+1 WHERE usuario_id=? AND minuto=?');
    $s->bind_param('ii',$usuario,$minuto); $s->execute();
    $s=$conexion->prepare('DELETE FROM identidad_limites WHERE usuario_id=? AND minuto<?');
    $anterior=$minuto-1440; $s->bind_param('ii',$usuario,$anterior); $s->execute();
    $conexion->commit();
} catch (Throwable $e) { $conexion->rollback(); throw $e; }
if ($modo==='factiliza') {
 if ($escenario!=='normal') fallo(400,'ESCENARIO_INVALIDO','Las fallas provocadas solo se permiten en el entorno academico.');
 require __DIR__.'/factiliza.php';$v=consultarFactiliza($dni);
 $_SESSION['identidad_validada']=['dni'=>$dni,'proveedor'=>'FACTILIZA','fecha'=>time(),'datosSinteticos'=>false];
 responder(200,['success'=>true,'simulado'=>false,'proveedor'=>'FACTILIZA','message'=>'Datos consultados mediante proveedor tercero. No es una conexion directa a RENIEC ni una verificacion de titularidad.','integracion'=>$v['integracion'],'datos'=>$v['datos']]);
}
if ($escenario==='no_disponible') {
    errorIdentidad(503,'PROVEEDOR_SIMULADO_NO_DISPONIBLE','Indisponibilidad simulada para probar el manejo de errores. No se ha consultado RENIEC.');
}
require __DIR__.'/proveedor_http.php';
try { $externo=proveedorHttp('/identidad','POST',json_encode(['dni'=>$dni])); }
catch (RuntimeException $e) { errorIdentidad(503,'PROVEEDOR_HTTP_NO_DISPONIBLE','El proveedor academico HTTP no responde. No se consulto RENIEC.'); }
if ($externo['status']===404) errorIdentidad(404,'IDENTIDAD_FICTICIA_NO_ENCONTRADA','El DNI no existe en el catalogo ficticio.');
if ($externo['status']!==200 || !isset($externo['json']['datos'])) errorIdentidad(502,'RESPUESTA_PROVEEDOR_INVALIDA','Respuesta no valida del proveedor academico.');
$persona=$externo['json']['datos'];
if ($persona['estado']!=='ACTIVO') errorIdentidad(422,'DOCUMENTO_FICTICIO_INACTIVO','El documento esta inactivo en el catalogo ficticio.');
$_SESSION['identidad_validada']=['dni'=>$dni,'proveedor'=>'ACADEMICO','fecha'=>time(),'datosSinteticos'=>true];
responder(200,['success'=>true,'simulado'=>true,'proveedor'=>'CATALOGO_FICTICIO',
    'message'=>'Documento encontrado en el catalogo ficticio. No acredita identidad real.',
    'integracion'=>$externo['integracion'],'datos'=>['dni'=>$dni]+$persona]);
