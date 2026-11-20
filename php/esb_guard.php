<?php
// Una vez activado, las APIs solo aceptan solicitudes del bus local autenticado.
$esbConfigPath=dirname(__DIR__,2).'/esb-config.json';
$esbConfig=is_file($esbConfigPath)?json_decode(file_get_contents($esbConfigPath),true):null;
if(!is_array($esbConfig)||empty($esbConfig['enabled'])||empty($esbConfig['token'])) {
    http_response_code(503);
    echo json_encode(['success'=>false,'error'=>['codigo'=>'ESB_SIN_CONFIGURAR','mensaje'=>'Configure el bus de servicios antes de utilizar la API.'],'message'=>'Configure el bus de servicios antes de utilizar la API.']);
    exit;
}
if(is_array($esbConfig)&&!empty($esbConfig['enabled'])) {
    $esbToken=$_SERVER['HTTP_X_ESB_TOKEN'] ?? '';
    $isBus=in_array($_SERVER['REMOTE_ADDR'] ?? '',['127.0.0.1','::1'],true)
        &&is_string($esbToken)&&isset($esbConfig['token'])&&hash_equals($esbConfig['token'],$esbToken);
    if(!$isBus) {
        http_response_code(403);
        echo json_encode(['success'=>false,'error'=>['codigo'=>'ACCESO_DIRECTO_NO_PERMITIDO','mensaje'=>'Utilice el bus de servicios.' ],'message'=>'Utilice el bus de servicios.']);
        exit;
    }
    // Solo se confia en esta cabecera despues de autenticar al bus.
    if(($_SERVER['HTTP_X_CLINICA_PROTO'] ?? '')==='https') $_SERVER['HTTPS']='on';
}
unset($esbConfig,$esbToken,$isBus);
