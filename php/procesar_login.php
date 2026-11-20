<?php
require __DIR__.'/api.php';
metodo('POST'); csrf(); $d=entrada();
$email=strtolower(texto($d,'email')); $password=$d['password'] ?? null;
if (!filter_var($email,FILTER_VALIDATE_EMAIL) || !is_string($password) || $password==='') fallo(400,'DATOS_INVALIDOS','Ingrese correo y contrasena.');
$s=$conexion->prepare("SELECT u.id,u.password,ip.nombre,p.id AS paciente_id FROM usuarios u JOIN informacion_personal ip ON ip.usuario_id=u.id JOIN usuario_roles ur ON ur.usuario_id=u.id JOIN roles r ON r.id=ur.rol_id AND r.nombre='paciente' JOIN pacientes p ON p.usuario_id=u.id WHERE u.email=?");
$s->bind_param('s',$email); $s->execute(); $u=$s->get_result()->fetch_assoc();
if (!$u || !password_verify($password,$u['password'])) fallo(401,'CREDENCIALES_INVALIDAS','Correo o contrasena incorrectos, o perfil no habilitado.');
session_regenerate_id(true);
$_SESSION['user_id']=(int)$u['id']; $_SESSION['paciente_id']=(int)$u['paciente_id'];
$_SESSION['user_nombre']=$u['nombre']; $_SESSION['user_rol']='paciente';
$_SESSION['csrf']=bin2hex(random_bytes(32));
responder(200,['success'=>true,'message'=>'Inicio de sesion correcto.','redirect'=>'citas.html']);
