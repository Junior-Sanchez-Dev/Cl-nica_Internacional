<?php
include 'conexion.php';  // Incluir la conexión

$paciente_id = $_POST['paciente_id'];
$doctor_id = $_POST['doctor_id'];
$fecha = $_POST['fecha'];
$hora = $_POST['hora'];
$motivo = $_POST['motivo'];

$sql = "INSERT INTO citas (paciente_id, doctor_id, fecha, hora, motivo) 
        VALUES ('$paciente_id', '$doctor_id', '$fecha', '$hora', '$motivo')";

if ($conexion->query($sql) === TRUE) {
    echo "Cita reservada exitosamente.";
} else {
    echo "Error: " . $sql . "<br>" . $conexion->error;
}

$conexion->close();
