-- =========================================================
-- Sistema de Gestión de Bodegas e Inventario
-- Schema reconstruido a partir del código fuente (sin datos reales).
-- Motor: MySQL/MariaDB, InnoDB, utf8mb4.
-- =========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS sistema_bodega CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistema_bodega;

-- ---------------------------------------------------------
-- unidades_organizacionales
-- ---------------------------------------------------------
CREATE TABLE unidades_organizacionales (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo     VARCHAR(20)  NOT NULL,
    nombre     VARCHAR(150) NOT NULL,
    estado     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_unidades_codigo (codigo)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- bodegas
-- ---------------------------------------------------------
CREATE TABLE bodegas (
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo                 VARCHAR(20)  NOT NULL,
    nombre                 VARCHAR(150) NOT NULL,
    id_unidad              INT UNSIGNED NULL,
    id_encargado           INT UNSIGNED NULL,
    descripcion            TEXT NULL,
    ubicacion_referencial  VARCHAR(255) NULL,
    responsable            VARCHAR(150) NULL,
    es_central             TINYINT(1) NOT NULL DEFAULT 0,
    estado                 TINYINT(1) NOT NULL DEFAULT 1,
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bodegas_codigo (codigo),
    KEY idx_bodegas_unidad (id_unidad),
    KEY idx_bodegas_encargado (id_encargado),
    CONSTRAINT fk_bodegas_unidad FOREIGN KEY (id_unidad) REFERENCES unidades_organizacionales (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- funcionarios
-- ---------------------------------------------------------
CREATE TABLE funcionarios (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo     VARCHAR(20)  NULL,
    rut        VARCHAR(15)  NOT NULL,
    nombre     VARCHAR(150) NOT NULL,
    id_unidad  INT UNSIGNED NULL,
    cargo      VARCHAR(150) NULL,
    programa   VARCHAR(150) NULL,
    email      VARCHAR(150) NULL,
    estado     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_funcionarios_rut (rut),
    KEY idx_funcionarios_unidad (id_unidad),
    CONSTRAINT fk_funcionarios_unidad FOREIGN KEY (id_unidad) REFERENCES unidades_organizacionales (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- usuarios
-- ---------------------------------------------------------
CREATE TABLE usuarios (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_funcionario INT UNSIGNED NULL,
    nombre         VARCHAR(150) NOT NULL,
    email          VARCHAR(150) NULL,
    usuario        VARCHAR(80)  NOT NULL,
    clave_hash     VARCHAR(255) NOT NULL,
    rol            ENUM('admin','bodega','solicitante') NOT NULL,
    id_bodega      INT UNSIGNED NULL,
    id_unidad      INT UNSIGNED NULL,
    estado         TINYINT(1)   NOT NULL DEFAULT 1,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuarios_usuario (usuario),
    KEY idx_usuarios_funcionario (id_funcionario),
    KEY idx_usuarios_bodega (id_bodega),
    KEY idx_usuarios_unidad (id_unidad),
    CONSTRAINT fk_usuarios_funcionario FOREIGN KEY (id_funcionario) REFERENCES funcionarios (id) ON DELETE SET NULL,
    CONSTRAINT fk_usuarios_bodega FOREIGN KEY (id_bodega) REFERENCES bodegas (id) ON DELETE SET NULL,
    CONSTRAINT fk_usuarios_unidad FOREIGN KEY (id_unidad) REFERENCES unidades_organizacionales (id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE bodegas
    ADD CONSTRAINT fk_bodegas_encargado FOREIGN KEY (id_encargado) REFERENCES usuarios (id) ON DELETE SET NULL;

-- ---------------------------------------------------------
-- usuarios_bodegas (M:N encargados <-> bodegas)
-- ---------------------------------------------------------
CREATE TABLE usuarios_bodegas (
    id_usuario   INT UNSIGNED NOT NULL,
    id_bodega    INT UNSIGNED NOT NULL,
    es_principal TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id_usuario, id_bodega),
    KEY idx_ub_bodega (id_bodega),
    CONSTRAINT fk_ub_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_ub_bodega  FOREIGN KEY (id_bodega)  REFERENCES bodegas (id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- unidades_medida
-- ---------------------------------------------------------
CREATE TABLE unidades_medida (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo      VARCHAR(10)  NOT NULL,
    nombre      VARCHAR(80)  NOT NULL,
    descripcion VARCHAR(255) NULL,
    estado      TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uq_um_codigo (codigo)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- tipos_producto
-- ---------------------------------------------------------
CREATE TABLE tipos_producto (
    id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_tp_nombre (nombre)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- productos
-- ---------------------------------------------------------
CREATE TABLE productos (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo           VARCHAR(30)  NOT NULL,
    nombre           VARCHAR(200) NOT NULL,
    descripcion      TEXT NULL,
    id_tipo_producto INT UNSIGNED NULL,
    id_unidad_medida INT UNSIGNED NULL,
    stock_minimo     DECIMAL(12,2) NOT NULL DEFAULT 0,
    activo_fijo      TINYINT(1) NOT NULL DEFAULT 0,
    controla_stock   TINYINT(1) NOT NULL DEFAULT 1,
    estado           TINYINT(1) NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_productos_codigo (codigo),
    KEY idx_productos_tipo (id_tipo_producto),
    KEY idx_productos_unidad_medida (id_unidad_medida),
    CONSTRAINT fk_productos_tipo FOREIGN KEY (id_tipo_producto) REFERENCES tipos_producto (id) ON DELETE SET NULL,
    CONSTRAINT fk_productos_unidad_medida FOREIGN KEY (id_unidad_medida) REFERENCES unidades_medida (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- proveedores
-- ---------------------------------------------------------
CREATE TABLE proveedores (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rut             VARCHAR(15)  NOT NULL,
    razon_social    VARCHAR(200) NOT NULL,
    nombre_fantasia VARCHAR(200) NULL,
    giro            VARCHAR(150) NULL,
    direccion       VARCHAR(255) NULL,
    comuna          VARCHAR(100) NULL,
    ciudad          VARCHAR(100) NULL,
    telefono        VARCHAR(30)  NULL,
    email           VARCHAR(150) NULL,
    contacto        VARCHAR(150) NULL,
    estado          TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_proveedores_rut (rut)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- ordenes_compra
-- ---------------------------------------------------------
CREATE TABLE ordenes_compra (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_oc          VARCHAR(30)  NOT NULL,
    id_proveedor       INT UNSIGNED NULL,
    fecha_oc           DATE NOT NULL,
    unidad_solicitante VARCHAR(150) NULL,
    centro_costo       VARCHAR(100) NULL,
    descripcion        TEXT NULL,
    monto_neto         DECIMAL(14,2) NOT NULL DEFAULT 0,
    monto_iva          DECIMAL(14,2) NOT NULL DEFAULT 0,
    monto_total        DECIMAL(14,2) NOT NULL DEFAULT 0,
    estado             ENUM('ingresada','anulada') NOT NULL DEFAULT 'ingresada',
    observacion        TEXT NULL,
    created_by         INT UNSIGNED NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_oc_numero (numero_oc),
    KEY idx_oc_proveedor (id_proveedor),
    KEY idx_oc_created_by (created_by),
    CONSTRAINT fk_oc_proveedor FOREIGN KEY (id_proveedor) REFERENCES proveedores (id) ON DELETE SET NULL,
    CONSTRAINT fk_oc_usuario   FOREIGN KEY (created_by)   REFERENCES usuarios (id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- ordenes_compra_detalle
-- ---------------------------------------------------------
CREATE TABLE ordenes_compra_detalle (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_orden_compra   INT UNSIGNED NOT NULL,
    id_producto       INT UNSIGNED NULL,
    descripcion_item  VARCHAR(255) NOT NULL,
    cantidad          DECIMAL(12,2) NOT NULL,
    precio_unitario   DECIMAL(14,2) NOT NULL,
    subtotal          DECIMAL(14,2) NOT NULL,
    KEY idx_ocd_oc (id_orden_compra),
    KEY idx_ocd_producto (id_producto),
    CONSTRAINT fk_ocd_oc       FOREIGN KEY (id_orden_compra) REFERENCES ordenes_compra (id) ON DELETE CASCADE,
    CONSTRAINT fk_ocd_producto FOREIGN KEY (id_producto)     REFERENCES productos (id)      ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- facturas
-- ---------------------------------------------------------
CREATE TABLE facturas (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_bodega        INT UNSIGNED NOT NULL,
    id_proveedor     INT UNSIGNED NULL,
    id_orden_compra  INT UNSIGNED NULL,
    numero_oc        VARCHAR(30)  NULL,
    numero_factura   VARCHAR(30)  NOT NULL,
    fecha_factura    DATE NOT NULL,
    fecha_recepcion  DATE NULL,
    monto_neto       DECIMAL(14,2) NOT NULL DEFAULT 0,
    monto_iva        DECIMAL(14,2) NOT NULL DEFAULT 0,
    monto_total      DECIMAL(14,2) NOT NULL DEFAULT 0,
    estado           ENUM('ingresada','anulada') NOT NULL DEFAULT 'ingresada',
    observacion      TEXT NULL,
    created_by       INT UNSIGNED NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_facturas_bodega (id_bodega),
    KEY idx_facturas_proveedor (id_proveedor),
    KEY idx_facturas_oc (id_orden_compra),
    KEY idx_facturas_created_by (created_by),
    CONSTRAINT fk_facturas_bodega     FOREIGN KEY (id_bodega)       REFERENCES bodegas (id)         ON DELETE CASCADE,
    CONSTRAINT fk_facturas_proveedor  FOREIGN KEY (id_proveedor)    REFERENCES proveedores (id)     ON DELETE SET NULL,
    CONSTRAINT fk_facturas_oc         FOREIGN KEY (id_orden_compra) REFERENCES ordenes_compra (id)  ON DELETE SET NULL,
    CONSTRAINT fk_facturas_usuario    FOREIGN KEY (created_by)      REFERENCES usuarios (id)        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- facturas_detalle
-- ---------------------------------------------------------
CREATE TABLE facturas_detalle (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_factura        INT UNSIGNED NOT NULL,
    id_producto       INT UNSIGNED NULL,
    descripcion_item  VARCHAR(255) NOT NULL,
    cantidad          DECIMAL(12,2) NOT NULL,
    precio_unitario   DECIMAL(14,2) NOT NULL,
    subtotal          DECIMAL(14,2) NOT NULL,
    KEY idx_fd_factura (id_factura),
    KEY idx_fd_producto (id_producto),
    CONSTRAINT fk_fd_factura  FOREIGN KEY (id_factura)  REFERENCES facturas (id)  ON DELETE CASCADE,
    CONSTRAINT fk_fd_producto FOREIGN KEY (id_producto) REFERENCES productos (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- stock_bodega
-- ---------------------------------------------------------
CREATE TABLE stock_bodega (
    id_bodega      INT UNSIGNED NOT NULL,
    id_producto    INT UNSIGNED NOT NULL,
    stock_actual   DECIMAL(12,2) NOT NULL DEFAULT 0,
    costo_promedio DECIMAL(14,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (id_bodega, id_producto),
    KEY idx_sb_producto (id_producto),
    CONSTRAINT fk_sb_bodega   FOREIGN KEY (id_bodega)   REFERENCES bodegas (id)   ON DELETE CASCADE,
    CONSTRAINT fk_sb_producto FOREIGN KEY (id_producto) REFERENCES productos (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- movimientos_bodega (kardex)
-- ---------------------------------------------------------
CREATE TABLE movimientos_bodega (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_bodega        INT UNSIGNED NOT NULL,
    id_producto      INT UNSIGNED NOT NULL,
    tipo_movimiento  ENUM(
                        'entrada_compra',
                        'salida_consumo',
                        'ajuste_entrada',
                        'ajuste_salida',
                        'traslado_entrada',
                        'traslado_salida'
                     ) NOT NULL,
    cantidad         DECIMAL(12,2) NOT NULL,
    precio_unitario  DECIMAL(14,2) NULL,
    total            DECIMAL(14,2) NULL,
    referencia_tipo  VARCHAR(30) NULL,
    referencia_id    INT UNSIGNED NULL,
    observacion      TEXT NULL,
    id_usuario       INT UNSIGNED NULL,
    fecha_movimiento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mb_bodega (id_bodega),
    KEY idx_mb_producto (id_producto),
    KEY idx_mb_usuario (id_usuario),
    KEY idx_mb_referencia (referencia_tipo, referencia_id),
    CONSTRAINT fk_mb_bodega   FOREIGN KEY (id_bodega)   REFERENCES bodegas (id)   ON DELETE CASCADE,
    CONSTRAINT fk_mb_producto FOREIGN KEY (id_producto) REFERENCES productos (id) ON DELETE CASCADE,
    CONSTRAINT fk_mb_usuario  FOREIGN KEY (id_usuario)  REFERENCES usuarios (id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- traspasos_bodega
-- ---------------------------------------------------------
CREATE TABLE traspasos_bodega (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_bodega_origen  INT UNSIGNED NOT NULL,
    id_bodega_destino INT UNSIGNED NOT NULL,
    fecha             DATE NOT NULL,
    estado            ENUM('borrador','pendiente','aprobada','ejecutada','completado','rechazada') NOT NULL DEFAULT 'completado',
    observacion       TEXT NULL,
    created_by        INT UNSIGNED NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tb_origen (id_bodega_origen),
    KEY idx_tb_destino (id_bodega_destino),
    KEY idx_tb_created_by (created_by),
    CONSTRAINT fk_tb_origen    FOREIGN KEY (id_bodega_origen)  REFERENCES bodegas (id) ON DELETE CASCADE,
    CONSTRAINT fk_tb_destino   FOREIGN KEY (id_bodega_destino) REFERENCES bodegas (id) ON DELETE CASCADE,
    CONSTRAINT fk_tb_usuario   FOREIGN KEY (created_by)        REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- traspasos_bodega_detalle
-- ---------------------------------------------------------
CREATE TABLE traspasos_bodega_detalle (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_traspaso       INT UNSIGNED NOT NULL,
    id_producto       INT UNSIGNED NOT NULL,
    descripcion_item  VARCHAR(255) NULL,
    cantidad          DECIMAL(12,2) NOT NULL,
    costo_unitario    DECIMAL(14,2) NULL,
    subtotal          DECIMAL(14,2) NULL,
    KEY idx_tbd_traspaso (id_traspaso),
    KEY idx_tbd_producto (id_producto),
    CONSTRAINT fk_tbd_traspaso FOREIGN KEY (id_traspaso) REFERENCES traspasos_bodega (id) ON DELETE CASCADE,
    CONSTRAINT fk_tbd_producto FOREIGN KEY (id_producto) REFERENCES productos (id)        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- solicitudes (consumo, solicitante -> bodega)
-- ---------------------------------------------------------
CREATE TABLE solicitudes (
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_solicitud       VARCHAR(30) NOT NULL DEFAULT 'PENDIENTE',
    id_bodega_origen       INT UNSIGNED NOT NULL,
    id_bodega_destino      INT UNSIGNED NULL,
    id_usuario             INT UNSIGNED NOT NULL,
    observacion            TEXT NULL,
    observacion_respuesta  TEXT NULL,
    id_usuario_respuesta   INT UNSIGNED NULL,
    fecha_respuesta        DATETIME NULL,
    dias_limite            INT UNSIGNED NOT NULL DEFAULT 3,
    fecha_limite           DATE NULL,
    estado                 ENUM('pendiente','en_revision','procesada','procesada_parcial','rechazada','caducada') NOT NULL DEFAULT 'pendiente',
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_sol_bodega_origen (id_bodega_origen),
    KEY idx_sol_bodega_destino (id_bodega_destino),
    KEY idx_sol_usuario (id_usuario),
    KEY idx_sol_estado (estado),
    CONSTRAINT fk_sol_bodega_origen  FOREIGN KEY (id_bodega_origen)  REFERENCES bodegas (id) ON DELETE CASCADE,
    CONSTRAINT fk_sol_bodega_destino FOREIGN KEY (id_bodega_destino) REFERENCES bodegas (id) ON DELETE SET NULL,
    CONSTRAINT fk_sol_usuario        FOREIGN KEY (id_usuario)        REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_sol_usuario_resp   FOREIGN KEY (id_usuario_respuesta) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- solicitudes_detalle
-- ---------------------------------------------------------
CREATE TABLE solicitudes_detalle (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_solicitud  INT UNSIGNED NOT NULL,
    id_producto   INT UNSIGNED NOT NULL,
    cantidad      DECIMAL(12,2) NOT NULL,
    cantidad_aprobada DECIMAL(12,2) NULL,
    observacion   TEXT NULL,
    estado        ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
    KEY idx_sd_solicitud (id_solicitud),
    KEY idx_sd_producto (id_producto),
    CONSTRAINT fk_sd_solicitud FOREIGN KEY (id_solicitud) REFERENCES solicitudes (id) ON DELETE CASCADE,
    CONSTRAINT fk_sd_producto  FOREIGN KEY (id_producto)  REFERENCES productos (id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- solicitudes_log (auditoría)
-- ---------------------------------------------------------
CREATE TABLE solicitudes_log (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_solicitud INT UNSIGNED NOT NULL,
    id_usuario   INT UNSIGNED NOT NULL DEFAULT 0,
    accion       VARCHAR(50) NOT NULL,
    detalle      TEXT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sl_solicitud (id_solicitud),
    CONSTRAINT fk_sl_solicitud FOREIGN KEY (id_solicitud) REFERENCES solicitudes (id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- Seed mínimo para arrancar en local
-- Genera tu propio hash antes de importar:
--   php -r "echo password_hash('admin123', PASSWORD_DEFAULT);"
-- Reemplaza REEMPLAZA_CON_TU_HASH abajo con el resultado.
-- =========================================================

INSERT INTO unidades_organizacionales (codigo, nombre, estado) VALUES
('U001', 'Unidad Central', 1);

INSERT INTO bodegas (codigo, nombre, id_unidad, descripcion, estado) VALUES
('B001', 'Bodega Principal', 1, 'Bodega de prueba para desarrollo local', 1);

INSERT INTO usuarios (id_funcionario, nombre, email, usuario, clave_hash, rol, id_bodega, id_unidad, estado) VALUES
(NULL, 'Administrador', 'admin@example.com', 'admin',
 'REEMPLAZA_CON_TU_HASH',
 'admin', NULL, NULL, 1);

INSERT INTO unidades_medida (codigo, nombre, descripcion, estado) VALUES
('UN', 'Unidad', 'Unidad genérica', 1),
('CJ', 'Caja', 'Caja', 1),
('KG', 'Kilogramo', 'Kilogramo', 1);

INSERT INTO tipos_producto (nombre, estado) VALUES
('Insumo de oficina', 1),
('Aseo', 1),
('Activo fijo', 1);