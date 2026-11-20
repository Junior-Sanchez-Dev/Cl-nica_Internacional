<?php
// Adaptador HTTPS a OSRM/FOSSGIS. No sustituye una falla por una ruta inventada.
function rutaOsrm(float $lat,float $lon,float $destLat,float $destLon): array {
    $url='https://routing.openstreetmap.de/routed-car/route/v1/driving/'.
        $lon.','.$lat.';'.$destLon.','.$destLat.'?overview=false&steps=false';
    // Cache y bloqueo global: evita rafagas al servidor publico (max. 1 solicitud/segundo).
    $base=dirname(__DIR__,2); $lock=fopen($base.'/osrm.lock','c+');
    if (!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('No se pudo controlar acceso a rutas');
    try {
        $cache=$base.'/osrm-'.hash('sha256',$url).'.json';
        if (is_file($cache) && time()-filemtime($cache)<3600) {
            $saved=json_decode(file_get_contents($cache),true);
            $saved['integracion']['cache']=true; return $saved;
        }
        rewind($lock);$last=(float)stream_get_contents($lock);
        $wait=1.1-(microtime(true)-$last);if ($wait>0) usleep((int)($wait*1000000));
        $c=curl_init($url);$inicio=microtime(true);
        curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>6,
            CURLOPT_USERAGENT=>'ClinicaSOA-AcademicPrototype/1.0 (educational routing demonstration)',
            CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        $raw=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
        rewind($lock);ftruncate($lock,0);fwrite($lock,(string)microtime(true));fflush($lock);
        if ($raw===false || $status!==200) throw new RuntimeException('OSRM no responde; no hay ruta confirmada');
        $v=json_decode($raw,true);
        if (($v['code'] ?? '')!=='Ok' || !isset($v['routes'][0]['distance'],$v['routes'][0]['duration'])) throw new RuntimeException('OSRM no devolvio ruta valida');
        $ruta=$v['routes'][0];
        $result=['distanciaKm'=>round($ruta['distance']/1000,3),'duracionMinutos'=>round($ruta['duration']/60,1),
            'tipoDistancia'=>'RUTA_VIAL_OSRM','atribucion'=>'Rutas: OSRM / FOSSGIS. Datos: OpenStreetMap contributors (ODbL). Sin trafico en tiempo real.',
            'integracion'=>['transporte'=>'HTTPS_JSON','destino'=>$url,'httpProveedor'=>$status,'duracionMs'=>round((microtime(true)-$inicio)*1000,2),'cache'=>false,'datosVialesExternos'=>true]];
        file_put_contents($cache,json_encode($result));return $result;
    } finally { flock($lock,LOCK_UN);fclose($lock); }
}
