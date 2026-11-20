<?php
require __DIR__.'/api.php'; metodo('GET'); paciente();
$resultado=$conexion->query('SELECT id,nombre,especialidad FROM doctores WHERE activo=1 ORDER BY especialidad,nombre');
responder(200,['success'=>true,'datos'=>$resultado->fetch_all(MYSQLI_ASSOC)]);
