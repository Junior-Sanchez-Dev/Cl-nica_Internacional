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
