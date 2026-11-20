<?php
require __DIR__.'/api.php'; metodo('GET'); $id=paciente();
$s=$conexion->prepare('SELECT c.id,d.nombre AS medico,d.especialidad,c.fecha,TIME_FORMAT(c.hora,"%H:%i") AS hora,c.motivo,x.sede_id AS idSede,x.modalidad,x.pagar_centimos/100 AS montoPaciente FROM citas c JOIN doctores d ON d.id=c.doctor_id LEFT JOIN cita_contexto x ON x.cita_id=c.id WHERE c.paciente_id=? ORDER BY c.fecha,c.hora');
$s->bind_param('i',$id); $s->execute();
responder(200,['success'=>true,'datos'=>$s->get_result()->fetch_all(MYSQLI_ASSOC)]);
