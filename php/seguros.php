<?php
require __DIR__.'/api.php';
metodo('POST'); paciente(); csrf();
function errorSeguro(int $status, string $codigo, string $mensaje): never {
    responder($status,['success'=>false,'simulado'=>true,'proveedor'=>'ASEGURADORA_DEMO',
        'error'=>['codigo'=>$codigo,'mensaje'=>$mensaje],'message'=>$mensaje]);
}
$d=entrada(); unset($_SESSION['cobertura_validada']); $dni=$d['dni'] ?? null;
if (!is_string($dni) || !preg_match('/^[0-9]{8}$/D',$dni)) errorSeguro(400,'FORMATO_DNI_INVALIDO','El DNI debe ser un texto de exactamente 8 digitos.');
require __DIR__.'/proveedor_http.php';
try { $externo=proveedorHttp('/seguros'); }
catch (RuntimeException $e) { errorSeguro(503,'PROVEEDOR_HTTP_NO_DISPONIBLE','El proveedor academico HTTP no responde. Cobertura sin determinar.'); }
if ($externo['status']!==200 || !isset($externo['json']['datos'])) errorSeguro(502,'RESPUESTA_PROVEEDOR_INVALIDA','Respuesta no valida del proveedor academico.');
$catalogo=$externo['json']['datos'];
$aseguradora=$d['aseguradora'] ?? null;
if ($aseguradora!==$catalogo['aseguradora']) errorSeguro(400,'ASEGURADORA_INVALIDA','Seleccione ASEGURADORA_DEMO.');
$atencion=$d['tipoAtencion'] ?? null;
if (!is_string($atencion) || !array_key_exists($atencion,$catalogo['tarifasCentimos'])) errorSeguro(400,'ATENCION_INVALIDA','Seleccione medicina_general, cardiologia o pediatria.');
$escenario=$d['escenario'] ?? 'normal';
if (!is_string($escenario) || !in_array($escenario,['normal','no_disponible'],true)) errorSeguro(400,'ESCENARIO_INVALIDO','Use normal o no_disponible.');
if ($escenario==='no_disponible') errorSeguro(503,'ASEGURADORA_SIMULADA_NO_DISPONIBLE','Falla simulada. No es posible determinar la cobertura; no se debe asumir que esta aprobada.');
$poliza=$catalogo['polizas'][$dni] ?? null;
if (!$poliza) {
 $_SESSION['cobertura_validada']=['dni'=>$dni,'tipoAtencion'=>$atencion,'cobertura'=>false,'tarifaCentimos'=>$catalogo['tarifasCentimos'][$atencion],'copagoCentimos'=>null,'fecha'=>time()];
 responder(404,['success'=>false,'simulado'=>true,'proveedor'=>'ASEGURADORA_DEMO','message'=>'No hay una poliza en el proveedor academico. Puede continuar como particular.',
   'error'=>['codigo'=>'AFILIACION_FICTICIA_NO_ENCONTRADA','mensaje'=>'Afiliacion no registrada en el entorno academico.'],
   'integracion'=>$externo['integracion'],'datos'=>['dni'=>$dni,'tipoAtencion'=>$atencion,'cobertura'=>false,'resultado'=>'SIN_AFILIACION_ACADEMICA','ofrecerParticular'=>true,'tarifaParticular'=>$catalogo['tarifasCentimos'][$atencion]/100,'copago'=>null,'moneda'=>'PEN']]);
}
$cubierto=$poliza['vigente'] && array_key_exists($atencion,$poliza['coberturas']);
$codigo=!$poliza['vigente'] ? 'SEGURO_VENCIDO' : ($cubierto ? 'COBERTURA_APROBADA' : 'ATENCION_NO_CUBIERTA');
$tarifa=$catalogo['tarifasCentimos'][$atencion];
$copago=$cubierto ? $poliza['coberturas'][$atencion] : null;
$_SESSION['cobertura_validada']=['dni'=>$dni,'tipoAtencion'=>$atencion,'cobertura'=>$cubierto,'tarifaCentimos'=>$tarifa,'copagoCentimos'=>$copago,'fecha'=>time()];
responder(200,['success'=>true,'simulado'=>true,'proveedor'=>'ASEGURADORA_DEMO',
    'message'=>'Consulta del catalogo de seguros ficticio completada. No acredita cobertura real.',
    'integracion'=>$externo['integracion'],'datos'=>['dni'=>$dni,'aseguradora'=>$aseguradora,'plan'=>$poliza['plan'],'tipoAtencion'=>$atencion,
        'vigenciaInicio'=>$poliza['inicio'],'vigenciaFin'=>$poliza['fin'],'vigente'=>$poliza['vigente'],'cobertura'=>$cubierto,'resultado'=>$codigo,'moneda'=>'PEN',
        'tarifaParticular'=>$tarifa/100,'copago'=>$copago===null ? null : $copago/100,
        'importeAseguradora'=>$copago===null ? null : ($tarifa-$copago)/100,
        'ofrecerParticular'=>!$cubierto]]);
