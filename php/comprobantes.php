<?php
require __DIR__.'/api.php';
$paciente=paciente();
$conexion->query("SET time_zone = '-05:00'");
function formatoComprobante(array $r): array {
    global $conexion;
    $q=$conexion->prepare('SELECT modalidad FROM cita_contexto WHERE cita_id=?');$q->bind_param('i',$r['cita_id']);$q->execute();$ctx=$q->get_result()->fetch_assoc();
    return ['idComprobante'=>(int)$r['id'],'codigo'=>'DEMO-'.str_pad((string)$r['id'],6,'0',STR_PAD_LEFT),
        'idCita'=>(int)$r['cita_id'],'tipo'=>'COMPROBANTE_DE_PRUEBA','cliente'=>$r['nombre_cliente'],
        'concepto'=>$r['concepto'],'moneda'=>'PEN','montoTotal'=>((int)$r['total_centimos'])/100,
        'modalidad'=>$ctx['modalidad'] ?? 'PARTICULAR_DEMO','estado'=>$r['estado'],'intentos'=>(int)$r['intentos'],
        'fechaEmision'=>$r['creado_en'],'fechaActualizacion'=>$r['actualizado_en'],'zonaHoraria'=>'America/Lima',
        'simulado'=>true,'validezTributaria'=>false,'pagoRealizado'=>false];
}
function respuestaFactura(int $status, bool $ok, string $mensaje, array $datos, ?string $error=null): never {
    $r=['success'=>$ok,'simulado'=>true,'proveedor'=>'RECEPTOR_XML_ACADEMICO','message'=>$mensaje,'datos'=>$datos];
    if ($error) $r['error']=['codigo'=>$error,'mensaje'=>$mensaje];
    responder($status,$r);
}
function buscarComprobante(mysqli $db,int $id,int $paciente): ?array {
    $s=$db->prepare('SELECT * FROM comprobantes_demo WHERE id=? AND paciente_id=?');
    $s->bind_param('ii',$id,$paciente);$s->execute();return $s->get_result()->fetch_assoc();
}
if ($_SERVER['REQUEST_METHOD']==='GET') {
    if (isset($_GET['id'])) {
        $id=filter_var($_GET['id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) fallo(400,'COMPROBANTE_INVALIDO','Indique un identificador positivo.');
        $r=buscarComprobante($conexion,$id,$paciente);
        if (!$r) fallo(404,'COMPROBANTE_NO_ENCONTRADO','Comprobante no encontrado para su cuenta.');
        respuestaFactura(200,true,'Documento de demostracion sin validez tributaria.',formatoComprobante($r));
    }
    $s=$conexion->prepare('SELECT * FROM comprobantes_demo WHERE paciente_id=? ORDER BY id DESC');
    $s->bind_param('i',$paciente);$s->execute();
    respuestaFactura(200,true,'Comprobantes de prueba de su cuenta.',array_map('formatoComprobante',$s->get_result()->fetch_all(MYSQLI_ASSOC)));
}
metodo('POST');csrf();$d=entrada();
foreach (['montoTotal','total_centimos','paciente_id','estado','copago'] as $campo) {
    if (array_key_exists($campo,$d)) fallo(400,'CAMPO_NO_PERMITIDO','El importe, paciente y estado se determinan en el servidor.');
}
$op=$d['operacion'] ?? 'generar';
if (!is_string($op) || !in_array($op,['generar','reenviar'],true)) fallo(400,'OPERACION_INVALIDA','Use generar o reenviar.');
$escenario=$d['escenario'] ?? 'aceptado';
if (!is_string($escenario) || !in_array($escenario,['aceptado','rechazado','no_disponible'],true)) fallo(400,'ESCENARIO_INVALIDO','Seleccione aceptado, rechazado o no_disponible.');
$estado='PENDIENTE_SIMULADO'; // Solo una respuesta del proveedor puede aprobar el documento.
$campo=$op==='generar' ? 'idCita' : 'idComprobante';
$id=filter_var($d[$campo] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!$id) fallo(400,'IDENTIFICADOR_INVALIDO','Indique '.$campo.' como entero positivo.');
$conexion->begin_transaction();
try {
    if ($op==='generar') {
        // Bloquear la cita evita dos emisiones simultaneas. La BD tambien tiene UNIQUE(cita_id).
        $s=$conexion->prepare('SELECT c.id,d.especialidad,p.nombre,p.apellido FROM citas c JOIN doctores d ON d.id=c.doctor_id JOIN pacientes p ON p.id=c.paciente_id WHERE c.id=? AND c.paciente_id=? FOR UPDATE');
        $s->bind_param('ii',$id,$paciente);$s->execute();$cita=$s->get_result()->fetch_assoc();
        if (!$cita) { $conexion->rollback();fallo(404,'CITA_NO_ENCONTRADA','Cita no encontrada para su cuenta.'); }
        $s=$conexion->prepare('SELECT * FROM comprobantes_demo WHERE cita_id=?');$s->bind_param('i',$id);$s->execute();$existente=$s->get_result()->fetch_assoc();
        if ($existente) { $conexion->commit();respuestaFactura(409,false,'La cita ya tiene un documento de prueba. Consulte o reenvie el existente.',formatoComprobante($existente),'COMPROBANTE_YA_GENERADO'); }
        $tarifas=['Medicina General'=>10000,'Cardiologia'=>15000,'Pediatria'=>12000];
        if (!isset($tarifas[$cita['especialidad']])) { $conexion->rollback();fallo(422,'TARIFA_NO_CONFIGURADA','No hay tarifa de demostracion para esta especialidad.'); }
        $total=$tarifas[$cita['especialidad']];
        $q=$conexion->prepare('SELECT pagar_centimos FROM cita_contexto WHERE cita_id=?');$q->bind_param('i',$id);$q->execute();$ctx=$q->get_result()->fetch_assoc();
        if ($ctx) $total=(int)$ctx['pagar_centimos'];$nombre=$cita['nombre'].' '.$cita['apellido'];$concepto='Consulta de prueba - '.$cita['especialidad'];
        $s=$conexion->prepare('INSERT INTO comprobantes_demo(cita_id,paciente_id,nombre_cliente,concepto,total_centimos,estado) VALUES (?,?,?,?,?,?)');
        $s->bind_param('iissis',$id,$paciente,$nombre,$concepto,$total,$estado);$s->execute();$comprobante=$conexion->insert_id;
    } else {
        $s=$conexion->prepare('SELECT * FROM comprobantes_demo WHERE id=? AND paciente_id=? FOR UPDATE');$s->bind_param('ii',$id,$paciente);$s->execute();$r=$s->get_result()->fetch_assoc();
        if (!$r) { $conexion->rollback();fallo(404,'COMPROBANTE_NO_ENCONTRADO','Comprobante no encontrado para su cuenta.'); }
        if ($r['estado']==='ACEPTADO_SIMULADO') { $conexion->commit();respuestaFactura(409,false,'El comprobante ya fue aceptado por el simulador.',formatoComprobante($r),'COMPROBANTE_YA_ACEPTADO'); }
        $s=$conexion->prepare('UPDATE comprobantes_demo SET estado=?,intentos=intentos+1 WHERE id=? AND paciente_id=?');$s->bind_param('sii',$estado,$id,$paciente);$s->execute();$comprobante=$id;
    }
    $conexion->commit();
} catch (Throwable $e) { $conexion->rollback();throw $e; }
$r=formatoComprobante(buscarComprobante($conexion,$comprobante,$paciente));
require __DIR__.'/proveedor_http.php';
$xml='<?xml version="1.0" encoding="UTF-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><demo:EnviarDocumento xmlns:demo="urn:clinica:facturacion:pruebas"><demo:Identificador>'.$r['codigo'].'</demo:Identificador><demo:TotalCentimos>'.(int)round($r['montoTotal']*100).'</demo:TotalCentimos><demo:Escenario>'.$escenario.'</demo:Escenario></demo:EnviarDocumento></soap:Body></soap:Envelope>';
try {
 $externo=proveedorHttp('/facturacion','POST',$xml,true);
 if ($externo['status']===503) { $escenario='no_disponible';$estado='PENDIENTE_SIMULADO'; }
 elseif ($externo['status']===200) {
  libxml_use_internal_errors(true);$res=simplexml_load_string($externo['raw'],'SimpleXMLElement',LIBXML_NONET);
  if ($res===false) throw new RuntimeException('XML proveedor invalido');
  $res->registerXPathNamespace('demo','urn:clinica:facturacion:pruebas');$estados=$res->xpath('//demo:Estado');$codigos=$res->xpath('//demo:Codigo');
  if (count($estados)!==1 || count($codigos)!==1 || (string)$codigos[0]!==$r['codigo'] || !in_array((string)$estados[0],['ACEPTADO_SIMULADO','RECHAZADO_SIMULADO'],true)) throw new RuntimeException('Respuesta XML invalida');
  $estado=(string)$estados[0];$escenario=$estado==='ACEPTADO_SIMULADO'?'aceptado':'rechazado';
 } else throw new RuntimeException('Fallo HTTP proveedor');
 $r['integracion']=$externo['integracion'];$r['xmlEnviado']=$xml;$r['xmlRecibido']=$externo['raw'];
} catch (RuntimeException $e) {
 $escenario='no_disponible';$estado='PENDIENTE_SIMULADO';
 $r['integracion']=['transporte'=>'SOAP_XML_HTTP','destino'=>'http://127.0.0.1:8090/facturacion','confirmado'=>false,'oficial'=>false];
}
$s=$conexion->prepare('UPDATE comprobantes_demo SET estado=? WHERE id=? AND paciente_id=?');$s->bind_param('sii',$estado,$comprobante,$paciente);$s->execute();$r['estado']=$estado;

if ($escenario==='no_disponible') respuestaFactura(503,false,'Proveedor simulado no disponible. El documento quedo guardado como pendiente; reenvie el mismo identificador.', $r,'PROVEEDOR_SIMULADO_NO_DISPONIBLE');
if ($escenario==='rechazado') respuestaFactura($op==='generar'?201:200,true,'Documento guardado y rechazado por el simulador. No tiene validez tributaria.',$r);
respuestaFactura($op==='generar'?201:200,true,'Documento de prueba aceptado por el simulador local. Sin validez tributaria ni cobro.',$r);
