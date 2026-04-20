<?php
/**
 * inc/bodegas_helpers.php
 * ------------------------------------------------------------
 * Helpers para el modelo M:N usuarios ↔ bodegas.
 * Incluir DESPUÉS de inc/db.php y inc/auth.php.
 *
 * Reglas:
 *   - Encargado (rol 'bodega'): gestiona TODAS sus bodegas asignadas
 *     en usuarios_bodegas.
 *   - Solicitante: pertenece a una unidad; puede pedir a
 *     CUALQUIER bodega de esa unidad (bodegas.id_unidad).
 *   - Admin: sin restricciones.
 * ------------------------------------------------------------
 */

if (!function_exists('user_bodegas_ids')) {
    /**
     * IDs de bodegas donde el usuario es encargado (M:N).
     * Para admin/solicitante devuelve array() si no aplica.
     */
    function user_bodegas_ids($id_usuario = null) {
        global $pdo;
        if ($id_usuario === null) {
            $u = current_user();
            if (!$u) return array();
            $id_usuario = (int)$u['id'];
        }
        $st = $pdo->prepare("SELECT id_bodega FROM usuarios_bodegas WHERE id_usuario = ?");
        $st->execute(array((int)$id_usuario));
        $out = array();
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $v) {
            $out[] = (int)$v;
        }
        return $out;
    }
}

if (!function_exists('user_bodegas')) {
    /**
     * Bodegas completas (filas) donde el usuario es encargado.
     */
    function user_bodegas($id_usuario = null) {
        global $pdo;
        if ($id_usuario === null) {
            $u = current_user();
            if (!$u) return array();
            $id_usuario = (int)$u['id'];
        }
        $sql = "SELECT b.*, ub.es_principal, uo.nombre AS unidad_nombre
                FROM   usuarios_bodegas ub
                INNER  JOIN bodegas b ON b.id = ub.id_bodega
                LEFT   JOIN unidades_organizacionales uo ON uo.id = b.id_unidad
                WHERE  ub.id_usuario = ? AND b.estado = 1
                ORDER  BY ub.es_principal DESC, b.nombre ASC";
        $st = $pdo->prepare($sql);
        $st->execute(array((int)$id_usuario));
        return $st->fetchAll();
    }
}

if (!function_exists('user_bodega_principal_id')) {
    /**
     * ID de la bodega principal del encargado (para compat).
     * Usa es_principal=1, o la primera disponible si no hay principal.
     */
    function user_bodega_principal_id($id_usuario = null) {
        global $pdo;
        if ($id_usuario === null) {
            $u = current_user();
            if (!$u) return 0;
            $id_usuario = (int)$u['id'];
        }
        $st = $pdo->prepare("
            SELECT id_bodega
            FROM   usuarios_bodegas
            WHERE  id_usuario = ?
            ORDER  BY es_principal DESC, id ASC
            LIMIT  1
        ");
        $st->execute(array((int)$id_usuario));
        $v = $st->fetchColumn();
        return $v ? (int)$v : 0;
    }
}

if (!function_exists('user_puede_operar_bodega')) {
    /**
     * ¿El usuario actual puede operar esta bodega?
     *  - admin  → sí
     *  - bodega → solo si está asignado (usuarios_bodegas)
     *  - otros  → no
     */
    function user_puede_operar_bodega($id_bodega) {
        if (is_admin()) return true;
        if (!is_encargado()) return false;
        $id_bodega = (int)$id_bodega;
        if ($id_bodega <= 0) return false;
        $ids = user_bodegas_ids();
        return in_array($id_bodega, $ids, true);
    }
}

if (!function_exists('bodegas_de_unidad')) {
    /**
     * Bodegas activas ligadas a una unidad organizacional.
     */
    function bodegas_de_unidad($id_unidad) {
        global $pdo;
        $id_unidad = (int)$id_unidad;
        if ($id_unidad <= 0) return array();
        $st = $pdo->prepare("
            SELECT id, codigo, nombre
            FROM   bodegas
            WHERE  id_unidad = ? AND estado = 1
            ORDER  BY nombre ASC
        ");
        $st->execute(array($id_unidad));
        return $st->fetchAll();
    }
}

if (!function_exists('bodegas_destino_solicitante')) {
    /**
     * Bodegas a las que el solicitante puede enviar una solicitud.
     * Por regla de negocio: bodegas de SU unidad.
     */
    function bodegas_destino_solicitante($id_usuario = null) {
        global $pdo;
        if ($id_usuario === null) {
            $u = current_user();
            if (!$u) return array();
            $id_usuario = (int)$u['id'];
        }
        $st = $pdo->prepare("SELECT id_unidad FROM usuarios WHERE id = ?");
        $st->execute(array((int)$id_usuario));
        $uni = (int)$st->fetchColumn();
        if ($uni <= 0) return array();
        return bodegas_de_unidad($uni);
    }
}

if (!function_exists('encargados_de_bodega')) {
    /**
     * Lista de usuarios encargados de una bodega.
     */
    function encargados_de_bodega($id_bodega) {
        global $pdo;
        $id_bodega = (int)$id_bodega;
        if ($id_bodega <= 0) return array();
        $sql = "SELECT u.id, u.usuario, u.rol,
                       COALESCE(f.nombre, u.nombre) AS nombre,
                       f.rut, f.cargo,
                       ub.es_principal, ub.created_at AS asignado_en
                FROM   usuarios_bodegas ub
                INNER  JOIN usuarios u     ON u.id = ub.id_usuario
                LEFT   JOIN funcionarios f ON f.id = u.id_funcionario
                WHERE  ub.id_bodega = ?
                ORDER  BY ub.es_principal DESC, nombre ASC";
        $st = $pdo->prepare($sql);
        $st->execute(array($id_bodega));
        return $st->fetchAll();
    }
}

if (!function_exists('asignar_encargado_bodega')) {
    /**
     * Asigna un usuario como encargado de una bodega.
     * Si es_principal=true, se convierte en la bodega principal.
     */
    function asignar_encargado_bodega($id_usuario, $id_bodega, $es_principal = false) {
        global $pdo;
        $id_usuario  = (int)$id_usuario;
        $id_bodega   = (int)$id_bodega;
        if ($id_usuario <= 0 || $id_bodega <= 0) return false;

        // Promover a 'bodega' si era solicitante
        $st = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ?");
        $st->execute(array($id_usuario));
        $rol = (string)$st->fetchColumn();
        if ($rol === 'solicitante') {
            $pdo->prepare("UPDATE usuarios SET rol = 'bodega' WHERE id = ?")
                ->execute(array($id_usuario));
        } elseif (!in_array($rol, array('admin','bodega'), true)) {
            return false;
        }

        // Insertar o actualizar
        $st = $pdo->prepare("
            INSERT INTO usuarios_bodegas (id_usuario, id_bodega, es_principal)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE es_principal = VALUES(es_principal)
        ");
        $st->execute(array($id_usuario, $id_bodega, $es_principal ? 1 : 0));

        // Mantener legacy bodegas.id_encargado (primer encargado principal)
        if ($es_principal) {
            $pdo->prepare("UPDATE bodegas SET id_encargado = ? WHERE id = ?")
                ->execute(array($id_usuario, $id_bodega));
        }
        return true;
    }
}

if (!function_exists('desasignar_encargado_bodega')) {
    /**
     * Quita un usuario como encargado de una bodega.
     * Si queda sin bodegas, su rol vuelve a 'solicitante'.
     */
    function desasignar_encargado_bodega($id_usuario, $id_bodega) {
        global $pdo;
        $id_usuario = (int)$id_usuario;
        $id_bodega  = (int)$id_bodega;
        if ($id_usuario <= 0 || $id_bodega <= 0) return false;

        $pdo->prepare("DELETE FROM usuarios_bodegas WHERE id_usuario = ? AND id_bodega = ?")
            ->execute(array($id_usuario, $id_bodega));

        // Limpiar id_encargado legacy si apuntaba a este usuario
        $pdo->prepare("UPDATE bodegas SET id_encargado = NULL
                       WHERE id = ? AND id_encargado = ?")
            ->execute(array($id_bodega, $id_usuario));

        // ¿Quedan otras bodegas?
        $st = $pdo->prepare("SELECT COUNT(*) FROM usuarios_bodegas WHERE id_usuario = ?");
        $st->execute(array($id_usuario));
        $restantes = (int)$st->fetchColumn();

        if ($restantes === 0) {
            // Degradar rol y limpiar id_bodega legacy
            $pdo->prepare("UPDATE usuarios
                           SET rol = 'solicitante', id_bodega = NULL
                           WHERE id = ? AND rol = 'bodega'")
                ->execute(array($id_usuario));
        } else {
            // Si esta era su principal, promover otra
            $st = $pdo->prepare("SELECT id_bodega FROM usuarios_bodegas
                                 WHERE id_usuario = ?
                                 ORDER BY es_principal DESC, id ASC LIMIT 1");
            $st->execute(array($id_usuario));
            $nuevaPrincipal = (int)$st->fetchColumn();
            if ($nuevaPrincipal > 0) {
                $pdo->prepare("UPDATE usuarios SET id_bodega = ? WHERE id = ?")
                    ->execute(array($nuevaPrincipal, $id_usuario));
            }
        }
        return true;
    }
}

if (!function_exists('set_bodega_principal')) {
    /**
     * Marca una bodega como principal para un usuario (quita el flag de las otras).
     */
    function set_bodega_principal($id_usuario, $id_bodega) {
        global $pdo;
        $id_usuario = (int)$id_usuario;
        $id_bodega  = (int)$id_bodega;
        $pdo->prepare("UPDATE usuarios_bodegas SET es_principal = 0 WHERE id_usuario = ?")
            ->execute(array($id_usuario));
        $pdo->prepare("UPDATE usuarios_bodegas SET es_principal = 1
                       WHERE id_usuario = ? AND id_bodega = ?")
            ->execute(array($id_usuario, $id_bodega));
        $pdo->prepare("UPDATE usuarios SET id_bodega = ? WHERE id = ?")
            ->execute(array($id_bodega, $id_usuario));
        $pdo->prepare("UPDATE bodegas SET id_encargado = ? WHERE id = ?")
            ->execute(array($id_usuario, $id_bodega));
        return true;
    }
}