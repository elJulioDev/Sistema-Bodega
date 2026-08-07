# AGENTS.md

Sistema de Bodega: inventario en **PHP puro procedural** (sin framework, sin composer, sin tests, sin build, sin CI). Se sirve con XAMPP/Apache en `localhost`; PHP 8.x local, pero el código exige PHP 7+ (`random_bytes` en `inc/csrf.php`; el README dice 5.5 y está desactualizado). Idioma del proyecto: español (UI, comentarios, commits, flashes).

## Cómo verificar el trabajo
- No hay tests ni linter. Verificación = `php -l <archivo>` en cada archivo tocado + revisión de `git diff`.
- Para probar manualmente: navegar a `http://localhost/Sistema-Bodega` (la base del path viene de `BASE_URL` en `.env`, que está gitignoreado; `.env.example` es la plantilla).

## Setup (solo primera vez)
1. `cp .env.example .env` y ajustar credenciales (`DB_NAME=sistema_bodega` por defecto).
2. Importar `database/schema.sql` (incluye seed mínimo y usuario `admin` con hash placeholder **inválido**).
3. Generar hash real y actualizarlo:
   `php -r "echo password_hash('CLAVE', PASSWORD_DEFAULT);"`
   luego `UPDATE usuarios SET clave_hash='...' WHERE usuario='admin';`

## Estructura y esqueleto de cada página
- Sin enrutador: cada archivo PHP es una página accesible directamente. Raíz: `login.php`, `index.php`, `logout.php`. Módulos: `modulos/<dominio>/<accion>.php`.
- Toda página sigue este orden obligatorio:
  1. `require_once __DIR__ . '/../../inc/db.php';` — expone el `$pdo` global (PDO, ERRMODE_EXCEPTION, EMULATE_PREPARES=false, FETCH_ASSOC).
  2. `inc/auth.php` → `inc/functions.php` → (si aplica) `inc/bodegas_helpers.php` (siempre después de db.php y auth.php).
  3. `require_login();` y `require_role(...)`.
  4. Asignar `$pageTitle` ANTES de `require .../inc/header.php` (que además carga auth/functions y el flash).
  5. Terminar con `require .../inc/footer.php`.
- Helpers frecuentes: `h()` (escape HTML), `get()/post()` (params con trim), `set_flash('success'|'error'|'danger', msg)` + `redirect('archivo.php')`. Los redirects relativos se resuelven contra la carpeta del módulo; para ir a la raíz usar `BASE_URL . '/index.php'`.

## Seguridad — invariantes a respetar
- `inc/functions.php` incluye `inc/csrf.php`, que **valida automáticamente todo POST** al cargarse (una vez por request, vía guard global).
  - Todo `<form method="post">` DEBE incluir `<?php echo csrf_field(); ?>` justo después de abrir el form.
  - Nunca crear acciones destructivas (toggle/eliminar/anular/revocar) por GET: son forms POST con token.
  - `login.php` es la excepción: token manual `name="csrf"` + `csrf_check()` explícito; no tocar.
- Roles reales (ENUM en schema.sql): `admin`, `bodega`, `solicitante`. `auditor` y `consulta` **no existen**; todo uso actual es código muerto — no agregar usos nuevos. Helpers: `is_admin()`, `is_encargado()`, `is_solicitante()`, `current_user()`.
- Escapar toda salida con `h()` y usar prepared statements (`$pdo->prepare(...)->execute(array(...))`).

## Modelo de datos
- `database/schema.sql` es la fuente de verdad, pero el código tiene divergencias conocidas: verificar toda columna ahí antes de usarla.
  - `modulos/movimientos/movimientos_crear.php:19-46` auto-crea `traspasos_bodega(_detalle)` con `CREATE TABLE IF NOT EXISTS` (sin FKs y tipos distintos a schema.sql).
  - `solicitudes_detalle` usa `observacion` para la nota del ítem (no `motivo_ajuste`).
  - `productos`, `stock_bodega` y `usuarios_bodegas` ya tienen `updated_at`/`created_at` en schema.sql.
- Encargados↔bodegas es **M:N** vía `usuarios_bodegas` (la columna legacy `bodegas.id_encargado` solo existe para compatibilidad). Usar helpers de `inc/bodegas_helpers.php`: `user_bodegas_ids()`, `user_bodegas()`, `user_puede_operar_bodega()`, `asignar_encargado_bodega()`, `desasignar_encargado_bodega()`, etc.

## Estilo de código
- PHP estilo clásico: `array()` (nunca `[]`), indentación 4 espacios, funciones snake_case, `<?php echo h(...) ?>`.
- Commits cortos en imperativo y español, ej.: "Añadida protección CSRF...", "Correccion bug al importar funcionarios". Rama por defecto: `main`, remoto `origin` (github.com/elJulioDev/Sistema-Bodega.git).

## Issues abiertos y referencia en commits
- Hay 5 issues abiertos en GitHub que representan features pendientes:
  - `#1` — Módulo de reportes
  - `#2` — Alertas de stock crítico
  - `#3` — Exportación de datos
  - `#4` — Auditoría avanzada global
  - `#5` — Gestión de devoluciones
- Convención de commits: si un commit cierra (o atiende) un issue, escribir el mensaje, dejar una línea en blanco y añadir `Closes #N` (con el número del issue), ej.:
  ```
  Añadido módulo de reportes con dashboard de consumo

  Closes #1
  ```
- Cuando se trabaje una de estas features, además de cerrar el issue en el commit, conviene mantener el README y/o AGENTS.md al día con lo implementado.
