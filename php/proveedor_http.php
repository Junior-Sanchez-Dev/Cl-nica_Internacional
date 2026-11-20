<?php
function proveedorHttp(string $path,string $method='GET',?string $body=null,bool $xml=false): array {
    if (!in_array($path,['/salud','/identidad','/seguros','/facturacion'],true)) throw new InvalidArgumentException('Destino no permitido');
    $config=json_decode(file_get_contents(dirname(__DIR__,2).'/proveedores-config.json'),true);
    $url='http://127.0.0.1:8090'.$path;
    $headers=['X-Provider-Token: '.$config['token'],'Content-Type: '.($xml?'text/xml; charset=utf-8':'application/json')];
    if ($xml) $headers[]='SOAPAction: "urn:clinica:facturacion:pruebas/EnviarDocumento"';
    $c=curl_init($url);
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>5,CURLOPT_NOPROXY=>'127.0.0.1',CURLOPT_FOLLOWLOCATION=>false]);
    if ($body!==null) curl_setopt($c,CURLOPT_POSTFIELDS,$body);
    $start=microtime(true); $raw=curl_exec($c); $status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE); curl_close($c);
    if ($raw===false) throw new RuntimeException('Proveedor HTTP no disponible');
    return ['status'=>$status,'raw'=>$raw,'json'=>$xml?null:json_decode($raw,true),
        'integracion'=>['transporte'=>$xml?'SOAP_XML_HTTP':'REST_JSON_HTTP','destino'=>$url,'httpProveedor'=>$status,'duracionMs'=>round((microtime(true)-$start)*1000,2),'datosSinteticos'=>true,'oficial'=>false]];
}
