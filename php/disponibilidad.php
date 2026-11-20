<?php
require __DIR__.'/api.php'; metodo('GET'); paciente();
$id=filter_var($_GET['doctor_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!$id) fallo(400,'MEDICO_INVALIDO','Seleccione un medico.');
$fecha=texto($_GET,'fecha'); fechaValida($fecha);
$s=$conexion->prepare('SELECT id FROM doctores WHERE id=? AND activo=1'); $s->bind_param('i',$id); $s->execute();
if (!$s->get_result()->fetch_assoc()) fallo(404,'MEDICO_NO_ENCONTRADO','El medico no esta disponible.');
$s=$conexion->prepare('SELECT TIME_FORMAT(hora, "%H:%i") AS hora FROM citas WHERE doctor_id=? AND fecha=?');
$s->bind_param('is',$id,$fecha); $s->execute(); $ocupados=array_column($s->get_result()->fetch_all(MYSQLI_ASSOC),'hora');
$ahora=new DateTimeImmutable();
$libres=array_values(array_filter(horarios(),fn($hora)=>!in_array($hora,$ocupados,true) && new DateTimeImmutable($fecha.' '.$hora)>$ahora));
responder(200,['success'=>true,'datos'=>['doctor_id'=>$id,'fecha'=>$fecha,'duracionMinutos'=>30,'horarios'=>$libres]]);
