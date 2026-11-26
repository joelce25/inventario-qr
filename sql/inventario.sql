CREATE DATABASE IF NOT EXISTS inventario_qr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventario_qr;

CREATE TABLE IF NOT EXISTS productos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(32) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    ubicacion VARCHAR(60) NOT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS movimientos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operacion CHAR(32) NOT NULL UNIQUE,
    producto_id INT UNSIGNED NOT NULL,
    tipo ENUM('entrada','salida') NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    saldo INT UNSIGNED NOT NULL,
    fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    INDEX (producto_id, id)
) ENGINE=InnoDB;

START TRANSACTION;
INSERT INTO productos (sku,nombre,ubicacion,stock) VALUES
 ('TEC-001','Teclado compacto','Estante A1',3),
 ('TEC-002','Mouse inalámbrico','Estante A2',8),
 ('TEC-003','Cable USB-C','Gaveta B1',12)
ON DUPLICATE KEY UPDATE sku = productos.sku;
INSERT INTO movimientos (operacion,producto_id,tipo,cantidad,saldo)
SELECT CONCAT(REPEAT('0',29), RIGHT(sku,3)),id,'entrada',stock,stock FROM productos
WHERE sku IN ('TEC-001','TEC-002','TEC-003')
ON DUPLICATE KEY UPDATE operacion = movimientos.operacion;
COMMIT;
