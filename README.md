# Sistema de Gestión de Bodegas e Inventario

## Descripción General
El Sistema de Gestión de Bodegas es una plataforma web modular diseñada para la administración eficiente del inventario, control de stock y seguimiento de solicitudes de materiales. Construido para ser escalable, seguro y fácil de usar, permite a las organizaciones tener una trazabilidad completa desde que se genera una orden de compra hasta que un funcionario solicita y recibe un producto.

---

## Arquitectura y Seguridad
El proyecto sigue una estructura modular, separando las lógicas de negocio en directorios específicos (Bodegas, Productos, Movimientos, Facturas, etc.). 

A nivel de seguridad, el sistema cuenta con:
* **Autenticación y Sesiones:** Cada ruta está protegida para evitar accesos no autorizados.
* **Control de Acceso Basado en Roles (RBAC):** Restricción de URLs según el tipo de usuario mediante la función `require_role()`.
* **Protección CSRF:** Tokens integrados para prevenir ataques de falsificación de peticiones en los formularios.

---

## Roles y Permisos (Manual de Acceso)

El acceso a los diferentes módulos está estrictamente gobernado por el rol del usuario autenticado:

### 1. Administrador (`admin`)
Es el rol de superusuario con acceso global a las configuraciones y mantenedores del sistema.
* **Acceso Exclusivo a Módulos:** Proveedores, Productos, Facturas, Bodegas, Funcionarios, Usuarios y Unidades.
* **Funciones:** Crear y editar nuevos usuarios, dar de alta nuevas bodegas y gestionar el catálogo general de productos.

### 2. Encargado de Bodega (`bodega`)
Rol operativo enfocado en el manejo físico y lógico del inventario.
* **Módulos Permitidos:** Stock, Movimientos (Entradas/Salidas/Traslados) y Solicitudes.
* **Funciones:** Revisar el stock disponible, registrar los movimientos diarios de los productos, gestionar traslados entre bodegas y aprobar/despachar las solicitudes de materiales.

### 3. Solicitante (`solicitante`)
Rol destinado a los funcionarios que requieren pedir materiales a la bodega.
* **Módulos Permitidos:** Únicamente Solicitudes.
* **Funciones:** Crear nuevas peticiones de insumos y revisar el estado de las mismas.

---

## Manual de Usuario por Módulos

### Módulos Administrativos (Solo Admin)
* **Usuarios y Funcionarios:** Permite registrar al personal y asignarles credenciales de acceso. Al crear un usuario, se le vincula un rol, una bodega (si es encargado) o una unidad/departamento específico.
* **Bodegas y Unidades:** Definición de los espacios físicos de almacenamiento (Bodegas) y los departamentos de la organización (Unidades).
* **Catálogo (Productos y Proveedores):** Gestión del diccionario de artículos que se manejarán en la bodega, definiendo sus categorías y las empresas que los suministran.
* **Facturas y Órdenes de Compra:** Registro de los documentos tributarios y órdenes previas que respaldan el ingreso de nueva mercadería.

### Módulos Operativos (Admin y Encargado)
* **Stock:** Tablero en tiempo real que refleja las cantidades actuales de cada producto por bodega.
* **Movimientos y Ajustes:** Historial donde se registran las entradas (ingresos por compra), las salidas (entregas por solicitud) y los ajustes de inventario.

### Módulos de Flujo (Todos los roles)
* **Solicitudes de Consumo (Mejorado):** El corazón del autoservicio. Los *Solicitantes* generan listas de productos requeridos. El flujo incluye características avanzadas:
  * **Sistema de Caducidad:** Las solicitudes tienen un plazo límite para ser gestionadas (por defecto 3 días). Si se supera la fecha límite sin respuesta, pasan automáticamente a estado *Caducada*.
  * **Aprobaciones Parciales y Ajustes:** Los encargados pueden aprobar o rechazar de manera individual cada ítem solicitado, ajustando las cantidades a entregar y justificando el motivo, permitiendo que una solicitud general quede *Procesada Parcialmente*.
  * **Trazabilidad (Logs):** Cada interacción (creación, revisión, rechazo o proceso) genera un registro de auditoría automático que guarda la fecha, el usuario y la acción realizada.
  * **Despacho Automatizado:** Al confirmar la entrega, el sistema genera automáticamente un movimiento de salida, descontando el stock.
* **Solicitudes de Traslado:** Permite a las bodegas solicitar el movimiento de mercadería hacia otras bodegas o unidades, manteniendo un ciclo de aprobación propio (borrador, pendiente, aprobada, ejecutada) y previniendo errores de transferencias a la misma bodega de origen.

---

## Documentación Técnica para Desarrolladores

### Helpers de Autorización y Contexto
Para facilitar el desarrollo de nuevas vistas, el sistema expone funciones de validación integradas:

```php
is_admin()            // Retorna true si el usuario activo es administrador
is_encargado()        // Retorna true si el usuario activo es encargado de bodega
is_solicitante()      // Retorna true si el usuario activo es solo solicitante

// Obtención de datos del contexto del usuario activo:
user_bodega_id()      // ID de la bodega asignada (0 si no aplica)
user_unidad_id()      // ID de la unidad asignada (0 si no aplica)
user_funcionario_id() // ID del funcionario vinculado (0 si no aplica)
```

La variable global `current_user()` almacena la sesión actual con todos los campos expandidos (incluyendo `id_bodega`, `id_unidad` e `id_funcionario`).

---

## Hoja de Ruta (Cosas por hacerse / To-Do)

- [ ] **Módulo de Reportes:** Creación de dashboards gráficos con métricas de consumo por unidad, productos más solicitados y proyecciones de quiebre de stock.
- [ ] **Alertas de Stock Crítico:** Implementar notificaciones visuales (y por correo) cuando un producto alcance su nivel de stock mínimo definido.
- [ ] **Exportación de Datos:** Añadir botones para exportar tablas (Stock, Movimientos, Facturas) a formatos Excel y PDF.
- [ ] **Auditoría Avanzada Global:** Extender la bitácora invisible (actualmente operativa en Solicitudes) para guardar qué usuario creó, editó o eliminó registros en todas las tablas del sistema.
- [ ] **Gestión de Devoluciones:** Implementar un flujo para que los solicitantes devuelvan materiales no utilizados a la bodega y el stock se reintegre.
