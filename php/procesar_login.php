<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conexion->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT u.id, u.email, u.password, ip.nombre, ip.apellido, r.nombre as rol 
            FROM usuarios u 
            JOIN informacion_personal ip ON u.id = ip.usuario_id 
            JOIN usuario_roles ur ON u.id = ur.usuario_id 
            JOIN roles r ON ur.rol_id = r.id 
            WHERE u.email = ?";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_email'] = $row['email'];
            $_SESSION['user_nombre'] = $row['nombre'];
            $_SESSION['user_apellido'] = $row['apellido'];
            $_SESSION['user_rol'] = $row['rol'];
            echo json_encode(["success" => true, "message" => "Inicio de sesión exitoso", "redirect" => "index.html"]);
        } else {
            echo json_encode(["success" => false, "message" => "Contraseña incorrecta"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Usuario no encontrado"]);
    }
    $stmt->close();
}

$conexion->close();
?>