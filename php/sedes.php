<?php
require __DIR__.'/api.php';
metodo('GET'); paciente();
function errorSede(int $status,string $codigo,string $mensaje): never {
    responder($status,['success'=>false,'datosDemostracion'=>true,'proveedor'=>'CATALOGO_LOCAL_SEDES',
        'error'=>['codigo'=>$codigo,'mensaje'=>$mensaje],'message'=>$mensaje]);
}
$catalogo=require __DIR__.'/sedes_catalogo.php';
$lat=$_GET['latitud'] ?? null; $lon=$_GET['longitud'] ?? null;
$hayOrigen=array_key_exists('latitud',$_GET) || array_key_exists('longitud',$_GET);
$rutaExterna=$_GET['ruta'] ?? '0';
if (!is_string($rutaExterna) || !in_array($rutaExterna,['0','1'],true)) errorSede(400,'RUTA_INVALIDA','Use ruta=0 o ruta=1.');
if ($rutaExterna==='1' && !$hayOrigen) errorSede(400,'ORIGEN_REQUERIDO','Para consultar ruta vial debe ingresar un punto de origen.');
if (!isset($_GET['id'])) {
    if ($hayOrigen) errorSede(400,'SEDE_REQUERIDA','Seleccione una sede para calcular la distancia.');
    $sedes=array_values(array_filter($catalogo,fn($s)=>$s['habilitada']));
    responder(200,['success'=>true,'datosDemostracion'=>true,'proveedor'=>'CATALOGO_LOCAL_SEDES','message'=>'Sedes ficticias; no representan establecimientos reales.','datos'=>$sedes]);
}
$id=filter_var($_GET['id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!$id) errorSede(400,'SEDE_INVALIDA','El identificador debe ser un entero positivo.');
$sede=null;
foreach ($catalogo as $s) if ($s['id']===$id && $s['habilitada']) $sede=$s;
if (!$sede) errorSede(404,'SEDE_NO_ENCONTRADA','La sede no existe o no esta habilitada.');
$sede['mapaUrl']='https://www.google.com/maps/search/?api=1&query='.rawurlencode($sede['latitud'].','.$sede['longitud']);
if ($hayOrigen) {
    if (!is_string($lat) || !is_string($lon) || !is_numeric($lat) || !is_numeric($lon) ||
        !is_finite((float)$lat) || !is_finite((float)$lon) || abs((float)$lat)>90 || abs((float)$lon)>180) {
        errorSede(400,'COORDENADAS_INVALIDAS','Ingrese latitud entre -90 y 90 y longitud entre -180 y 180.');
    }
    // Haversine: distancia geodesica aproximada, no ruta vial ni tiempo de viaje.
    $phi1=deg2rad((float)$lat); $phi2=deg2rad($sede['latitud']);
    $a=sin(($phi2-$phi1)/2)**2+cos($phi1)*cos($phi2)*sin(deg2rad($sede['longitud']-(float)$lon)/2)**2;
    $sede['distanciaKm']=round(6371*2*asin(sqrt(max(0,min(1,$a)))),3);
    $sede['tipoDistancia']='LINEA_RECTA_APROXIMADA';
    if ($rutaExterna==='1') {
        require __DIR__.'/rutas_osrm.php';
        try { $ruta=rutaOsrm((float)$lat,(float)$lon,$sede['latitud'],$sede['longitud']); }
        catch (RuntimeException $e) { errorSede(503,'OSRM_NO_DISPONIBLE','El servicio externo OSRM no respondio. No se ha obtenido una ruta vial. Puede consultar la ubicacion sin ruta.'); }
        $sede=array_merge($sede,$ruta);
    }
}
responder(200,['success'=>true,'datosDemostracion'=>true,'proveedor'=>'CATALOGO_LOCAL_SEDES',
    'message'=>'Ubicacion ficticia. La distancia opcional es en linea recta, no una ruta ni un tiempo de viaje.','datos'=>$sede]);
