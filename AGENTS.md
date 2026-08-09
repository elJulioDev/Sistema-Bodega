# AGENTS.md

Sistema de Bodega: inventario en **PHP puro procedural** (sin framework, sin composer, sin tests, sin build, sin CI). Se sirve con XAMPP/Apache en `localhost`; PHP 8.x local (README pide 7.4+, el código usa `random_bytes` en `inc/csrf.php`). Idioma del proyecto: español (UI, comentarios, commits, flashes).

## Cómo verificar el trabajo
- No hay tests ni linter. Verificación = `php -l <archivo>` en cada archivo tocado + revisión de `git diff`.
- Lint: sirve tanto `php` de PATH (8.5) como `/opt/lampp/bin/php` (8.2, el de XAMPP). La BD viva local es la de XAMPP (`127.0.0.1:3306`, usuario `root` sin password, ver `.env`).
- Acceso real: Apache/XAMPP en `http://localhost/Sistema-Bodega` (path de `BASE_URL` en `.env`, gitignoreado; `.env.example` es la plantilla).
- Smoke test rápido sin Apache: `php -S 127.0.0.1:8099` desde la raíz del proyecto y pedir `login.php`/`index.php` directamente (302 sin sesión, render con sesión).

## Setup (solo primera vez)
1. `cp .env.example .env` y ajustar credenciales (`DB_NAME=sistema_bodega` por defecto).
2. Importar `database/schema.sql` (incluye seed mínimo y usuario `admin` con hash placeholder **inválido**).
3. Generar hash real y actualizarlo:
   `php -r "echo password_hash('CLAVE', PASSWORD_DEFAULT);"`
   luego `UPDATE usuarios SET clave_hash='...' WHERE usuario='admin';`

## Estructura y esqueleto de cada página
- Sin enrutador: cada archivo PHP es una página accesible directamente. Raíz: `login.php`, `index.php`, `logout.php`. Módulos: `modulos/<dominio>/<accion>.php`.
- Toda página sigue este orden obligatorio:
  1. `require_once __DIR__ . '/../../inc/db.php';` — expone el `$pdo` global (PDO, ERRMODE_EXCEPTION, EMULATE_PREPARES=false, FETCH_ASSOC). A su vez carga `inc/config.php`, que lee `.env` y define `BASE_URL`, `APP_ENV` (con `APP_ENV=local` se muestran errores en pantalla) y los defines `DB_*`.
  2. `inc/auth.php` → `inc/functions.php` → (si aplica) `inc/bodegas_helpers.php` (siempre después de db.php y auth.php).
  3. `require_login();` y `require_role(...)`.
  4. Asignar `$pageTitle` ANTES de `require .../inc/header.php` (que además carga auth/functions, `inc/settings.php` y `inc/ui.php`, y el flash).
  5. Terminar con `require .../inc/footer.php`.
- Helpers frecuentes: `h()` (escape HTML), `get()/post()` (params con trim), `set_flash('success'|'error'|'danger', msg)` + `redirect('archivo.php')`. Los redirects relativos se resuelven contra la carpeta del módulo; para ir a la raíz usar `BASE_URL . '/index.php'`.

## UI y design system (tema claro/oscuro)
- `inc/header.php` ya renderiza el shell completo: topbar (breadcrumb, selector de tema, menú de usuario con avatar y cerrar sesión), sidebar colapsable por rol, overlay móvil y el flash. Las páginas **no** deben duplicar breadcrumbs ni el encabezado de página a mano. El estado del sidebar (`sb_collapsed`) y el tema (`sb_theme`) se restauran de forma síncrona en el `<head>` (evita parpadeo al navegar).
- Cabecera de página → helper `ui_page_header($icon, $title, $subtitle, $actions)` de `inc/ui.php` (escapa título/subtítulo con `h()`; no pasar HTML en ellos). Botones con `ui_btn_link()` / `ui_btn_submit()` y badges/estados vacíos con `ui_badge()` / `ui_empty_state()` / `ui_alert()`.
- Prohibido en vistas: `text-dark`, `bg-white`, `bg-light`, `table-light` y colores hex fijos en `<style>` inline (rompen dark mode). Usar clases adaptativas (`text-body`, `bg-body`) y variables de `static/css/app.css` (`--app-text`, `--app-border`, `--app-surface-alt`, `--app-brand`, `--app-accent`, etc.).
- Íconos de búsqueda de filtros/buscadores: `input-group-text bg-body` + `<i class="bi bi-search text-secondary">`. Sin `text-secondary` el ícono hereda el color del cuerpo (gris en claro, blanco en oscuro); `bg-light` además rompe el tema.
- El flash de `set_flash()` se renderiza como modal `#flashModal` (en `inc/header.php`). No usar `confirm()`/`alert()` nativos: `data-confirm` en un form/botón/enlace abre el modal de confirmación, y hay APIs globales `uiConfirm(msg, cb, title)` / `uiAlert(msg, title, type)` definidas en `inc/footer.php` (que además carga el bundle de Bootstrap 5.3 por CDN al inicio).
- Personalización del sitio (nombre, ícono, **dos colores**: `site_color` principal y `site_color_secundario` usado en la línea de acento de la topbar y el indicador del menú activo, tema por defecto, org) se guarda en la tabla `configuraciones` y se lee con `site_config()` de `inc/settings.php`; el panel admin está en `modulos/configuraciones/editar.php`.
- El favicon de la pestaña es SVG inline (data URI) generado por `site_favicon()` de `inc/settings.php` a partir de los colores de la marca y el ícono `bi-box-seam`; `inc/header.php` lo renderiza con `<link rel="icon">`. No usar favicons externos.

## Seguridad — invariantes a respetar
- `inc/functions.php` incluye `inc/csrf.php`, que **valida automáticamente todo POST** al cargarse (una vez por request, vía guard global).
  - Todo `<form method="post">` DEBE incluir `<?php echo csrf_field(); ?>` justo después de abrir el form.
  - Nunca crear acciones destructivas (toggle/eliminar/anular/revocar) por GET: son forms POST con token.
  - `login.php` es la excepción: token manual `name="csrf"` + `csrf_check()` explícito; no tocar.
- Roles reales (ENUM en schema.sql): `admin`, `bodega`, `solicitante`. `auditor` y `consulta` **no existen** — no agregar usos nuevos. Helpers: `is_admin()`, `is_encargado()`, `is_solicitante()`, `current_user()`.
- Escapar toda salida con `h()` y usar prepared statements (`$pdo->prepare(...)->execute(array(...))`).

## Modelo de datos
- `database/schema.sql` es la fuente de verdad: toda tabla y columna que use el código debe existir ahí antes de usarla.
  - `solicitudes_detalle` tiene tanto `observacion` (nota del solicitante al pedir) como `motivo_ajuste` (nota del encargado al ajustar/revisar).
  - `productos`, `stock_bodega` y `usuarios_bodegas` ya tienen `updated_at`/`created_at` en schema.sql.
  - `configuraciones` (clave/valor) guarda la personalización del sitio; se lee/escribe con `site_config()` / `site_save_config()` de `inc/settings.php`.
  - `usuarios.debe_cambiar_clave` (TINYINT) fuerza el cambio de clave en el primer login; la página obligatoria es `modulos/usuarios/cambiar_clave.php` y `require_login()` reenvía ahí hasta cambiarla.
  - `login_intentos` (usuario, ip, intentos, bloqueado_hasta, ultimo_intento) es el throttle persistente de fuerza bruta; helpers en `inc/functions.php`: `login_bloqueado()`, `registrar_intento_fallido()`, `limpiar_intentos_login()`.
- Solicitudes caducan solas: al cargar `solicitudes_lista.php` se llama `caducar_solicitudes_vencidas($pdo)` (en `inc/functions.php`) para pasar a `caducada` las pendientes/en_revisión vencidas (estado ENUM). Llamarla al inicio en cualquier vista de solicitudes que deba mostrar estados al día.
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
