-- =========================================================
-- SEED DEMO — datos de prueba para el Sistema de Bodegas
-- =========================================================
-- ADVERTENCIA: este script VACÍA todas las tablas y carga un
-- set completo de datos de ejemplo para poder tomar capturas
-- y demostrar el sistema. No ejecutar sobre datos reales.
--
-- Cómo ejecutarlo (con la BD de XAMPP):
--   /opt/lampp/bin/mysql -u root < database/seed_demo.sql
--
-- Usuarios de ejemplo (todos con estado activo):
--   admin     / Admin123    (administrador)
--   atorres   / Demo1234    (encargada de Bodega Central)
--   lmorales  / Demo1234    (encargado de Bodega Principal)
--   evera     / Demo1234    (encargado de Bodega de Aseo)
--   msoto     / Demo1234    (solicitante, Finanzas)
--   crojas    / Demo1234    (solicitante, Compras)
--   vsalazar  / Demo1234    (solicitante, RRHH)
--
-- Las fechas se generan relativas a la fecha actual para que el
-- dashboard (gráfico 7 días, movimientos del mes, etc.) siempre
-- se vea reciente.
-- =========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
USE sistema_bodega;

-- ---------------------------------------------------------
-- Vaciar tablas (orden inverso de dependencias)
-- ---------------------------------------------------------
TRUNCATE TABLE solicitudes_log;
TRUNCATE TABLE solicitudes_detalle;
TRUNCATE TABLE solicitudes;
TRUNCATE TABLE traspasos_bodega_detalle;
TRUNCATE TABLE traspasos_bodega;
TRUNCATE TABLE movimientos_bodega;
TRUNCATE TABLE stock_bodega;
TRUNCATE TABLE facturas_detalle;
TRUNCATE TABLE facturas;
TRUNCATE TABLE ordenes_compra_detalle;
TRUNCATE TABLE ordenes_compra;
TRUNCATE TABLE proveedores;
TRUNCATE TABLE productos;
TRUNCATE TABLE tipos_producto;
TRUNCATE TABLE unidades_medida;
TRUNCATE TABLE usuarios_bodegas;
TRUNCATE TABLE usuarios;
TRUNCATE TABLE funcionarios;
TRUNCATE TABLE bodegas;
TRUNCATE TABLE unidades_organizacionales;
TRUNCATE TABLE configuraciones;

-- ---------------------------------------------------------
-- Maestros
-- ---------------------------------------------------------
INSERT INTO unidades_organizacionales (id, codigo, nombre, estado) VALUES
(1, 'U001', 'Gerencia General', 1),
(2, 'U002', 'Recursos Humanos', 1),
(3, 'U003', 'Finanzas', 1),
(4, 'U004', 'Logística', 1),
(5, 'U005', 'Operaciones', 1),
(6, 'U006', 'Compras', 1),
(7, 'U007', 'Informática', 1);

INSERT INTO bodegas (id, codigo, nombre, id_unidad, id_encargado, descripcion, ubicacion_referencial, responsable, es_central, estado) VALUES
(1, 'CENTRAL', 'Bodega Central', 4, 2, 'Bodega principal de recepción de compras y despacho a las demás bodegas.', 'Sector Patio 1', 'Ana Torres', 1, 1),
(2, 'B001', 'Bodega Principal', 4, 3, 'Bodega de abastecimiento general de oficinas.', 'Pasillo central', 'Luis Morales', 0, 1),
(3, 'B002', 'Bodega de Aseo', 5, 4, 'Almacenamiento de productos de aseo, higiene y limpieza.', 'Bodega contigua al comedor', 'Esteban Vera', 0, 1),
(4, 'B003', 'Bodega de Oficina', 2, 2, 'Insumos y materiales de escritorio para las oficinas.', 'Segundo piso, ala norte', 'Ana Torres', 0, 1);

INSERT INTO funcionarios (id, codigo, rut, nombre, id_unidad, cargo, programa, email, estado) VALUES
(1,  'F001', '12345678-2', 'Juan Pérez González',      2, 'Analista de RRHH',   'Gestión de Personas',   'jperez@demo.cl', 1),
(2,  'F002', '12345789-3', 'María Soto Rojas',         3, 'Contadora General',  'Gestión Financiera',    'msoto@demo.cl', 1),
(3,  'F003', '12398765-3', 'Pedro Díaz Muñoz',         5, 'Jefe de Operaciones','Operaciones',           'pdiaz@demo.cl', 1),
(4,  'F004', '98765432-8', 'Ana Torres Vera',          4, 'Encargada de Bodega','Abastecimiento',        'atorres@demo.cl', 1),
(5,  'F005', '98765543-9', 'Luis Morales Cid',         4, 'Bodeguero',          'Abastecimiento',        'lmorales@demo.cl', 1),
(6,  'F006', '55544332-9', 'Carolina Rojas Pino',      6, 'Analista de Compras','Abastecimiento',        'crojas@demo.cl', 1),
(7,  'F007', '12312312-5', 'Jorge Fuentes Lara',       7, 'Soporte Técnico',    'Tecnología',            'jfuentes@demo.cl', 1),
(8,  'F008', '87654321-7', 'Valentina Salazar Ortiz',  2, 'Asistente de RRHH',  'Gestión de Personas',   'vsalazar@demo.cl', 1),
(9,  'F009', '13579246-5', 'Ricardo Bravo Núñez',      5, 'Supervisor de Terreno', 'Operaciones',       'rbravo@demo.cl', 1),
(10, 'F010', '24681357-7', 'Claudia Mena Ríos',        3, 'Analista Financiera','Gestión Financiera',    'cmena@demo.cl', 1),
(11, 'F011', '15975346-0', 'Esteban Vera Castillo',    4, 'Auxiliar de Bodega', 'Abastecimiento',        'evera@demo.cl', 1),
(12, 'F012', '11223344-9', 'Patricia Campos Leiva',    6, 'Jefa de Compras',    'Abastecimiento',        'pcampos@demo.cl', 1);

INSERT INTO usuarios (id, id_funcionario, nombre, email, usuario, clave_hash, rol, id_bodega, id_unidad, estado) VALUES
(1, NULL, 'Administrador del Sistema', 'admin@demo.cl', 'admin',
 '$2y$10$gpyBApi2mJw3AobkDdkOr.s0tDuMBQ.YaC1xwCKoqzvak8t0TgS26', 'admin', NULL, NULL, 1),
(2, 4,  'Ana Torres Vera',        'atorres@demo.cl',  'atorres',
 '$2y$10$C1FYJJipQPuwNCZyRk.Yaedjif0hnd1Fgw0rBeEaWcTj5ACHvDNwK', 'bodega', 1, 4, 1),
(3, 5,  'Luis Morales Cid',       'lmorales@demo.cl', 'lmorales',
 '$2y$10$C1FYJJipQPuwNCZyRk.Yaedjif0hnd1Fgw0rBeEaWcTj5ACHvDNwK', 'bodega', 2, 4, 1),
(4, 11, 'Esteban Vera Castillo',  'evera@demo.cl',     'evera',
 '$2y$10$C1FYJJipQPuwNCZyRk.Yaedjif0hnd1Fgw0rBeEaWcTj5ACHvDNwK', 'bodega', 3, 4, 1),
(5, 2,  'María Soto Rojas',       'msoto@demo.cl',     'msoto',
 '$2y$10$C1FYJJipQPuwNCZyRk.Yaedjif0hnd1Fgw0rBeEaWcTj5ACHvDNwK', 'solicitante', NULL, 3, 1),
(6, 6,  'Carolina Rojas Pino',    'crojas@demo.cl',    'crojas',
 '$2y$10$C1FYJJipQPuwNCZyRk.Yaedjif0hnd1Fgw0rBeEaWcTj5ACHvDNwK', 'solicitante', NULL, 6, 1),
(7, 8,  'Valentina Salazar Ortiz','vsalazar@demo.cl',  'vsalazar',
 '$2y$10$C1FYJJipQPuwNCZyRk.Yaedjif0hnd1Fgw0rBeEaWcTj5ACHvDNwK', 'solicitante', NULL, 2, 1);

INSERT INTO usuarios_bodegas (id_usuario, id_bodega, es_principal) VALUES
(2, 1, 1),
(2, 4, 0),
(3, 2, 1),
(3, 1, 0),
(4, 3, 1);

INSERT INTO unidades_medida (id, codigo, nombre, descripcion, estado) VALUES
(1, 'UN', 'Unidad',      'Unidad genérica', 1),
(2, 'CJ', 'Caja',        'Caja cerrada', 1),
(3, 'KG', 'Kilogramo',   'Peso en kilogramos', 1),
(4, 'LT', 'Litro',       'Volumen en litros', 1),
(5, 'RL', 'Rollo',       'Rollo', 1);

INSERT INTO tipos_producto (id, nombre, estado) VALUES
(1, 'Insumo de oficina', 1),
(2, 'Aseo e higiene', 1),
(3, 'Activo fijo', 1),
(4, 'Computación', 1),
(5, 'Ferretería y seguridad', 1),
(6, 'Abarrotes y consumo', 1);

INSERT INTO proveedores (id, rut, razon_social, nombre_fantasia, giro, direccion, comuna, ciudad, telefono, email, contacto, estado) VALUES
(1, '76432123-K', 'Comercial Andina Ltda.',    'Comercial Andina',    'Venta de artículos de oficina',  'Av. Libertad 1234',    'Santiago',     'Santiago',    '+56 2 2111 2222', 'ventas@comercialandina.cl',  'Rodrigo Fuentes', 1),
(2, '76234567-0', 'Distribuidora del Sur S.A.','Distribuidora del Sur','Distribución de productos de aseo','Calle Rengo 456',     'Concepción',   'Concepción',  '+56 41 2777 8888', 'contacto@delsur.cl',        'Paola Vidal',   1),
(3, '77345678-8', 'Alimentos Norte SpA',       'Alimentos Norte',     'Comercialización de alimentos y bebidas','Ruta 5 Norte km 12','La Serena', 'La Serena',   '+56 51 2444 5555', 'pedidos@alimentosnorte.cl', 'Diego Pizarro', 1),
(4, '78345678-0', 'Servicios Integrales MTG',  'MTG Servicios',       'Suministros y servicios integrales','Av. Errázuriz 789',   'Valparaíso',   'Valparaíso',  '+56 32 2333 4444', 'info@mtgservicios.cl',      'Camila Andrade', 1),
(5, '79456789-8', 'Ferretería Industrial Los Andes','Ferretería Los Andes','Ferretería e insumos industriales','Los Carrera 321',    'Temuco',       'Temuco',      '+56 45 2222 3333', 'ventas@ferreterialosandes.cl','Héctor Rivas', 1),
(6, '80567890-4', 'Tecno Import Chile SpA',    'TecnoImport',         'Importación de equipos tecnológicos','Av. San Martín 654',  'Viña del Mar', 'Viña del Mar','+56 32 2666 7777', 'contacto@tecnoimport.cl',   'Natalia Soto',  1),
(7, '81678901-5', 'Papelera del Norte',        'Papelera del Norte',  'Artículos de escritorio',        'Calle Matta 987',      'Antofagasta',  'Antofagasta', '+56 55 2555 6666', 'ventas@papeleranorte.cl',   'Sergio Vega',   1);

INSERT INTO productos (id, codigo, nombre, descripcion, id_tipo_producto, id_unidad_medida, stock_minimo, activo_fijo, controla_stock, estado) VALUES
(1,  'PROD-0001', 'Resma de papel carta 500 hojas', 'Papel bond 75 gr, formato carta.', 1, 1, 20.00, 0, 1, 1),
(2,  'PROD-0002', 'Tóner impresora HP 85A',         'Cartucho compatible HP CE285A.', 4, 1, 5.00, 0, 1, 1),
(3,  'PROD-0003', 'Lápiz pasta azul',               'Punta fina 0,7 mm.', 1, 1, 50.00, 0, 1, 1),
(4,  'PROD-0004', 'Cuaderno universitario 100 hojas','Cuaderno cuadriculado tapa blanda.', 1, 1, 30.00, 0, 1, 1),
(5,  'PROD-0005', 'Caja de clip metálico',          'Clip estándar, caja de 100 unidades.', 1, 2, 40.00, 0, 1, 1),
(6,  'PROD-0006', 'Detergente en polvo 5 kg',       'Detergente multiuso para pisos.', 2, 1, 15.00, 0, 1, 1),
(7,  'PROD-0007', 'Papel higiénico doble hoja',     'Caja con 12 rollos de 200 m.', 2, 2, 25.00, 0, 1, 1),
(8,  'PROD-0008', 'Bolsas de basura 60 L',          'Paquete de 10 bolsas reforzadas.', 2, 2, 20.00, 0, 1, 1),
(9,  'PROD-0009', 'Desinfectante multiusos 1 L',    'Limpiador desinfectante con aroma cítrico.', 2, 4, 12.00, 0, 1, 1),
(10, 'PROD-0010', 'Alcohol gel 500 ml',             'Alcohol en gel antibacterial 70%.', 2, 1, 20.00, 0, 1, 1),
(11, 'PROD-0011', 'Notebook Lenovo ThinkPad E14',   'Intel i5, 16 GB RAM, SSD 512 GB.', 4, 1, 2.00, 1, 1, 1),
(12, 'PROD-0012', 'Monitor LG 24 pulgadas',         'Full HD, puerto HDMI y VGA.', 4, 1, 3.00, 1, 1, 1),
(13, 'PROD-0013', 'Silla ergonómica',               'Con soporte lumbar y apoyabrazos.', 3, 1, 4.00, 1, 1, 1),
(14, 'PROD-0014', 'Escritorio ejecutivo 1,2 m',     'Melamina con cajonera lateral.', 3, 1, 3.00, 1, 1, 1),
(15, 'PROD-0015', 'Extintor 5 kg ABC',              'Polvo químico seco, con soporte.', 5, 1, 6.00, 0, 1, 1),
(16, 'PROD-0016', 'Cinta adhesiva transparente',    'Rollo 12 mm x 66 m.', 1, 5, 40.00, 0, 1, 1),
(17, 'PROD-0017', 'Caja de corchetes',              'Corchete metálico N° 26, caja de 5000.', 1, 2, 30.00, 0, 1, 1),
(18, 'PROD-0018', 'Agua mineral 6 x 1,5 L',         'Pack de botellas sin gas.', 6, 2, 15.00, 0, 1, 1),
(19, 'PROD-0019', 'Café instantáneo 200 g',         'Café soluble clásico.', 6, 1, 10.00, 0, 1, 1),
(20, 'PROD-0020', 'Azúcar 1 kg',                    'Azúcar refinada granulada.', 6, 3, 8.00, 0, 1, 1);

-- ---------------------------------------------------------
-- Órdenes de compra
-- ---------------------------------------------------------
INSERT INTO ordenes_compra (id, numero_oc, id_proveedor, fecha_oc, unidad_solicitante, centro_costo, descripcion, monto_neto, monto_iva, monto_total, estado, observacion, created_by) VALUES
(1, 'OC-2026-0001', 1, DATE_SUB(CURDATE(), INTERVAL 48 DAY), 'Compras', 'CC-OP-01', 'Abastecimiento de insumos de oficina', 473750.00, 90012.50, 563762.50, 'ingresada', 'Compra trimestral de insumos de escritorio.', 1),
(2, 'OC-2026-0002', 2, DATE_SUB(CURDATE(), INTERVAL 38 DAY), 'Logística', 'CC-AS-02', 'Compra de artículos de aseo e higiene', 845500.00, 160645.00, 1006145.00, 'ingresada', NULL, 1),
(3, 'OC-2026-0003', 6, DATE_SUB(CURDATE(), INTERVAL 28 DAY), 'Informática', 'CC-INF-03', 'Renovación de equipos de computación', 3944200.00, 749398.00, 4693598.00, 'ingresada', 'Adjudicación licitación pública.', 1),
(4, 'OC-2026-0004', 5, DATE_SUB(CURDATE(), INTERVAL 18 DAY), 'Operaciones', 'CC-OP-04', 'Mobiliario y equipos de seguridad', 1950500.00, 370595.00, 2321095.00, 'ingresada', NULL, 1),
(5, 'OC-2026-0005', 3, DATE_SUB(CURDATE(), INTERVAL 8 DAY), 'Compras', 'CC-AL-05', 'Stock de café y agua para salas de reunión', 276500.00, 52535.00, 329035.00, 'ingresada', NULL, 1),
(6, 'OC-2026-0006', 4, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Compras', 'CC-AL-05', 'Compra adicional no concretada', 109000.00, 20710.00, 129710.00, 'anulada', 'Orden anulada por incumplimiento del proveedor.', 1);

INSERT INTO ordenes_compra_detalle (id_orden_compra, id_producto, descripcion_item, cantidad, precio_unitario, subtotal) VALUES
(1, 1,  'Resma de papel carta 500 hojas',      30.00, 3900.00,  117000.00),
(1, 2,  'Tóner impresora HP 85A',              6.00,  18500.00, 111000.00),
(1, 3,  'Lápiz pasta azul',                    100.00, 450.00,  45000.00),
(1, 4,  'Cuaderno universitario 100 hojas',    60.00, 2400.00, 144000.00),
(1, 5,  'Caja de clip metálico',               25.00, 950.00,  23750.00),
(1, 16, 'Cinta adhesiva transparente',         30.00, 700.00,  21000.00),
(1, 17, 'Caja de corchetes',                   20.00, 600.00,  12000.00),
(2, 6,  'Detergente en polvo 5 kg',            40.00, 4800.00, 192000.00),
(2, 7,  'Papel higiénico doble hoja',          60.00, 5900.00, 354000.00),
(2, 8,  'Bolsas de basura 60 L',               50.00, 2900.00, 145000.00),
(2, 9,  'Desinfectante multiusos 1 L',         30.00, 2350.00, 70500.00),
(2, 10, 'Alcohol gel 500 ml',                  40.00, 2100.00, 84000.00),
(3, 11, 'Notebook Lenovo ThinkPad E14',        8.00,  389900.00, 3119200.00),
(3, 12, 'Monitor LG 24 pulgadas',              10.00, 82500.00, 825000.00),
(4, 15, 'Extintor 5 kg ABC',                   15.00, 28900.00, 433500.00),
(4, 13, 'Silla ergonómica',                    10.00, 74900.00, 749000.00),
(4, 14, 'Escritorio ejecutivo 1,2 m',          6.00,  128000.00, 768000.00),
(5, 18, 'Agua mineral 6 x 1,5 L',              40.00, 3200.00, 128000.00),
(5, 19, 'Café instantáneo 200 g',              25.00, 4500.00, 112500.00),
(5, 20, 'Azúcar 1 kg',                         30.00, 1200.00, 36000.00),
(6, 18, 'Agua mineral 6 x 1,5 L',              20.00, 3200.00, 64000.00),
(6, 19, 'Café instantáneo 200 g',              10.00, 4500.00, 45000.00);

-- ---------------------------------------------------------
-- Facturas (todas ingresadas: alimentan stock de la central)
-- ---------------------------------------------------------
INSERT INTO facturas (id, id_bodega, id_proveedor, id_orden_compra, numero_oc, numero_factura, fecha_factura, fecha_recepcion, monto_neto, monto_iva, monto_total, estado, observacion, created_by) VALUES
(1, 1, 1, 1, 'OC-2026-0001', '1012345', DATE_SUB(CURDATE(), INTERVAL 47 DAY), DATE_SUB(CURDATE(), INTERVAL 47 DAY), 473750.00, 90012.50, 563762.50, 'ingresada', NULL, 1),
(2, 1, 2, 2, 'OC-2026-0002', '1023456', DATE_SUB(CURDATE(), INTERVAL 37 DAY), DATE_SUB(CURDATE(), INTERVAL 37 DAY), 845500.00, 160645.00, 1006145.00, 'ingresada', NULL, 1),
(3, 1, 6, 3, 'OC-2026-0003', '1034567', DATE_SUB(CURDATE(), INTERVAL 27 DAY), DATE_SUB(CURDATE(), INTERVAL 27 DAY), 3944200.00, 749398.00, 4693598.00, 'ingresada', 'Equipos recepcionados y verificados por Informática.', 1),
(4, 1, 5, 4, 'OC-2026-0004', '1045678', DATE_SUB(CURDATE(), INTERVAL 17 DAY), DATE_SUB(CURDATE(), INTERVAL 17 DAY), 1950500.00, 370595.00, 2321095.00, 'ingresada', NULL, 1),
(5, 1, 3, 5, 'OC-2026-0005', '1056789', DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_SUB(CURDATE(), INTERVAL 7 DAY), 276500.00, 52535.00, 329035.00, 'ingresada', NULL, 1);

INSERT INTO facturas_detalle (id_factura, id_producto, descripcion_item, cantidad, precio_unitario, subtotal) VALUES
(1, 1,  'Resma de papel carta 500 hojas',      30.00, 3900.00,  117000.00),
(1, 2,  'Tóner impresora HP 85A',              6.00,  18500.00, 111000.00),
(1, 3,  'Lápiz pasta azul',                    100.00, 450.00,  45000.00),
(1, 4,  'Cuaderno universitario 100 hojas',    60.00, 2400.00, 144000.00),
(1, 5,  'Caja de clip metálico',               25.00, 950.00,  23750.00),
(1, 16, 'Cinta adhesiva transparente',         30.00, 700.00,  21000.00),
(1, 17, 'Caja de corchetes',                   20.00, 600.00,  12000.00),
(2, 6,  'Detergente en polvo 5 kg',            40.00, 4800.00, 192000.00),
(2, 7,  'Papel higiénico doble hoja',          60.00, 5900.00, 354000.00),
(2, 8,  'Bolsas de basura 60 L',               50.00, 2900.00, 145000.00),
(2, 9,  'Desinfectante multiusos 1 L',         30.00, 2350.00, 70500.00),
(2, 10, 'Alcohol gel 500 ml',                  40.00, 2100.00, 84000.00),
(3, 11, 'Notebook Lenovo ThinkPad E14',        8.00,  389900.00, 3119200.00),
(3, 12, 'Monitor LG 24 pulgadas',              10.00, 82500.00, 825000.00),
(4, 15, 'Extintor 5 kg ABC',                   15.00, 28900.00, 433500.00),
(4, 13, 'Silla ergonómica',                    10.00, 74900.00, 749000.00),
(4, 14, 'Escritorio ejecutivo 1,2 m',          6.00,  128000.00, 768000.00),
(5, 18, 'Agua mineral 6 x 1,5 L',              40.00, 3200.00, 128000.00),
(5, 19, 'Café instantáneo 200 g',              25.00, 4500.00, 112500.00),
(5, 20, 'Azúcar 1 kg',                         30.00, 1200.00, 36000.00);

-- ---------------------------------------------------------
-- Traspasos entre bodegas
-- ---------------------------------------------------------
INSERT INTO traspasos_bodega (id, id_bodega_origen, id_bodega_destino, fecha, estado, observacion, created_by) VALUES
(1, 1, 3, DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'completado', 'Reposición de artículos de aseo a la bodega de aseo.', 2),
(2, 1, 4, DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'completado', 'Reposición de insumos de oficina a la bodega de oficina.', 2),
(3, 1, 2, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'ejecutada', 'Café y agua para la bodega principal.', 3),
(4, 1, 4, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'pendiente', 'Papel y corchetes para la bodega de oficina.', 2),
(5, 1, 2, CURDATE(), 'borrador', NULL, 3);

INSERT INTO traspasos_bodega_detalle (id_traspaso, id_producto, descripcion_item, cantidad, costo_unitario, subtotal) VALUES
(1, 6,  'Detergente en polvo 5 kg',            10.00, 4800.00, 48000.00),
(1, 9,  'Desinfectante multiusos 1 L',         10.00, 2350.00, 23500.00),
(1, 8,  'Bolsas de basura 60 L',               20.00, 2900.00, 58000.00),
(1, 10, 'Alcohol gel 500 ml',                  15.00, 2100.00, 31500.00),
(1, 7,  'Papel higiénico doble hoja',          20.00, 5900.00, 118000.00),
(2, 1,  'Resma de papel carta 500 hojas',      15.00, 3900.00, 58500.00),
(2, 3,  'Lápiz pasta azul',                    30.00, 450.00,  13500.00),
(2, 4,  'Cuaderno universitario 100 hojas',    20.00, 2400.00, 48000.00),
(2, 5,  'Caja de clip metálico',               10.00, 950.00,  9500.00),
(2, 16, 'Cinta adhesiva transparente',         15.00, 700.00,  10500.00),
(2, 17, 'Caja de corchetes',                   10.00, 600.00,  6000.00),
(3, 19, 'Café instantáneo 200 g',              5.00,  4500.00, 22500.00),
(3, 18, 'Agua mineral 6 x 1,5 L',              6.00,  3200.00, 19200.00),
(4, 1,  'Resma de papel carta 500 hojas',      10.00, 3900.00, 39000.00),
(4, 17, 'Caja de corchetes',                   5.00,  600.00,  3000.00),
(5, 12, 'Monitor LG 24 pulgadas',              2.00,  82500.00, 165000.00);

-- ---------------------------------------------------------
-- Solicitudes de consumo
-- ---------------------------------------------------------
INSERT INTO solicitudes (id, numero_solicitud, id_bodega_origen, id_bodega_destino, id_usuario, observacion, observacion_respuesta, id_usuario_respuesta, fecha_respuesta, dias_limite, fecha_limite, estado, created_at) VALUES
(1, 'SOL-2026-00001', 1, 2, 5, 'Agua para la sala de reuniones de Finanzas.', NULL, NULL, NULL, 3, DATE_SUB(CURDATE(), INTERVAL 17 DAY), 'caducada', DATE_SUB(NOW(), INTERVAL 20 DAY)),
(2, 'SOL-2026-00002', 1, 2, 5, 'Insumos para la oficina de Finanzas.', 'Solicitud atendida en su totalidad.', 2, DATE_SUB(NOW(), INTERVAL 6 DAY), 3, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'procesada', DATE_SUB(NOW(), INTERVAL 6 DAY)),
(3, 'SOL-2026-00003', 1, 2, 7, 'Materiales para las oficinas de RRHH.', 'Atendida parcialmente, sin stock de cinta.', 2, DATE_SUB(NOW(), INTERVAL 3 DAY), 3, CURDATE(), 'procesada_parcial', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(4, 'SOL-2026-00004', 1, 4, 6, 'Cuadernos y papel para el área de Compras.', 'Solicitud ejecutada.', 2, DATE_SUB(NOW(), INTERVAL 4 DAY), 5, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'procesada', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(5, 'SOL-2026-00005', 1, 2, 7, 'Un notebook para la nueva asistente de RRHH.', 'Solicitud duplicada, el equipo ya fue asignado.', 2, DATE_SUB(NOW(), INTERVAL 4 DAY), 3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'rechazada', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(6, 'SOL-2026-00006', 1, 4, 6, 'Papel y cinta para la bodega de oficina.', NULL, NULL, NULL, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'en_revision', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(7, 'SOL-2026-00007', 1, 3, 5, 'Artículos de aseo para reponer la bodega de aseo.', NULL, NULL, NULL, 3, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'pendiente', NOW()),
(8, 'SOL-2026-00008', 1, 4, 6, 'Agua y azúcar para la sala de reuniones.', 'Ejecutada hoy.', 2, NOW(), 4, DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'procesada', NOW());

INSERT INTO solicitudes_detalle (id_solicitud, id_producto, cantidad, cantidad_aprobada, observacion, motivo_ajuste, estado) VALUES
(1, 18, 12.00, NULL, 'Para consumo interno.', NULL, 'pendiente'),
(2, 1,  3.00, 3.00, NULL, NULL, 'aprobado'),
(2, 3,  10.00, 10.00, NULL, NULL, 'aprobado'),
(2, 16, 3.00, 3.00, NULL, NULL, 'aprobado'),
(2, 19, 2.00, 2.00, NULL, NULL, 'aprobado'),
(3, 2,  2.00, 2.00, 'Tóner para impresora de la oficina.', NULL, 'aprobado'),
(3, 7,  5.00, 5.00, NULL, NULL, 'aprobado'),
(3, 16, 5.00, NULL, NULL, 'Ítem sin stock disponible en bodega.', 'rechazado'),
(4, 1,  5.00, 5.00, NULL, NULL, 'aprobado'),
(4, 4,  10.00, 10.00, NULL, NULL, 'aprobado'),
(4, 17, 5.00, 5.00, NULL, NULL, 'aprobado'),
(5, 11, 1.00, NULL, NULL, 'Solicitud duplicada, equipo ya asignado.', 'rechazado'),
(6, 1,  10.00, NULL, NULL, NULL, 'pendiente'),
(6, 4,  5.00, NULL, NULL, NULL, 'pendiente'),
(6, 16, 4.00, NULL, NULL, NULL, 'pendiente'),
(7, 6,  5.00, NULL, 'Detergente para limpieza de oficinas.', NULL, 'pendiente'),
(7, 9,  10.00, NULL, NULL, NULL, 'pendiente'),
(7, 10, 8.00, NULL, NULL, NULL, 'pendiente'),
(8, 18, 4.00, 4.00, NULL, NULL, 'aprobado'),
(8, 20, 3.00, 3.00, NULL, NULL, 'aprobado');

INSERT INTO solicitudes_log (id_solicitud, id_usuario, accion, detalle, created_at) VALUES
(1, 5,  'creada',    'Solicitud creada por María Soto Rojas.', DATE_SUB(NOW(), INTERVAL 20 DAY)),
(1, 2,  'caducada',  'Caducada automáticamente. Fecha límite: vencida.', DATE_SUB(NOW(), INTERVAL 17 DAY)),
(2, 5,  'creada',    'Solicitud creada por María Soto Rojas.', DATE_SUB(NOW(), INTERVAL 6 DAY)),
(2, 2,  'ejecutada', '4 de 4 ítems ejecutados. Estado: procesada.', DATE_SUB(NOW(), INTERVAL 6 DAY)),
(3, 7,  'creada',    'Solicitud creada por Valentina Salazar Ortiz.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3, 2,  'ejecutada', '2 de 3 ítems ejecutados. Estado: procesada_parcial.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(4, 6,  'creada',    'Solicitud creada por Carolina Rojas Pino.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(4, 2,  'ejecutada', '3 de 3 ítems ejecutados. Estado: procesada.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(5, 7,  'creada',    'Solicitud creada por Valentina Salazar Ortiz.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(5, 2,  'rechazada', 'Solicitud duplicada, el equipo ya fue asignado.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(6, 6,  'creada',    'Solicitud creada por Carolina Rojas Pino.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(7, 5,  'creada',    'Solicitud creada por María Soto Rojas.', NOW()),
(8, 6,  'creada',    'Solicitud creada por Carolina Rojas Pino.', NOW()),
(8, 2,  'ejecutada', '2 de 2 ítems ejecutados. Estado: procesada.', NOW());

-- ---------------------------------------------------------
-- Kardex (movimientos de bodega)
-- ---------------------------------------------------------
-- Entradas por factura (Bodega Central)
INSERT INTO movimientos_bodega (id_bodega, id_producto, tipo_movimiento, cantidad, precio_unitario, total, referencia_tipo, referencia_id, observacion, id_usuario, fecha_movimiento) VALUES
(1, 1,  'entrada_compra', 30.00, 3900.00,  117000.00, 'factura', 1, 'Ingreso por factura N° 1012345', 1, DATE_SUB(NOW(), INTERVAL 48 DAY)),
(1, 2,  'entrada_compra', 6.00,  18500.00, 111000.00, 'factura', 1, 'Ingreso por factura N° 1012345', 1, DATE_SUB(NOW(), INTERVAL 48 DAY)),
(1, 3,  'entrada_compra', 100.00, 450.00,  45000.00,  'factura', 1, 'Ingreso por factura N° 1012345', 1, DATE_SUB(NOW(), INTERVAL 48 DAY)),
(1, 4,  'entrada_compra', 60.00, 2400.00, 144000.00, 'factura', 1, 'Ingreso por factura N° 1012345', 1, DATE_SUB(NOW(), INTERVAL 48 DAY)),
(1, 5,  'entrada_compra', 25.00, 950.00,  23750.00,  'factura', 1, 'Ingreso por factura N° 1012345', 1, DATE_SUB(NOW(), INTERVAL 48 DAY)),
(1, 16, 'entrada_compra', 30.00, 700.00,  21000.00,  'factura', 1, 'Ingreso por factura N° 1012345', 1, DATE_SUB(NOW(), INTERVAL 48 DAY)),
(1, 17, 'entrada_compra', 20.00, 600.00,  12000.00,  'factura', 1, 'Ingreso por factura N° 1012345', 1, DATE_SUB(NOW(), INTERVAL 48 DAY)),
(1, 6,  'entrada_compra', 40.00, 4800.00, 192000.00, 'factura', 2, 'Ingreso por factura N° 1023456', 1, DATE_SUB(NOW(), INTERVAL 38 DAY)),
(1, 7,  'entrada_compra', 60.00, 5900.00, 354000.00, 'factura', 2, 'Ingreso por factura N° 1023456', 1, DATE_SUB(NOW(), INTERVAL 38 DAY)),
(1, 8,  'entrada_compra', 50.00, 2900.00, 145000.00, 'factura', 2, 'Ingreso por factura N° 1023456', 1, DATE_SUB(NOW(), INTERVAL 38 DAY)),
(1, 9,  'entrada_compra', 30.00, 2350.00, 70500.00,  'factura', 2, 'Ingreso por factura N° 1023456', 1, DATE_SUB(NOW(), INTERVAL 38 DAY)),
(1, 10, 'entrada_compra', 40.00, 2100.00, 84000.00,  'factura', 2, 'Ingreso por factura N° 1023456', 1, DATE_SUB(NOW(), INTERVAL 38 DAY)),
(1, 11, 'entrada_compra', 8.00,  389900.00, 3119200.00, 'factura', 3, 'Ingreso por factura N° 1034567', 1, DATE_SUB(NOW(), INTERVAL 28 DAY)),
(1, 12, 'entrada_compra', 10.00, 82500.00, 825000.00, 'factura', 3, 'Ingreso por factura N° 1034567', 1, DATE_SUB(NOW(), INTERVAL 28 DAY)),
(1, 15, 'entrada_compra', 15.00, 28900.00, 433500.00, 'factura', 4, 'Ingreso por factura N° 1045678', 1, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(1, 13, 'entrada_compra', 10.00, 74900.00, 749000.00, 'factura', 4, 'Ingreso por factura N° 1045678', 1, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(1, 14, 'entrada_compra', 6.00,  128000.00, 768000.00, 'factura', 4, 'Ingreso por factura N° 1045678', 1, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(1, 18, 'entrada_compra', 40.00, 3200.00, 128000.00, 'factura', 5, 'Ingreso por factura N° 1056789', 1, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(1, 19, 'entrada_compra', 25.00, 4500.00, 112500.00, 'factura', 5, 'Ingreso por factura N° 1056789', 1, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(1, 20, 'entrada_compra', 30.00, 1200.00, 36000.00,  'factura', 5, 'Ingreso por factura N° 1056789', 1, DATE_SUB(NOW(), INTERVAL 8 DAY));

-- Traspaso 1 (Central -> Bodega de Aseo)
INSERT INTO movimientos_bodega (id_bodega, id_producto, tipo_movimiento, cantidad, precio_unitario, total, referencia_tipo, referencia_id, observacion, id_usuario, fecha_movimiento) VALUES
(1, 6,  'traslado_salida',  10.00, 4800.00, 48000.00, 'traslado', 1, 'Traspaso a Bodega de Aseo', 2, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(1, 9,  'traslado_salida',  10.00, 2350.00, 23500.00, 'traslado', 1, 'Traspaso a Bodega de Aseo', 2, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(1, 8,  'traslado_salida',  20.00, 2900.00, 58000.00, 'traslado', 1, 'Traspaso a Bodega de Aseo', 2, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(1, 10, 'traslado_salida',  15.00, 2100.00, 31500.00, 'traslado', 1, 'Traspaso a Bodega de Aseo', 2, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(1, 7,  'traslado_salida',  20.00, 5900.00, 118000.00, 'traslado', 1, 'Traspaso a Bodega de Aseo', 2, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(3, 6,  'traslado_entrada', 10.00, 4800.00, 48000.00, 'traslado', 1, 'Traspaso a Bodega de Aseo', 2, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(3, 9,  'traslado_entrada', 10.00, 2350.00, 23500.00, 'traslado', 1, 'Traspaso a Bodega de Aseo', 2, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(3, 8,  'traslado_entrada', 20.00, 2900.00, 58000.00, 'traslado', 1, 'Traspaso a Bodega de Aseo', 2, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(3, 10, 'traslado_entrada', 15.00, 2100.00, 31500.00, 'traslado', 1, 'Traspaso a Bodega de Aseo', 2, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(3, 7,  'traslado_entrada', 20.00, 5900.00, 118000.00, 'traslado', 1, 'Traspaso a Bodega de Aseo', 2, DATE_SUB(NOW(), INTERVAL 15 DAY));

-- Traspaso 2 (Central -> Bodega de Oficina)
INSERT INTO movimientos_bodega (id_bodega, id_producto, tipo_movimiento, cantidad, precio_unitario, total, referencia_tipo, referencia_id, observacion, id_usuario, fecha_movimiento) VALUES
(1, 1,  'traslado_salida',  15.00, 3900.00, 58500.00, 'traslado', 2, 'Traspaso a Bodega de Oficina', 2, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(1, 3,  'traslado_salida',  30.00, 450.00,  13500.00, 'traslado', 2, 'Traspaso a Bodega de Oficina', 2, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(1, 4,  'traslado_salida',  20.00, 2400.00, 48000.00, 'traslado', 2, 'Traspaso a Bodega de Oficina', 2, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(1, 5,  'traslado_salida',  10.00, 950.00,  9500.00,  'traslado', 2, 'Traspaso a Bodega de Oficina', 2, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(1, 16, 'traslado_salida',  15.00, 700.00,  10500.00, 'traslado', 2, 'Traspaso a Bodega de Oficina', 2, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(1, 17, 'traslado_salida',  10.00, 600.00,  6000.00,  'traslado', 2, 'Traspaso a Bodega de Oficina', 2, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(4, 1,  'traslado_entrada', 15.00, 3900.00, 58500.00, 'traslado', 2, 'Traspaso a Bodega de Oficina', 2, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(4, 3,  'traslado_entrada', 30.00, 450.00,  13500.00, 'traslado', 2, 'Traspaso a Bodega de Oficina', 2, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(4, 4,  'traslado_entrada', 20.00, 2400.00, 48000.00, 'traslado', 2, 'Traspaso a Bodega de Oficina', 2, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(4, 5,  'traslado_entrada', 10.00, 950.00,  9500.00,  'traslado', 2, 'Traspaso a Bodega de Oficina', 2, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(4, 16, 'traslado_entrada', 15.00, 700.00,  10500.00, 'traslado', 2, 'Traspaso a Bodega de Oficina', 2, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(4, 17, 'traslado_entrada', 10.00, 600.00,  6000.00,  'traslado', 2, 'Traspaso a Bodega de Oficina', 2, DATE_SUB(NOW(), INTERVAL 12 DAY));

-- Solicitud 2 (Central -> Bodega Principal, procesada)
INSERT INTO movimientos_bodega (id_bodega, id_producto, tipo_movimiento, cantidad, precio_unitario, total, referencia_tipo, referencia_id, observacion, id_usuario, fecha_movimiento) VALUES
(1, 1,  'traslado_salida',  3.00, 0, 0, 'solicitud', 2, 'Solicitud SOL-2026-00002', 2, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, 3,  'traslado_salida',  10.00, 0, 0, 'solicitud', 2, 'Solicitud SOL-2026-00002', 2, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, 16, 'traslado_salida',  3.00, 0, 0, 'solicitud', 2, 'Solicitud SOL-2026-00002', 2, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, 19, 'traslado_salida',  2.00, 0, 0, 'solicitud', 2, 'Solicitud SOL-2026-00002', 2, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(2, 1,  'traslado_entrada', 3.00, 0, 0, 'solicitud', 2, 'Solicitud SOL-2026-00002', 2, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(2, 3,  'traslado_entrada', 10.00, 0, 0, 'solicitud', 2, 'Solicitud SOL-2026-00002', 2, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(2, 16, 'traslado_entrada', 3.00, 0, 0, 'solicitud', 2, 'Solicitud SOL-2026-00002', 2, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(2, 19, 'traslado_entrada', 2.00, 0, 0, 'solicitud', 2, 'Solicitud SOL-2026-00002', 2, DATE_SUB(NOW(), INTERVAL 6 DAY));

-- Traspaso 3 (Central -> Bodega Principal, ejecutada)
INSERT INTO movimientos_bodega (id_bodega, id_producto, tipo_movimiento, cantidad, precio_unitario, total, referencia_tipo, referencia_id, observacion, id_usuario, fecha_movimiento) VALUES
(1, 19, 'traslado_salida',  5.00, 4500.00, 22500.00, 'traslado', 3, 'Traspaso a Bodega Principal', 3, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 18, 'traslado_salida',  6.00, 3200.00, 19200.00, 'traslado', 3, 'Traspaso a Bodega Principal', 3, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 19, 'traslado_entrada', 5.00, 4500.00, 22500.00, 'traslado', 3, 'Traspaso a Bodega Principal', 3, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 18, 'traslado_entrada', 6.00, 3200.00, 19200.00, 'traslado', 3, 'Traspaso a Bodega Principal', 3, DATE_SUB(NOW(), INTERVAL 5 DAY));

-- Solicitud 4 (Central -> Bodega de Oficina, procesada)
INSERT INTO movimientos_bodega (id_bodega, id_producto, tipo_movimiento, cantidad, precio_unitario, total, referencia_tipo, referencia_id, observacion, id_usuario, fecha_movimiento) VALUES
(1, 1,  'traslado_salida',  5.00, 0, 0, 'solicitud', 4, 'Solicitud SOL-2026-00004', 2, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, 4,  'traslado_salida',  10.00, 0, 0, 'solicitud', 4, 'Solicitud SOL-2026-00004', 2, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, 17, 'traslado_salida',  5.00, 0, 0, 'solicitud', 4, 'Solicitud SOL-2026-00004', 2, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(4, 1,  'traslado_entrada', 5.00, 0, 0, 'solicitud', 4, 'Solicitud SOL-2026-00004', 2, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(4, 4,  'traslado_entrada', 10.00, 0, 0, 'solicitud', 4, 'Solicitud SOL-2026-00004', 2, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(4, 17, 'traslado_entrada', 5.00, 0, 0, 'solicitud', 4, 'Solicitud SOL-2026-00004', 2, DATE_SUB(NOW(), INTERVAL 4 DAY));

-- Solicitud 3 (Central -> Bodega Principal, procesada parcial)
INSERT INTO movimientos_bodega (id_bodega, id_producto, tipo_movimiento, cantidad, precio_unitario, total, referencia_tipo, referencia_id, observacion, id_usuario, fecha_movimiento) VALUES
(1, 2,  'traslado_salida',  2.00, 0, 0, 'solicitud', 3, 'Solicitud SOL-2026-00003', 2, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 7,  'traslado_salida',  5.00, 0, 0, 'solicitud', 3, 'Solicitud SOL-2026-00003', 2, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 2,  'traslado_entrada', 2.00, 0, 0, 'solicitud', 3, 'Solicitud SOL-2026-00003', 2, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 7,  'traslado_entrada', 5.00, 0, 0, 'solicitud', 3, 'Solicitud SOL-2026-00003', 2, DATE_SUB(NOW(), INTERVAL 3 DAY));

-- Solicitud 8 (Central -> Bodega de Oficina, procesada hoy)
INSERT INTO movimientos_bodega (id_bodega, id_producto, tipo_movimiento, cantidad, precio_unitario, total, referencia_tipo, referencia_id, observacion, id_usuario, fecha_movimiento) VALUES
(1, 18, 'traslado_salida',  4.00, 0, 0, 'solicitud', 8, 'Solicitud SOL-2026-00008', 2, NOW()),
(1, 20, 'traslado_salida',  3.00, 0, 0, 'solicitud', 8, 'Solicitud SOL-2026-00008', 2, NOW()),
(4, 18, 'traslado_entrada', 4.00, 0, 0, 'solicitud', 8, 'Solicitud SOL-2026-00008', 2, NOW()),
(4, 20, 'traslado_entrada', 3.00, 0, 0, 'solicitud', 8, 'Solicitud SOL-2026-00008', 2, NOW());

-- ---------------------------------------------------------
-- Stock final por bodega (consistente con el kardex)
-- ---------------------------------------------------------
INSERT INTO stock_bodega (id_bodega, id_producto, stock_actual, costo_promedio) VALUES
-- Bodega Central (1)
(1, 1,  7.00,  3900.00),
(1, 2,  4.00,  18500.00),
(1, 3,  60.00, 450.00),
(1, 4,  30.00, 2400.00),
(1, 5,  15.00, 950.00),
(1, 6,  30.00, 4800.00),
(1, 7,  35.00, 5900.00),
(1, 8,  30.00, 2900.00),
(1, 9,  20.00, 2350.00),
(1, 10, 25.00, 2100.00),
(1, 11, 8.00,  389900.00),
(1, 12, 10.00, 82500.00),
(1, 13, 10.00, 74900.00),
(1, 14, 6.00,  128000.00),
(1, 15, 15.00, 28900.00),
(1, 16, 12.00, 700.00),
(1, 17, 5.00,  600.00),
(1, 18, 24.00, 3200.00),
(1, 19, 18.00, 4500.00),
(1, 20, 27.00, 1200.00),
-- Bodega Principal (2)
(2, 1,  3.00, 3900.00),
(2, 2,  2.00, 18500.00),
(2, 3,  10.00, 450.00),
(2, 7,  5.00, 5900.00),
(2, 16, 3.00, 700.00),
(2, 18, 6.00, 3200.00),
(2, 19, 7.00, 4500.00),
-- Bodega de Aseo (3)
(3, 6,  10.00, 4800.00),
(3, 7,  20.00, 5900.00),
(3, 8,  20.00, 2900.00),
(3, 9,  10.00, 2350.00),
(3, 10, 15.00, 2100.00),
-- Bodega de Oficina (4)
(4, 1,  20.00, 3900.00),
(4, 3,  30.00, 450.00),
(4, 4,  30.00, 2400.00),
(4, 5,  10.00, 950.00),
(4, 16, 15.00, 700.00),
(4, 17, 15.00, 600.00),
(4, 18, 4.00, 3200.00),
(4, 20, 3.00, 1200.00);

-- ---------------------------------------------------------
-- Configuraciones (personalización del sitio)
-- ---------------------------------------------------------
INSERT INTO configuraciones (clave, valor) VALUES
('site_nombre',       'Sistema Bodega'),
('site_descripcion',  'Gestión de inventario y bodegas'),
('site_icono',        'bi-box-seam'),
('site_color',        '#0d6efd'),
('site_color_secundario', '#8b5cf6'),
('tema_default',      'auto'),
('org_nombre',        'Organización Demo'),
('org_email_dominio', 'demo.cl');

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- Verificación rápida
-- =========================================================
SELECT 'unidades' AS tabla, COUNT(*) AS registros FROM unidades_organizacionales
UNION ALL SELECT 'bodegas', COUNT(*) FROM bodegas
UNION ALL SELECT 'funcionarios', COUNT(*) FROM funcionarios
UNION ALL SELECT 'usuarios', COUNT(*) FROM usuarios
UNION ALL SELECT 'productos', COUNT(*) FROM productos
UNION ALL SELECT 'proveedores', COUNT(*) FROM proveedores
UNION ALL SELECT 'ordenes_compra', COUNT(*) FROM ordenes_compra
UNION ALL SELECT 'facturas', COUNT(*) FROM facturas
UNION ALL SELECT 'traspasos', COUNT(*) FROM traspasos_bodega
UNION ALL SELECT 'solicitudes', COUNT(*) FROM solicitudes
UNION ALL SELECT 'movimientos', COUNT(*) FROM movimientos_bodega
UNION ALL SELECT 'stock', COUNT(*) FROM stock_bodega;
