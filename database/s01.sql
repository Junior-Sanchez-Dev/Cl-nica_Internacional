-- Control de frecuencia por cuenta; no almacena DNI consultado.
USE clinica_labendecida;
CREATE TABLE IF NOT EXISTS identidad_limites (
  usuario_id INT NOT NULL,
  minuto BIGINT NOT NULL,
  consultas INT NOT NULL DEFAULT 0,
  PRIMARY KEY(usuario_id, minuto),
  FOREIGN KEY(usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;
