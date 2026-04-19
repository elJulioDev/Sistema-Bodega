<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/functions.php';

require_login();

$id = (int)get('id');
$reactivar = (int)get('reactivar');

if ($id <= 0) {
    set_flash('error', 'ID de factura inválido.');
    redirect('facturas_lista.php');
}

// Cargar factura
$stmt = $pdo->prepare("SELECT * FROM facturas WHERE id = ? LIMIT 1");
$stmt->execute(array($id));
$factura = $stmt->fetch();

if (!$factura) {
    set_flash('error', 'Factura no encontrada.');
    redirect('facturas_lista.php');
}

$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$id_bodega = (int)$factura['id_bodega'];

// Obtener detalle
$stmt = $pdo->prepare("SELECT * FROM facturas_detalle WHERE id_factura = ?");
$stmt->execute(array($id));
$detalle = $stmt->fetchAll();

try {
    $pdo->beginTransaction();

    if ($reactivar === 1) {
        // --- REACTIVAR ---
        if ($factura['estado'] !== 'anulada') {
            throw new Exception('Solo se pueden reactivar facturas anuladas.');
        }

        foreach ($detalle as $d) {
            if (empty($d['id_producto'])) continue;
            $idP = (int)$d['id_producto'];
            $cant = (float)$d['cantidad'];
            $precio = (float)$d['precio_unitario'];
            $subtotal = (float)$d['subtotal'];

            // Movimiento entrada_compra (reversión de anulación)
            $pdo->prepare("
                INSERT INTO movimientos_bodega 
                (id_bodega, id_producto, tipo_movimiento, cantidad, precio_unitario, total, referencia_tipo, referencia_id, observacion, id_usuario)
                VALUES (?, ?, 'entrada_compra', ?, ?, ?, 'factura', ?, ?, ?)
            ")->execute(array(
                $id_bodega, $idP, $cant, $precio, $subtotal, $id,
                'Reactivación de factura N° ' . $factura['numero_factura'],
                $uid
            ));

            // Actualizar stock_bodega
            $stockStmt = $pdo->prepare("SELECT id, stock_actual FROM stock_bodega WHERE id_bodega = ? AND id_producto = ? LIMIT 1");
            $stockStmt->execute(array($id_bodega, $idP));
            $sb = $stockStmt->fetch();

            if ($sb) {
                $pdo->prepare("UPDATE stock_bodega SET stock_actual = stock_actual + ?, costo_promedio = ? WHERE id = ?")
                    ->execute(array($cant, $precio, (int)$sb['id']));
            } else {
                $pdo->prepare("INSERT INTO stock_bodega (id_bodega, id_producto, stock_actual, costo_promedio) VALUES (?, ?, ?, ?)")
                    ->execute(array($id_bodega, $idP, $cant, $precio));
            }
        }

        $pdo->prepare("UPDATE facturas SET estado = 'ingresada' WHERE id = ?")->execute(array($id));
        $pdo->commit();
        set_flash('success', 'Factura reactivada. Stock reingresado a bodega.');

    } else {
        // --- ANULAR ---
        if ($factura['estado'] === 'anulada') {
            throw new Exception('La factura ya está anulada.');
        }

        foreach ($detalle as $d) {
            if (empty($d['id_producto'])) continue;
            $idP = (int)$d['id_producto'];
            $cant = (float)$d['cantidad'];
            $precio = (float)$d['precio_unitario'];
            $subtotal = (float)$d['subtotal'];

            // Verificar stock disponible
            $stockStmt = $pdo->prepare("SELECT id, stock_actual FROM stock_bodega WHERE id_bodega = ? AND id_producto = ? LIMIT 1");
            $stockStmt->execute(array($id_bodega, $idP));
            $sb = $stockStmt->fetch();

            if (!$sb || (float)$sb['stock_actual'] < $cant) {
                $nomStmt = $pdo->prepare("SELECT nombre FROM productos WHERE id = ?");
                $nomStmt->execute(array($idP));
                $prod = $nomStmt->fetch();
                $disponible = $sb ? $sb['stock_actual'] : 0;
                throw new Exception(
                    'No se puede anular: el producto "' . ($prod['nombre'] ?? 'ID ' . $idP) . 
                    '" ya fue consumido o trasladado. Stock disponible: ' . $disponible . ', requerido: ' . $cant . '.'
                );
            }

            // Movimiento ajuste_salida (reversión)
            $pdo->prepare("
                INSERT INTO movimientos_bodega 
                (id_bodega, id_producto, tipo_movimiento, cantidad, precio_unitario, total, referencia_tipo, referencia_id, observacion, id_usuario)
                VALUES (?, ?, 'ajuste_salida', ?, ?, ?, 'factura', ?, ?, ?)
            ")->execute(array(
                $id_bodega, $idP, $cant, $precio, $subtotal, $id,
                'Anulación de factura N° ' . $factura['numero_factura'],
                $uid
            ));

            // Restar stock
            $pdo->prepare("UPDATE stock_bodega SET stock_actual = stock_actual - ? WHERE id = ?")
                ->execute(array($cant, (int)$sb['id']));
        }

        $pdo->prepare("UPDATE facturas SET estado = 'anulada' WHERE id = ?")->execute(array($id));
        $pdo->commit();
        set_flash('success', 'Factura anulada. Stock revertido correctamente.');
    }

} catch (Exception $e) {
    $pdo->rollBack();
    set_flash('error', $e->getMessage());
}

redirect('facturas_ver.php?id=' . $id);