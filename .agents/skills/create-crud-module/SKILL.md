---
name: create-crud-module
description: >-
  Procedimiento portable para crear módulos CRUD administrativos en CodeIgniter
  3 y Metronic siguiendo el patrón canónico de Usuarios.
---

# Skill: Crear Módulo CRUD Administrativo Canónico

## Uso

Aplicar al crear un módulo administrativo nuevo o al completar una capa faltante
de un CRUD existente. Antes de editar, revisar `AGENTS.md`, `MEMORY.md`, el
módulo `Usuarios` y las dependencias reales del proyecto.

Este runbook describe el procedimiento estándar paso a paso para crear un módulo administrativo completo en este proyecto base.

## 1. Fases del Procedimiento

### Fase 0: Inspección Previa de Templates en Metronic (Demo 10 Preferido y Demás Demos)
Antes de escribir código HTML o diseñar la vista:
1. Navegar e inspeccionar preferentemente `c:\xampp\htdocs\proyecto_base_metronic\dist\html\demo10/` o cualquiera de los demás demos (`demo1` a `demo9`) según el tipo de pantalla requerida.
2. Identificar si existe una pantalla o componente análogo para el módulo:
   - Ventas, comercio o inventario: `demo10/store-client/` (catálogo, producto, checkout, órdenes).
   - Facturación o cobros: `demo10/account/billing/`.
   - Miembros, equipos, roles: `demo10/account/members/`.
   - Seguridad, auditoría, sesiones: `demo10/account/security/`.
   - Listados tabulares especializados: `demo10/network/user-table/`.
   - Layouts, barras laterales y widgets generales: `demo1/` (`dashboards/dark-sidebar.html`, etc.) o `demo2` a `demo9`.
3. Adoptar la estructura visual, cards, badges y KeenIcons del demo seleccionado para integrarlos en la vista de `proyecto_base`.

### Fase 1: Base de Datos y Registro
1. **Crear tabla o migración SQL**:
   - Crear archivo en `database/YYYY-MM-DD_create_cab_<modulo>.sql`.
   - Incluir campos de auditoría estándar:
     * `<prefijo>_id` INT AUTO_INCREMENT PRIMARY KEY
     * `<prefijo>_status` TINYINT(1) DEFAULT 1 (1 = activo, 0 = inactivo)
     * `<prefijo>_eliminado` TINYINT(1) DEFAULT 0 (si usa borrado lógico, no mezclar con status)
     * `<prefijo>_fechareg` DATETIME DEFAULT CURRENT_TIMESTAMP
     * `<prefijo>_usureg` VARCHAR(100)
2. **Registrar en Menú (`cab_menu`)**:
   - `men_nombre`: Nombre visible en sidebar.
   - `men_url`: `<Controlador>/index`
   - `men_urlamigable`: `<slug-amigable>`
    - `men_orden`: Posición numérica en menú.
    - `sub_men_id`: 0 para primer nivel, o el `men_id` correspondiente.
    - `men_tipo`, `men_img`, `men_imgmenu`, `men_externo` y `men_inicio` según
      la estructura del menú existente. No usar `men_icono`, `men_padre` o
      `men_status`, porque no existen en la base actual.
3. **Asignar Permisos**:
    - Crear la relación rol-menú en `cab_permisologia` (`mis_permiso = 1000`,
      `mis_menu = <men_id>`).
    - Crear las acciones CRUD en `cab_permiaccion` (`acc_id = mis_id`) para el
      rol administrador o los roles autorizados.

---

### Fase 2: Modelo (`application/models/<Modulo>_model.php`)
El modelo debe concentrar las consultas y el contrato DataTables:
```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class <Modulo>_model extends CI_Model
{
    protected $table = 'cab_<modulo>';
    protected $primary_key = '<prefijo>_id';
    protected $columnas_ordenables = [
        0 => '<prefijo>_id',
        1 => '<prefijo>_nombre',
        // ...
    ];

    public function get_por_id($id)
    {
        return $this->db
            ->where($this->primary_key, $id)
            ->where('<prefijo>_eliminado', 0)
            ->get($this->table)
            ->row_array();
    }

    public function count_todos()
    {
        return $this->db
            ->where('<prefijo>_eliminado', 0)
            ->count_all_results($this->table);
    }

    public function count_filtrados($search = '')
    {
        $this->_datatable_query($search);
        return $this->db->count_all_results($this->table);
    }

    public function get_datatable($start, $length, $search = '', $order_col = 0, $order_dir = 'asc')
    {
        $this->_datatable_query($search);
        
        $columna = $this->columnas_ordenables[$order_col] ?? $this->primary_key;
        $direccion = strtolower($order_dir) === 'desc' ? 'DESC' : 'ASC';
        $this->db->order_by($columna, $direccion);

        if ($length > 0) {
            $this->db->limit($length, $start);
        }

        return $this->db->get($this->table)->result_array();
    }

    private function _datatable_query($search = '')
    {
        $this->db->where('<prefijo>_eliminado', 0);

        if (!empty($search)) {
            $this->db->group_start();
            $this->db->like('<prefijo>_nombre', $search);
            // or_like en otros campos buscables
            $this->db->group_end();
        }
    }

    public function insertar(array $data)
    {
        return $this->db->insert($this->table, $data)
            ? $this->db->insert_id() : FALSE;
    }

    public function actualizar($id, array $data)
    {
        return $this->db
            ->where($this->primary_key, $id)
            ->update($this->table, $data);
    }

    public function eliminar($id)
    {
        // Borrado lógico recomendado
        return $this->db
            ->where($this->primary_key, $id)
            ->update($this->table, ['<prefijo>_eliminado' => 1]);
    }
}
```

---

### Fase 3: Controlador (`application/controllers/<Modulo>.php`)
Debe extender `MY_Controller` e implementar el flujo canónico:
Verificar los permisos por acción, el método HTTP y las rutas directas; la
comprobación de sesión o el encabezado AJAX no sustituyen la autorización ni
la protección CSRF. Normalizar `start` y limitar `length` antes de consultar.
1. `__construct()`: Cargar modelo, configurar `$this->page_title`, `$this->breadcrumb`, y assets vendor (`page_styles`, `page_vendor_scripts`).
2. `index()`: Renderizar la vista principal `pages/<modulo>/index`.
3. `tabla()`: Endpoint AJAX DataTables server-side:
   - Validar `$this->input->is_ajax_request()`.
   - Extraer `draw`, `start`, `length`, `search`, `order`.
   - Llamar a `$this-><Modulo>_model->get_datatable(...)`.
   - Formatear filas con `_formatear_filas_datatable()`.
   - Responder con `$this->json([ 'draw' => ..., 'recordsTotal' => ..., 'recordsFiltered' => ..., 'data' => ... ])`.
4. `datos($id)`: Devolver fila en JSON para cargar modal.
5. `crear()`: Validar POST con `_validar()`, guardar, registrar en bitácora `$this->bitacora('<MODULO>_CREAR', "ID: $nuevo_id | Detalle: ...")` y responder `{ ok: true, msg: '...' }`.
6. `actualizar($id)`: Validar, actualizar, registrar en bitácora `$this->bitacora('<MODULO>_ACTUALIZAR', "ID: $id | Detalle: ...")` y responder `{ ok: true, msg: '...' }`.
7. `eliminar($id)`: Borrado lógico, registrar en bitácora `$this->bitacora('<MODULO>_ELIMINAR', "ID: $id | Detalle: ...")` y responder `{ ok: true, msg: '...' }`.
*Regla Mandatoria: Absolutamente todo nuevo módulo debe registrar cada mutación en `cab_bitacora` sin excepción.*

---

### Fase 4: Vista (`application/views/pages/<modulo>/index.php`)
Debe componerse de:
1. Card principal Metronic demo1 (`card card-flush`) con header, buscador rápido y botón "Nuevo".
2. Tabla DataTables server-side con identificador `#tabla_<modulo>`.
3. Modal único para Crear / Editar (`#modal_<modulo>`).
4. Botones de acción compactos (`btn-sm`) e Icono Obligatorio: Los botones del modal y formularios ("Guardar", "Cancelar", etc.) deben usar obligatoriamente la clase `btn-sm` e incorporar su respectivo icono KeenIcons (`ki-check`, `ki-cross`, etc.). Queda prohibido el uso de botones grandes o con texto plano sin icono.
5. **Compatibilidad Obligatoria con Temas (Modo Claro y Oscuro)**:
   - Todo nuevo módulo debe diseñarse para alternar fluidamente entre Modo Claro y Oscuro (`data-bs-theme="light"` / `data-bs-theme="dark"`).
   - Utilizar exclusivamente clases semánticas Metronic/Bootstrap (`text-gray-900`, `text-gray-700`, `text-muted`, `bg-body`, `bg-light`, `border-gray-200`, `table-row-dashed`).
   - Queda estrictamente prohibido colocar colores fijos en línea (`style="background: #ffffff;"`, `style="color: #000000;"`).
   - Si se requiere CSS adicional, consumir las variables de tema (`var(--bs-primary)`, `var(--bs-body-bg)`, `var(--app-header-bg)`).
6. Script JS que incluya:
   - `inicializar_datatable()`
   - `abrir_crear()` y `abrir_edicion(id)`
   - `limpiar_modal()`
   - `mostrar_errores(errores)`
   - Guardar vía AJAX con botón animado (`data-kt-indicator="on"`)
    - Confirmación de eliminación con SweetAlert2 (`confirmar_eliminar(id, nombre)`).

## Fase 5: Seguridad y QA

1. Pasar la revisión de `security-audit`.
2. Ejecutar la skill `qa-module` y registrar pruebas exitosas y fallidas.
3. No declarar el módulo terminado si existe un bloqueo de seguridad o una
   prueba crítica sin ejecutar.

## Entrega obligatoria

Informar migración, modelo, controlador, vista, permisos, endpoints, bitácora,
revisión de seguridad, pruebas y pendientes.
