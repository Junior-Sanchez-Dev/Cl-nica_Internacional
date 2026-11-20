<?php
require __DIR__.'/../php/factiliza_respuesta.php';
$persona=['numero'=>'00000001','nombres'=>'Ana','apellido_paterno'=>'Demo','apellido_materno'=>'Prueba','direccion'=>'Dato que debe descartarse'];
$casos=[
 ['documentado',200,['status'=>200,'success'=>true,'data'=>$persona],null],
 ['sin success',200,['status'=>200,'data'=>$persona],null],
 ['status texto',200,['status'=>'200','data'=>$persona],null],
 ['rechazo explicito',200,['status'=>200,'success'=>false,'data'=>$persona],'RECHAZADA'],
 ['sin indicador de exito',200,['data'=>$persona],'CONTRATO'],
 ['credencial cuerpo',200,['status'=>401,'success'=>false],'CREDENCIAL'],
 ['limite cuerpo',200,['status'=>429,'success'=>false],'LIMITE'],
 ['no encontrado',404,['status'=>404],'NO_ENCONTRADO'],
 ['datos incompletos',200,['success'=>true,'data'=>['numero'=>'00000001']],'CONTRATO'],
 ['otro documento',200,['success'=>true,'data'=>array_merge($persona,['numero'=>'00000002'])],'DOCUMENTO_INCONSISTENTE'],
 ['falla HTTP',500,['success'=>true,'data'=>$persona],'RECHAZADA'],
];
foreach($casos as [$nombre,$http,$body,$esperado]) {
 $r=interpretarFactiliza($http,json_encode($body),'00000001');
 if (($r['error'] ?? null)!==$esperado) throw new RuntimeException('Fallo: '.$nombre);
 if (isset($r['datos']['direccion'])) throw new RuntimeException('Se filtro direccion');
 echo 'OK '.$nombre.PHP_EOL;
}
if ((interpretarFactiliza(200,'<html>error</html>','00000001')['error'] ?? '')!=='RECHAZADA') throw new RuntimeException('Se acepto HTML');
echo 'OK contenido no JSON'.PHP_EOL;
