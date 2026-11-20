<?php
require __DIR__.'/api.php'; metodo('POST'); $paciente=paciente(); csrf(); $d=entrada();
// La identidad del paciente se obtiene de la sesion, nunca del formulario.
if (array_key_exists('paciente_id',$d) || array_key_exists('usuario_id',$d)) fallo(400,'CAMPO_NO_PERMITIDO','La identidad del paciente se obtiene de la sesion.');
$id=filter_var($d['doctor_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$fecha=texto($d,'fecha'); $hora=texto($d,'hora'); $motivo=texto($d,'motivo');
if (!$id || $motivo==='' || mb_strlen($motivo)>500) fallo(400,'DATOS_INVALIDOS','Indique medico y motivo de hasta 500 caracteres.');
fechaValida($fecha);
if (!in_array($hora,horarios(),true)) fallo(400,'HORA_INVALIDA','Seleccione un turno de 30 minutos entre 08:00 y 16:30.');
if (new DateTimeImmutable($fecha.' '.$hora)<=new DateTimeImmutable()) fallo(400,'FECHA_PASADA','La cita debe ser futura.');
$s=$conexion->prepare('SELECT id,especialidad FROM doctores WHERE id=? AND activo=1'); $s->bind_param('i',$id); $s->execute();
$medico=$s->get_result()->fetch_assoc();
if (!$medico) fallo(404,'MEDICO_NO_ENCONTRADO','El medico no esta disponible.');
$contexto=null;
if (defined('FLUJO_ADMISION')) {
    $identidad=$_SESSION['identidad_validada'] ?? null;
    $cobertura=$_SESSION['cobertura_validada'] ?? null;
    if (!$identidad || time()-$identidad['fecha']>600) fallo(409,'IDENTIDAD_REQUERIDA','Primero consulta la identidad; la validacion dura 10 minutos.');
    $sedeId=filter_var($d['idSede'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $sedes=require __DIR__.'/sedes_catalogo.php';$sedeOk=false;
    foreach ($sedes as $sede) if ($sede['id']===$sedeId && $sede['habilitada']) $sedeOk=true;
    if (!$sedeOk) fallo(400,'SEDE_INVALIDA','Selecciona una sede habilitada.');
    $tipo=['Medicina General'=>'medicina_general','Cardiologia'=>'cardiologia','Pediatria'=>'pediatria'][$medico['especialidad']] ?? null;
    $modalidad=$d['modalidad'] ?? '';
    if (!in_array($modalidad,['seguro','particular'],true)) fallo(400,'MODALIDAD_INVALIDA','Elige seguro o particular.');
    if (!$cobertura || time()-$cobertura['fecha']>600 || $cobertura['dni']!==$identidad['dni'] || $cobertura['tipoAtencion']!==$tipo) fallo(409,'COBERTURA_REQUERIDA','Consulta cobertura con el mismo DNI y la especialidad del medico elegido.');
    if ($modalidad==='seguro' && !$cobertura['cobertura']) fallo(409,'COBERTURA_NO_APROBADA','No hay cobertura aprobada. Puedes elegir atencion particular.');
    $contexto=['sede'=>$sedeId,'modalidad'=>$modalidad,'tarifa'=>$cobertura['tarifaCentimos'],'pagar'=>$modalidad==='seguro'?$cobertura['copagoCentimos']:$cobertura['tarifaCentimos'],'proveedor'=>$identidad['proveedor'],'sinteticos'=>(int)$identidad['datosSinteticos']];
}
$conexion->begin_transaction();
try {
    $s=$conexion->prepare('INSERT INTO citas(paciente_id,doctor_id,fecha,hora,motivo) VALUES (?,?,?,?,?)');
    $s->bind_param('iisss',$paciente,$id,$fecha,$hora,$motivo); $s->execute();
    $cita=$conexion->insert_id;
    if ($contexto) {
        $s=$conexion->prepare('INSERT INTO cita_contexto(cita_id,sede_id,modalidad,tarifa_centimos,pagar_centimos,identidad_proveedor,datos_sinteticos) VALUES (?,?,?,?,?,?,?)');
        $s->bind_param('iisiisi',$cita,$contexto['sede'],$contexto['modalidad'],$contexto['tarifa'],$contexto['pagar'],$contexto['proveedor'],$contexto['sinteticos']);$s->execute();
    }
    $conexion->commit();
} catch (mysqli_sql_exception $e) {
    $conexion->rollback();
    if ($e->getCode()===1062) fallo(409,'HORARIO_NO_DISPONIBLE','El turno acaba de ser reservado. Consulte otro horario.');
    throw $e;
}
responder(201,['success'=>true,'message'=>'Cita reservada correctamente.','datos'=>['idCita'=>$cita,'codigoReserva'=>'CITA-'.str_pad((string)$cita,6,'0',STR_PAD_LEFT),'estado'=>'RESERVADA','modalidad'=>$contexto['modalidad'] ?? 'independiente','montoPaciente'=>isset($contexto['pagar'])?$contexto['pagar']/100:null,'idSede'=>$contexto['sede'] ?? null,'doctor_id'=>$id,'fecha'=>$fecha,'hora'=>$hora]]);
