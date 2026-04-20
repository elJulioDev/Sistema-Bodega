# Checklist: Protección URL por rol

Añadir la linea `require_role(...)` inmediatamente despues del `require_login()` existente. Si no existe `require_login()`, añadir ambos.

## Archivos que requieren SOLO admin

```php
require_login();
require_role('admin');
```

Aplicar a:

- `modulos/proveedores/proveedores_lista.php`
- `modulos/proveedores/proveedores_crear.php`
- `modulos/proveedores/proveedores_editar.php`
- `modulos/proveedores/proveedores_ver.php` *(si existe)*
- `modulos/productos/productos_lista.php`
- `modulos/productos/productos_crear.php`
- `modulos/productos/productos_editar.php`
- `modulos/productos/productos_ver.php` *(si existe)*
- `modulos/facturas/facturas_lista.php`
- `modulos/facturas/facturas_crear.php`
- `modulos/facturas/facturas_ver.php`
- `modulos/facturas/facturas_editar.php` *(si existe)*
- `modulos/bodegas/bodegas_lista.php`
- `modulos/bodegas/bodegas_crear.php`
- `modulos/bodegas/bodegas_editar.php`
- `modulos/funcionarios/funcionarios_lista.php`
- `modulos/funcionarios/funcionarios_crear.php`
- `modulos/funcionarios/funcionarios_editar.php`
- `modulos/usuarios/usuarios_lista.php` ✅ ya protegido
- `modulos/usuarios/usuarios_crear.php` ✅ ya protegido
- `modulos/usuarios/usuarios_editar.php` ✅ ya protegido
- `modulos/unidades/*.php` *(si existe)*

## Archivos admin + encargado (NO solicitante)

```php
require_login();
require_role(array('admin', 'bodega'));
```

Aplicar a:

- `modulos/stock_lista.php`
- `modulos/movimientos/movimientos_lista.php`
- `modulos/movimientos/movimientos_crear.php`
- `modulos/movimientos/movimientos_ver.php`

## Archivos de solicitudes (admin + encargado + solicitante)

```php
require_login();
require_role(array('admin', 'bodega', 'solicitante'));
```

Aplicar a:

- `modulos/movimientos/solicitudes_lista.php`
- `modulos/movimientos/solicitudes_crear.php`
- `modulos/movimientos/solicitudes_ver.php` *(si existe)*

## Solo login (sin restricción de rol)

- `index.php` (dashboard)
- `logout.php`

---

## Nuevos helpers disponibles

En cualquier archivo ya puedes usar:

```php
is_admin()            // true si rol = 'admin'
is_encargado()        // true si rol = 'bodega'
is_solicitante()      // true si rol = 'solicitante'
user_bodega_id()      // id de la bodega asignada (0 si no tiene)
user_unidad_id()      // id de la unidad asignada (0 si no tiene)
user_funcionario_id() // id del funcionario vinculado (0 si no tiene)
```

El `current_user()` ahora incluye tambien `id_bodega`, `id_unidad` y `id_funcionario`.
