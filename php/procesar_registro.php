<?php
require __DIR__.'/api.php';
metodo('POST');
csrf();
$d=entrada();
$nombre=texto($d,'nombre'); $apellido=texto($d,'apellido');
$email=strtolower(texto($d,'email')); $password=$d['password'] ?? null;
if ($nombre==='' || $apellido==='' || mb_strlen($nombre)>100 || mb_strlen($apellido)>100 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email)>254 || !is_string($password) || strlen($password)<8 || strlen($password)>72) {
    fallo(400,'DATOS_INVALIDOS','Complete nombres, apellidos, un correo valido y una contrasena de 8 a 72 bytes.');
}
$hash=password_hash($password, PASSWORD_DEFAULT);
$conexion->begin_transaction();
try {
    $s=$conexion->prepare('INSERT INTO usuarios(email,password) VALUES (?,?)');
    $s->bind_param('ss',$email,$hash); $s->execute(); $id=$conexion->insert_id;
    $s=$conexion->prepare('INSERT INTO informacion_personal(usuario_id,nombre,apellido) VALUES (?,?,?)');
    $s->bind_param('iss',$id,$nombre,$apellido); $s->execute();
    $s=$conexion->prepare("INSERT INTO usuario_roles(usuario_id,rol_id) SELECT ?,id FROM roles WHERE nombre='paciente'");
    $s->bind_param('i',$id); $s->execute();
    if ($s->affected_rows!==1) throw new RuntimeException('Falta el rol paciente');
    $s=$conexion->prepare('INSERT INTO pacientes(usuario_id,nombre,apellido) VALUES (?,?,?)');
    $s->bind_param('iss',$id,$nombre,$apellido); $s->execute();
    $conexion->commit();
} catch (Throwable $e) {
    $conexion->rollback();
    if ($e instanceof mysqli_sql_exception && $e->getCode()===1062) fallo(409,'CORREO_REGISTRADO','El correo ya esta registrado.');
    throw $e;
}
responder(201,['success'=>true,'message'=>'Cuenta creada. Ahora puede iniciar sesion.']);
