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
