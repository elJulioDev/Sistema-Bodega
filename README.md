# 📦 Sistema de Gestión de Bodegas e Inventario

## 📖 Descripción General
El Sistema de Gestión de Bodegas es una plataforma web modular diseñada para la administración eficiente del inventario, control de stock y seguimiento de solicitudes de materiales. Construido para ser escalable, seguro y fácil de usar, permite a las organizaciones tener una trazabilidad completa desde que se genera una orden de compra hasta que un funcionario solicita y recibe un producto.

---

## 🏗️ Arquitectura y Seguridad
El proyecto sigue una estructura modular, separando las lógicas de negocio en directorios específicos (Bodegas, Productos, Movimientos, Facturas, etc.). 

A nivel de seguridad, el sistema cuenta con:
* **Autenticación y Sesiones:** Cada ruta está protegida para evitar accesos no autorizados.
* **Control de Acceso Basado en Roles (RBAC):** Restricción de URLs según el tipo de usuario mediante la función `require_role()`.
* **Protección CSRF:** Tokens integrados para prevenir ataques de falsificación de peticiones en los formularios.

---

## 👥 Roles y Permisos (Manual de Acceso)

El acceso a los diferentes módulos está estrictamente gobernado por el rol del usuario autenticado:

### 1. Administrador (`admin`)
Es el rol de superusuario con acceso global a las configuraciones y mantenedores del sistema.
* **Acceso Exclusivo a Módulos:** Proveedores, Productos, Facturas, Bodegas, Funcionarios, Usuarios y Unidades.
* **Funciones:** Crear y editar nuevos usuarios, dar de alta nuevas bodegas y gestionar el catálogo general de productos.

### 2. Encargado de Bodega (`bodega`)
Rol operativo enfocado en el manejo físico y lógico del inventario.
* **Módulos Permitidos:** Stock, Movimientos (Entradas/Salidas) y Solicitudes.
* **Funciones:** Revisar el stock disponible, registrar los movimientos diarios de los productos y aprobar/despachar las solicitudes de materiales.

### 3. Solicitante (`solicitante`)
Rol destinado a los funcionarios que requieren pedir materiales a la bodega.
* **Módulos Permitidos:** Únicamente Solicitudes.
* **Funciones:** Crear nuevas peticiones de insumos y revisar el estado de las mismas.

---

## 📚 Manual de Usuario por Módulos

### Módulos Administrativos (Solo Admin)
* **Usuarios y Funcionarios:** Permite registrar al personal y asignarles credenciales de acceso. Al crear un usuario, se le vincula un rol, una bodega (si es encargado) o una unidad/departamento específico.
* **Bodegas y Unidades:** Definición de los espacios físicos de almacenamiento (Bodegas) y los departamentos de la organización (Unidades).
* **Catálogo (Productos y Proveedores):** Gestión del diccionario de artículos que se manejarán en la bodega, definiendo sus categorías y las empresas que los suministran.
* **Facturas y Órdenes de Compra:** Registro de los documentos tributarios y órdenes previas que respaldan el ingreso de nueva mercadería.

### Módulos Operativos (Admin y Encargado)
* **Stock:** Tablero en tiempo real que refleja las cantidades actuales de cada producto por bodega.
* **Movimientos:** Historial donde se registran las entradas (ingresos por compra) y las salidas (entregas por solicitud).

### Módulos de Flujo (Todos los roles)
* **Solicitudes:** El corazón del autoservicio. Los *Solicitantes* generan listas de productos requeridos. Los *Encargados* reciben la alerta, preparan el pedido y al confirmar la entrega, el sistema genera automáticamente un *Movimiento* de salida, descontando el *Stock*.

---

## ⚙️ Documentación Técnica para Desarrolladores

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

## 🚀 Hoja de Ruta (Cosas por hacerse / To-Do)

- [ ] **Módulo de Reportes:** Creación de dashboards gráficos con métricas de consumo por unidad, productos más solicitados y proyecciones de quiebre de stock.
- [ ] **Alertas de Stock Crítico:** Implementar notificaciones visuales (y por correo) cuando un producto alcance su nivel de stock mínimo definido.
- [ ] **Exportación de Datos:** Añadir botones para exportar tablas (Stock, Movimientos, Facturas) a formatos Excel y PDF.
- [ ] **Auditoría Avanzada (Logs):** Crear un registro (bitácora) invisible que guarde qué usuario creó, editó o eliminó un registro específico junto con la fecha y hora.
- [ ] **Gestión de Devoluciones:** Implementar un flujo para que los solicitantes devuelvan materiales no utilizados a la bodega y el stock se reintegre.
