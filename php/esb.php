<?php
// Puente de transporte del mismo origen. La mediacion se ejecuta en Apache Synapse.
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function errorBus(int $status,string $code,string $message): never {
    http_response_code($status);
    echo json_encode(['success'=>false,'error'=>['codigo'=>$code,'mensaje'=>$message],'message'=>$message]);
    exit;
}
$operations=[
    'sesion'=>['GET'],'procesar_login'=>['POST'],'procesar_registro'=>['POST'],
    'cerrar_sesion'=>['POST'],'estado_integraciones'=>['GET'],
    'identidad'=>['POST'],'seguros'=>['POST'],'sedes'=>['GET'],
    'doctores'=>['GET'],'disponibilidad'=>['GET'],'admision'=>['POST'],
    'reservar_cita'=>['POST'],'obtener_citas'=>['GET'],'comprobantes'=>['GET','POST']
];
$operation=$_GET['servicio'] ?? '';
if(!is_string($operation)||!isset($operations[$operation])) errorBus(404,'SERVICIO_NO_REGISTRADO','El servicio solicitado no esta registrado en el ESB.');
$method=$_SERVER['REQUEST_METHOD'];
if(!in_array($method,$operations[$operation],true)) {
    header('Allow: '.implode(', ',$operations[$operation]));
    errorBus(405,'METODO_NO_PERMITIDO','Metodo no permitido para este servicio.');
}
$configPath=dirname(__DIR__,2).'/esb-config.json';
$config=is_file($configPath)?json_decode(file_get_contents($configPath),true):null;
if(!is_array($config)||empty($config['token'])) errorBus(503,'ESB_SIN_CONFIGURAR','El ESB no esta configurado.');
$query=$_GET;unset($query['servicio']);
$headers=['Accept: application/json','X-ESB-Token: '.$config['token'],
    'X-Clinica-Query: '.http_build_query($query,'','&',PHP_QUERY_RFC3986),
    'X-Clinica-Proto: '.(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http')];
foreach(['CONTENT_TYPE'=>'Content-Type','HTTP_X_CSRF_TOKEN'=>'X-CSRF-Token'] as $key=>$name) {
    if(isset($_SERVER[$key])&&!preg_match('/[\r\n]/',$_SERVER[$key])) $headers[]=$name.': '.$_SERVER[$key];
}
$session=$_COOKIE['PHPSESSID'] ?? '';
if(is_string($session)&&preg_match('/^[a-zA-Z0-9,-]{1,128}$/D',$session)) $headers[]='Cookie: PHPSESSID='.$session;
$body=file_get_contents('php://input',false,null,0,65537);
if(strlen($body)>65536) errorBus(413,'SOLICITUD_DEMASIADO_GRANDE','La solicitud supera el limite permitido.');
$receivedHeaders=[];
$c=curl_init('http://127.0.0.1:8280/clinica/'.$operation);
curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,
    CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>15,
    CURLOPT_NOPROXY=>'127.0.0.1',CURLOPT_FOLLOWLOCATION=>false,
    CURLOPT_HEADERFUNCTION=>function($curl,string $line) use (&$receivedHeaders):int {
        if(str_contains($line,':')) {[$name,$value]=explode(':',$line,2);
            if(in_array(strtolower($name),['content-type','set-cookie','cache-control','x-esb-trace','x-esb-engine','retry-after','allow'],true)) $receivedHeaders[]=trim($name).': '.trim($value);
        } return strlen($line);
    }]);
if($method==='POST') curl_setopt($c,CURLOPT_POSTFIELDS,$body);
$response=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
if($response===false||$status<100) errorBus(503,'ESB_NO_DISPONIBLE','El bus de servicios no esta disponible. Inicie el ESB e intente nuevamente.');
foreach($receivedHeaders as $line) header($line,strncasecmp($line,'Set-Cookie:',11)!==0);
// Los fallos del mediador se adaptan al sobre JSON empleado por la interfaz.
if($status>=400) {
    $decoded=json_decode($response,true);
    if(!is_array($decoded)||!array_key_exists('success',$decoded)) {
        $detail=is_array($decoded)?($decoded['respuesta']['message'] ?? ''):'';
        $code=$status===403?'ESB_ACCESO_DENEGADO':'SERVICIO_INTERNO_NO_DISPONIBLE';
        errorBus($status,$code,$detail!==''?$detail:'El ESB no pudo completar la comunicacion con el servicio.');
    }
}
http_response_code($status);echo $response;
