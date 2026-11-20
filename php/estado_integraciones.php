<?php
require __DIR__.'/api.php';metodo('GET');paciente();
require __DIR__.'/proveedor_http.php';
try {
    $v=proveedorHttp('/salud');
    responder($v['status']===200?200:503,['success'=>$v['status']===200,'datos'=>$v['json'],'integracion'=>$v['integracion'],
        'message'=>'Proveedor academico separado. HTTP real, datos sinteticos; no es RENIEC, aseguradora ni SUNAT.']);
} catch (RuntimeException $e) {
    fallo(503,'PROVEEDOR_NO_DISPONIBLE','Inicie iniciar-proveedores.ps1 en otra terminal de VS Code.');
}
