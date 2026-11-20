<?php
function consultarFactiliza(string $dni): array {
 $path=dirname(__DIR__,2).'/factiliza-config.json';
 $cfg=is_file($path)?json_decode(file_get_contents($path),true):[];
 $token=is_array($cfg)?($cfg['token'] ?? ''):'';
 if (!is_string($token) || $token==='') fallo(503,'FACTILIZA_SIN_CONFIGURAR','Falta configurar localmente el token del plan gratuito. No se consulto ningun proveedor.');
 if (strlen($token)<8 || strlen($token)>8192 || preg_match('/[\s\x00-\x1F\x7F]/',$token) || preg_match('/^[*]+$/D',$token)) fallo(503,'FACTILIZA_TOKEN_INCOMPLETO','El token guardado esta incompleto o contiene caracteres de control. Copialo de Factiliza y ejecuta configurar-factiliza.ps1 -DesdePortapapeles. No se realizo una consulta externa.');
 // Presupuesto preventivo local de 80 solicitudes por mes para esta instalacion.
 // No conoce el consumo hecho fuera de esta aplicacion ni cambia el plan contratado.
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/factiliza-uso.sqlite');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $db->exec('CREATE TABLE IF NOT EXISTS uso(mes TEXT PRIMARY KEY,consultas INTEGER NOT NULL)');
 $mes=(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y-m');
 $db->exec('BEGIN IMMEDIATE');
 $q=$db->prepare('INSERT OR IGNORE INTO uso VALUES (?,0)');$q->execute([$mes]);
 $q=$db->prepare('SELECT consultas FROM uso WHERE mes=?');$q->execute([$mes]);
 if ((int)$q->fetchColumn()>=80) { $db->exec('ROLLBACK');fallo(429,'PRESUPUESTO_LOCAL_AGOTADO','Se alcanzo el limite preventivo de 80 solicitudes mensuales. No se realizo una llamada al proveedor.'); }
 $q=$db->prepare('UPDATE uso SET consultas=consultas+1 WHERE mes=?');$q->execute([$mes]);$db->exec('COMMIT');
 $url='https://api.factiliza.com/v1/dni/info/'.$dni;
 $c=curl_init($url);$start=microtime(true);
 curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>7,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Accept: application/json','Authorization: Bearer '.$token],CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
 $raw=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
 if ($raw===false) fallo(503,'FACTILIZA_NO_DISPONIBLE','No se obtuvo respuesta de Factiliza. No se usaron datos de reemplazo.');
 require_once __DIR__.'/factiliza_respuesta.php';
 $v=interpretarFactiliza($status,$raw,$dni);
 if (isset($v['error'])) {
  $mensajes=['CREDENCIAL'=>'El proveedor rechazo la credencial configurada.','LIMITE'=>'Limite del proveedor alcanzado. No se contratara un plan pagado.','NO_ENCONTRADO'=>'El proveedor no encontro el documento.','RECHAZADA'=>'El proveedor no confirmo la consulta.','CONTRATO'=>'La respuesta no coincide con el contrato esperado.','DOCUMENTO_INCONSISTENTE'=>'El documento recibido no coincide con el solicitado.'];
  $http=$v['error']==='LIMITE'?429:($v['error']==='NO_ENCONTRADO'?404:502);
  $mensaje=$mensajes[$v['error']].' HTTP del proveedor: '.$status.'. Estado reportado: '.($v['estado'] ?? 'no informado').'.';
  responder($http,['success'=>false,'proveedor'=>'FACTILIZA','message'=>$mensaje,
      'error'=>['codigo'=>'FACTILIZA_'.$v['error'],'mensaje'=>$mensaje],
      'diagnostico'=>['httpProveedor'=>$status,'estadoProveedor'=>$v['estado'],'categoria'=>$v['error']]]);
 }
 return ['datos'=>$v['datos'],
 'integracion'=>['transporte'=>'HTTPS_JSON','destino'=>'https://api.factiliza.com/v1/dni/info/{dni}','httpProveedor'=>$status,'duracionMs'=>round((microtime(true)-$start)*1000,2),'datosSinteticos'=>false,'oficial'=>false]];
}
