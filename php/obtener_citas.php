<?php
require_once 'conexion.php';

$sql = "SELECT c.id, p.nombre, c.fecha, c.hora, c.motivo 
        FROM citas c 
        JOIN pacientes p ON c.paciente_id = p.id 
        ORDER BY c.fecha, c.hora";
$resultado = $conexion->query($sql);

$citas = [];
if ($resultado->num_rows > 0) {
    while ($fila = $resultado->fetch_assoc()) {
        $citas[] = $fila;
    }
}

echo json_encode($citas);

$conexion->close();