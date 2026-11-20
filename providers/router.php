<?php
declare(strict_types=1);
// Servidor academico independiente. Escuchar exclusivamente en 127.0.0.1.
ini_set('display_errors','0');
function enviar(int $status,array $data): never {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data,JSON_UNESCAPED_UNICODE); exit;
}
$configPath=dirname(__DIR__,2).'/proveedores-config.json';
$config=json_decode(file_get_contents($configPath),true);
if (!hash_equals($config['token'],$_SERVER['HTTP_X_PROVIDER_TOKEN'] ?? '')) enviar(401,['error'=>'Credencial de servicio requerida']);
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
header('X-Provider: Laboratorio-SOA');
if ($path==='/salud') enviar(200,['status'=>'ok','entorno'=>'ACADEMICO','datosSinteticos'=>true]);
if ($path==='/identidad' && $_SERVER['REQUEST_METHOD']==='POST') {
    $d=json_decode(file_get_contents('php://input'),true); $dni=$d['dni'] ?? null;
    if (!is_string($dni) || !preg_match('/^[0-9]{8}$/D',$dni)) enviar(400,['error'=>'DNI invalido']);
    $catalogo=require dirname(__DIR__).'/php/identidad_catalogo.php';
    if (!isset($catalogo[$dni])) enviar(404,['error'=>'Registro sintetico inexistente']);
    enviar(200,['datos'=>$catalogo[$dni],'datosSinteticos'=>true,'proveedor'=>'IDENTIDAD_ACADEMICA']);
}
if ($path==='/seguros' && $_SERVER['REQUEST_METHOD']==='GET') {
    require __DIR__.'/seguros_db.php';
    enviar(200,['datos'=>catalogoPolizas(),'datosSinteticos'=>true,'proveedor'=>'SEGUROS_ACADEMICOS_SQLITE']);
}
if ($path==='/facturacion' && $_SERVER['REQUEST_METHOD']==='POST') {
    $raw=file_get_contents('php://input');
    if (strlen($raw)>16000 || stripos($raw,'<!DOCTYPE')!==false) enviar(400,['error'=>'XML no admitido']);
    libxml_use_internal_errors(true);
    $xml=simplexml_load_string($raw,'SimpleXMLElement',LIBXML_NONET);
    if ($xml===false) enviar(400,['error'=>'XML invalido']);
    if (!$xml->asXML()) enviar(400,['error'=>'XML vacio']);
    $xml->registerXPathNamespace('soap','http://schemas.xmlsoap.org/soap/envelope/');
    $xml->registerXPathNamespace('demo','urn:clinica:facturacion:pruebas');
    $nodes=$xml->xpath('/soap:Envelope/soap:Body/demo:EnviarDocumento');
    if (count($nodes)!==1) enviar(400,['error'=>'Operacion SOAP no encontrada']);
    $v=$nodes[0]->children('urn:clinica:facturacion:pruebas');
    if (count($v->Identificador)!==1 || count($v->TotalCentimos)!==1 || count($v->Escenario)!==1 || count($v)!==3) enviar(422,['error'=>'Contrato XML invalido: se requieren Identificador, TotalCentimos y Escenario una sola vez']);
    $codigo=(string)$v->Identificador; $total=(string)$v->TotalCentimos; $escenario=(string)$v->Escenario;
    if (!preg_match('/^DEMO-[0-9]+$/D',$codigo) || !ctype_digit($total) || (int)$total<=0 || !in_array($escenario,['aceptado','rechazado','no_disponible'],true)) enviar(422,['error'=>'Documento de prueba invalido']);
    if ($escenario==='no_disponible') enviar(503,['error'=>'Indisponibilidad provocada del proveedor academico']);
    $db=new PDO('sqlite:'.dirname(__DIR__,2).'/proveedor-documentos.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE IF NOT EXISTS documentos(codigo TEXT PRIMARY KEY,total INTEGER NOT NULL,estado TEXT NOT NULL)');
    $db->beginTransaction();
    $s=$db->prepare('SELECT * FROM documentos WHERE codigo=?'); $s->execute([$codigo]); $previo=$s->fetch(PDO::FETCH_ASSOC);
    if ($previo && (int)$previo['total']!==(int)$total) { $db->rollBack(); enviar(409,['error'=>'Codigo reutilizado con otro importe']); }
    $estado=$previo && $previo['estado']==='ACEPTADO_SIMULADO' ? $previo['estado'] : ($escenario==='rechazado'?'RECHAZADO_SIMULADO':'ACEPTADO_SIMULADO');
    $s=$db->prepare('INSERT INTO documentos(codigo,total,estado) VALUES (?,?,?) ON CONFLICT(codigo) DO UPDATE SET estado=excluded.estado');
    $s->execute([$codigo,(int)$total,$estado]); $db->commit();
    header('Content-Type: text/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><demo:Respuesta xmlns:demo="urn:clinica:facturacion:pruebas"><demo:Codigo>'.htmlspecialchars($codigo,ENT_XML1).'</demo:Codigo><demo:Estado>'.$estado.'</demo:Estado><demo:ValidezTributaria>false</demo:ValidezTributaria></demo:Respuesta></soap:Body></soap:Envelope>';
    exit;
}
enviar(404,['error'=>'Recurso no encontrado']);
