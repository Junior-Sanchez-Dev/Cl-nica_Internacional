-- Base local de demostracion reconstruida desde el codigo del repositorio.
-- Los medicos son ficticios. No elimina bases ni tablas existentes.
CREATE DATABASE IF NOT EXISTS clinica_labendecida
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE clinica_labendecida;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(254) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS informacion_personal (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL UNIQUE,
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100) NOT NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(40) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usuario_roles (
  usuario_id INT NOT NULL,
  rol_id INT NOT NULL,
  PRIMARY KEY (usuario_id, rol_id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pacientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL UNIQUE,
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100) NOT NULL,
  dni CHAR(8) UNIQUE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS doctores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL UNIQUE,
  nombre VARCHAR(150) NOT NULL,
  especialidad VARCHAR(100) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS citas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  paciente_id INT NOT NULL,
  doctor_id INT NOT NULL,
  fecha DATE NOT NULL,
  hora TIME NOT NULL,
  motivo VARCHAR(500) NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY turno_medico (doctor_id, fecha, hora),
  FOREIGN KEY (paciente_id) REFERENCES pacientes(id),
  FOREIGN KEY (doctor_id) REFERENCES doctores(id)
) ENGINE=InnoDB;

INSERT INTO roles (nombre)
SELECT 'paciente' WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre='paciente');

INSERT INTO doctores (codigo, nombre, especialidad)
SELECT 'DEMO-001', 'Ana Torres (prueba)', 'Medicina General'
WHERE NOT EXISTS (SELECT 1 FROM doctores WHERE codigo='DEMO-001');
INSERT INTO doctores (codigo, nombre, especialidad)
SELECT 'DEMO-002', 'Luis Ramos (prueba)', 'Cardiologia'
WHERE NOT EXISTS (SELECT 1 FROM doctores WHERE codigo='DEMO-002');
INSERT INTO doctores (codigo, nombre, especialidad)
SELECT 'DEMO-003', 'Elena Perez (prueba)', 'Pediatria'
WHERE NOT EXISTS (SELECT 1 FROM doctores WHERE codigo='DEMO-003');

-- Completa el perfil de cuentas creadas con la version anterior del registro.
INSERT INTO pacientes(usuario_id,nombre,apellido)
SELECT ip.usuario_id,ip.nombre,ip.apellido FROM informacion_personal ip
JOIN usuario_roles ur ON ur.usuario_id=ip.usuario_id
JOIN roles r ON r.id=ur.rol_id AND r.nombre='paciente'
LEFT JOIN pacientes p ON p.usuario_id=ip.usuario_id
WHERE p.id IS NULL;

-- Control de frecuencia por cuenta; no almacena DNI consultado.
USE clinica_labendecida;
CREATE TABLE IF NOT EXISTS identidad_limites (
  usuario_id INT NOT NULL,
  minuto BIGINT NOT NULL,
  consultas INT NOT NULL DEFAULT 0,
  PRIMARY KEY(usuario_id, minuto),
  FOREIGN KEY(usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Documentos academicos sin validez tributaria; no elimina datos.
USE clinica_labendecida;
CREATE TABLE IF NOT EXISTS comprobantes_demo (
 id INT AUTO_INCREMENT PRIMARY KEY,
 cita_id INT NOT NULL UNIQUE,
 paciente_id INT NOT NULL,
 nombre_cliente VARCHAR(201) NOT NULL,
 concepto VARCHAR(200) NOT NULL,
 total_centimos INT NOT NULL,
 estado VARCHAR(40) NOT NULL,
 intentos INT NOT NULL DEFAULT 1,
 creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(cita_id) REFERENCES citas(id),
 FOREIGN KEY(paciente_id) REFERENCES pacientes(id)
) ENGINE=InnoDB;

USE clinica_labendecida;
CREATE TABLE IF NOT EXISTS cita_contexto (
 cita_id INT PRIMARY KEY,
 sede_id INT NOT NULL,
 modalidad VARCHAR(30) NOT NULL,
 tarifa_centimos INT NOT NULL,
 pagar_centimos INT NOT NULL,
 identidad_proveedor VARCHAR(30) NOT NULL,
 datos_sinteticos TINYINT(1) NOT NULL,
 FOREIGN KEY(cita_id) REFERENCES citas(id)
) ENGINE=InnoDB;
