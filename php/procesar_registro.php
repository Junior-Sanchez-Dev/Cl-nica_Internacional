<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $conexion->real_escape_string($_POST['nombre']);
    $apellido = $conexion->real_escape_string($_POST['apellido']);
    $email = $conexion->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Iniciar transacción
    $conexion->begin_transaction();

    try {
        // Insertar en la tabla usuarios
        $sql_usuario = "INSERT INTO usuarios (email, password) VALUES (?, ?)";
        $stmt_usuario = $conexion->prepare($sql_usuario);
        $stmt_usuario->bind_param("ss", $email, $password);
        $stmt_usuario->execute();
        $usuario_id = $stmt_usuario->insert_id;

        // Insertar en la tabla informacion_personal
        $sql_info = "INSERT INTO informacion_personal (usuario_id, nombre, apellido) VALUES (?, ?, ?)";
        $stmt_info = $conexion->prepare($sql_info);
        $stmt_info->bind_param("iss", $usuario_id, $nombre, $apellido);
        $stmt_info->execute();

        // Asignar rol de paciente por defecto
        $sql_rol = "INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (?, (SELECT id FROM roles WHERE nombre = 'paciente'))";
        $stmt_rol = $conexion->prepare($sql_rol);
        $stmt_rol->bind_param("i", $usuario_id);
        $stmt_rol->execute();

        // Confirmar transacción
        $conexion->commit();

        echo json_encode(["success" => true, "message" => "Usuario registrado exitosamente"]);
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conexion->rollback();
        echo json_encode(["success" => false, "message" => "Error al registrar: " . $e->getMessage()]);
    }

    $stmt_usuario->close();
    $stmt_info->close();
    $stmt_rol->close();
}

$conexion->close();
?>