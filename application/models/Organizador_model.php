<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Organizador_model
 * ─────────────────────────────────────────────────────────────
 * Modelo del núcleo multi-tenant (M01): organizadores y sus dominios.
 *
 * - Tabla principal: rif_organizadores (org_id).
 * - Tabla de dominios: rif_dominios (dom_org_id -> org_id).
 * - Contrato DataTables server-side canónico.
 * - Borrado lógico mediante org_eliminado.
 * - Todas las consultas usan query builder o bindings.
 */
class Organizador_model extends CI_Model
{
    protected $table          = 'rif_organizadores';
    protected $dominios_table = 'rif_dominios';
    protected $primary_key    = 'org_id';

    /**
     * Mapa de columnas ordenables expuestas a DataTables.
     * 0 => nombre público, 1 => slug, 2 => email, 3 => estado.
     */
    protected $columnas_ordenables = [
        0 => 'org_nombre_publico',
        1 => 'org_slug',
        2 => 'org_email',
        3 => 'org_status',
    ];

    /**
     * Slugs reservados para rutas internas o futuras.
     */
    protected static $slugs_reservados = [
        'www', 'app', 'admin', 'administrador', 'api', 'mail', 'email', 'smtp',
        'imap', 'pop', 'pop3', 'webmail', 'autodiscover', 'autoconfig',
        'ns1', 'ns2', 'ns3', 'dns', 'ftp', 'sftp', 'ssh', 'vpn', 'proxy',
        'localhost', 'sortea2', 'sorteo', 'sorteos', 'rifa', 'rifas',
        'sitio', 'publico', 'public', 'errors', 'auth', 'login', 'logout',
        'registro', 'recuperar-clave', 'nueva-clave', 'perfil', 'configuracion',
        'notificaciones', 'webhook', 'webhooks', 'buycredits', 'comprar-creditos',
        'order-history', 'usuarios', 'permisos', 'menus', 'bitacora', 'reportes',
        'dashboard', 'cdn', 'static', 'assets', 'upload', 'uploads', 'media',
        'soporte', 'support', 'help', 'ayuda', 'docs', 'documentation', 'blog',
        'status', 'dev', 'staging', 'test', 'demo', 'panel', 'mi-tienda', 'mi-vitrina',
        'mi-cuenta', 'checkout', 'orden', 'ordenes', 'pago', 'pagos',
        'ganadores', 'terminos', 'privacidad', 'planes', 'plan', 'suscripcion',
        'suscripciones', 'facturacion', 'billing', 'payment', 'pay', 'gateway',
        'reseller', 'partners', 'afiliados', 'mail', 'gateway', 'sistema',
        'organizadores',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    // ─────────────────────────────────────────────────────────
    // CONTRATO DATATABLES SERVER-SIDE
    // ─────────────────────────────────────────────────────────

    /**
     * Total de registros no eliminados (sin filtro).
     *
     * @return int
     */
    public function count_todos()
    {
        return (int)$this->db
            ->where('org_eliminado', 0)
            ->count_all_results($this->table);
    }

    /**
     * Total de registros no eliminados tras aplicar la búsqueda.
     *
     * @param  string $search
     * @return int
     */
    public function count_filtrados($search = '')
    {
        $this->_datatable_query($search);
        return (int)$this->db->count_all_results($this->table);
    }

    /**
     * Registros paginados para DataTables server-side.
     *
     * @param  int    $start
     * @param  int    $length
     * @param  string $search
     * @param  int    $order_col
     * @param  string $order_dir
     * @return array
     */
    public function get_datatable($start, $length, $search = '', $order_col = 0, $order_dir = 'asc')
    {
        $this->_datatable_query($search);

        $columna   = $this->columnas_ordenables[(int)$order_col] ?? 'org_id';
        $direccion = strtolower((string)$order_dir) === 'desc' ? 'DESC' : 'ASC';
        $this->db->order_by($columna, $direccion);

        if ((int)$length > 0) {
            $this->db->limit((int)$length, max(0, (int)$start));
        }

        return $this->db->get($this->table)->result_array();
    }

    /**
     * Query base para conteos y listado DataTables.
     * Filtra siempre por org_eliminado = 0.
     *
     * @param  string $search
     * @return void
     */
    private function _datatable_query($search = '')
    {
        $this->db->where('org_eliminado', 0);

        $search = trim((string)$search);
        if ($search !== '') {
            $this->db->group_start();
            $this->db->like('org_nombre_publico', $search);
            $this->db->or_like('org_slug', $search);
            $this->db->or_like('org_email', $search);
            $this->db->or_like('org_telefono', $search);
            $this->db->group_end();
        }
    }

    // ─────────────────────────────────────────────────────────
    // CONSULTAS
    // ─────────────────────────────────────────────────────────

    /**
     * Obtiene un organizador no eliminado por su ID.
     *
     * @param  int $id
     * @return array|null
     */
    public function get_por_id($id)
    {
        return $this->db
            ->where($this->primary_key, (int)$id)
            ->where('org_eliminado', 0)
            ->limit(1)
            ->get($this->table)
            ->row_array();
    }

    /**
     * Obtiene el organizador asociado a un usuario (perfil de tienda).
     *
     * @param  string $usuario_id
     * @return array|null
     */
    public function get_por_usuario($usuario_id, $incluir_inactivos = FALSE)
    {
        $this->db
            ->where('org_usuario_id', (string)$usuario_id)
            ->where('org_eliminado', 0);

        if (!$incluir_inactivos) {
            $this->db->where('org_status', 1);
        }

        return $this->db
            ->limit(1)
            ->get($this->table)
            ->row_array();
    }

    /**
     * Devuelve el organizador del usuario aunque esté suspendido.
     * Se usa para mostrar el motivo de suspensión en Mi Vitrina.
     *
     * @param  string $usuario_id
     * @return array|null
     */
    public function get_por_usuario_incluir_inactivo($usuario_id)
    {
        return $this->get_por_usuario($usuario_id, TRUE);
    }

    /**
     * Obtiene un organizador no eliminado por su slug (case-insensitive).
     *
     * @param  string $slug
     * @return array|null
     */
    public function get_por_slug($slug)
    {
        $slug = strtolower(trim((string)$slug));
        if ($slug === '') {
            return NULL;
        }

        return $this->db
            ->where('LOWER(org_slug)', $slug)
            ->where('org_eliminado', 0)
            ->limit(1)
            ->get($this->table)
            ->row_array();
    }

    /**
     * Organizadores activos para selects (Select2).
     *
     * @param  string $search
     * @param  int    $limit
     * @return array
     */
    public function get_activos_para_select($search = '', $limit = 50)
    {
        if (!$this->db->table_exists($this->table)) {
            return [];
        }

        $limit = max(1, min(100, (int)$limit));

        $this->db
            ->select('org_id, org_nombre_publico, org_slug')
            ->where('org_status', 1)
            ->where('org_eliminado', 0);

        $search = trim((string)$search);
        if ($search !== '') {
            $this->db->group_start();
            $this->db->like('org_nombre_publico', $search);
            $this->db->or_like('org_slug', $search);
            $this->db->group_end();
        }

        return $this->db
            ->order_by('org_nombre_publico', 'ASC')
            ->limit($limit)
            ->get($this->table)
            ->result_array();
    }

    /**
     * Obtiene un organizador activo a partir de un dominio verificado.
     *
     * Devuelve todos los campos del organizador más:
     * dom_host, dom_tipo y dom_verificado.
     *
     * @param  string $host
     * @return array|null
     */
    public function get_por_host($host)
    {
        $host = $this->_normalizar_host($host);
        if ($host === '') {
            return NULL;
        }

        return $this->db
            ->select('o.*, d.dom_host, d.dom_tipo, d.dom_verificado')
            ->from($this->table . ' o')
            ->join($this->dominios_table . ' d', 'd.dom_org_id = o.org_id', 'inner')
            ->where('d.dom_host', $host)
            ->where('d.dom_status', 1)
            ->group_start()
                ->where('d.dom_tipo', 'subdominio')
                ->or_where('d.dom_verificado', 1)
            ->group_end()
            ->where('o.org_status', 1)
            ->where('o.org_eliminado', 0)
            ->limit(1)
            ->get()
            ->row_array();
    }

    /**
     * Verifica si un slug ya está en uso (case-insensitive).
     *
     * Se consideran también los registros con borrado lógico porque la
     * restricción UNIQUE de la tabla sigue vigente sobre ellos.
     *
     * @param  string $slug
     * @param  int    $ignorar_id
     * @return bool
     */
    public function existe_slug($slug, $ignorar_id = 0)
    {
        $slug = strtolower(trim((string)$slug));
        if ($slug === '') {
            return FALSE;
        }

        $this->db->where('LOWER(org_slug)', $slug);

        if ((int)$ignorar_id > 0) {
            $this->db->where($this->primary_key . ' !=', (int)$ignorar_id);
        }

        return $this->db->count_all_results($this->table) > 0;
    }

    /**
     * Verifica si un usuario ya tiene un organizador registrado.
     *
     * Se consideran los registros con borrado lógico porque la restricción
     * UNIQUE sobre org_usuario_id sigue vigente.
     *
     * @param  string $usuario_id
     * @param  int    $ignorar_id
     * @return bool
     */
    public function existe_usuario($usuario_id, $ignorar_id = 0)
    {
        $usuario_id = trim((string)$usuario_id);
        if ($usuario_id === '') {
            return FALSE;
        }

        $this->db->where('org_usuario_id', $usuario_id);

        if ((int)$ignorar_id > 0) {
            $this->db->where($this->primary_key . ' !=', (int)$ignorar_id);
        }

        return $this->db->count_all_results($this->table) > 0;
    }

    /**
     * Verifica si un host ya está asignado a otro organizador.
     *
     * @param  string $host
     * @param  int    $ignorar_org_id
     * @return bool
     */
    public function existe_host($host, $ignorar_org_id = 0)
    {
        $host = $this->_normalizar_host($host);
        if ($host === '') {
            return FALSE;
        }

        $this->db->where('dom_host', $host);

        if ((int)$ignorar_org_id > 0) {
            $this->db->where('dom_org_id !=', (int)$ignorar_org_id);
        }

        return $this->db->count_all_results($this->dominios_table) > 0;
    }

    /**
     * Indica si un slug está reservado por el sistema.
     *
     * @param  string $slug
     * @return bool
     */
    public function slug_reservado($slug)
    {
        $slug = strtolower(trim((string)$slug));
        if ($slug === '') {
            return TRUE;
        }

        return in_array($slug, self::$slugs_reservados, TRUE);
    }

    /**
     * Lista de slugs reservados (para datalists/ayudas en la vista).
     *
     * @return array
     */
    public function slugs_reservados()
    {
        return self::$slugs_reservados;
    }

    /**
     * Dominio base multi-tenant configurado en el entorno.
     *
     * @return string
     */
    public function base_domain()
    {
        $dominio = strtolower(trim((string)env_value('TENANT_BASE_DOMAIN', 'sortea2.net')));
        $dominio = trim($dominio, " \t\n\r\0\x0B.");

        return $dominio !== '' ? $dominio : 'sortea2.net';
    }

    // ─────────────────────────────────────────────────────────
    // MUTACIONES
    // ─────────────────────────────────────────────────────────

    /**
     * Inserta un organizador.
     *
     * @param  array $data
     * @return int|false ID insertado o FALSE
     */
    public function crear(array $data)
    {
        $data['org_status']     = isset($data['org_status']) ? (int)$data['org_status'] : 1;
        $data['org_verificado'] = isset($data['org_verificado']) ? (int)$data['org_verificado'] : 0;
        $data['org_created_at'] = date('Y-m-d H:i:s');
        $data['org_updated_at'] = date('Y-m-d H:i:s');
        $data['org_eliminado']  = 0;

        if ($this->db->insert($this->table, $data)) {
            return (int)$this->db->insert_id();
        }

        return FALSE;
    }

    /**
     * Crea el organizador y su subdominio en una sola transacción.
     *
     * @param  array  $org
     * @param  string $host
     * @return int|false ID del organizador o FALSE
     */
    public function crear_con_dominio(array $org, $host)
    {
        $host = $this->_normalizar_host($host);
        if ($host === '' || $this->existe_host($host)) {
            return FALSE;
        }

        $this->db->trans_begin();

        try {
            $org_id = $this->crear($org);
            if (!$org_id) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $insertado = $this->db->insert($this->dominios_table, [
                'dom_org_id'     => (int)$org_id,
                'dom_host'       => $host,
                'dom_tipo'       => 'subdominio',
                'dom_verificado' => 1,
                'dom_status'     => 1,
                'dom_created_at' => date('Y-m-d H:i:s'),
                'dom_updated_at' => date('Y-m-d H:i:s'),
            ]);

            if (!$insertado || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return (int)$org_id;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Organizador_model::crear_con_dominio falló al crear el tenant.');
            return FALSE;
        }
    }

    /**
     * Actualiza un organizador no eliminado.
     *
     * @param  int   $id
     * @param  array $data
     * @return bool
     */
    public function actualizar($id, array $data)
    {
        // Whitelist explícita: el slug, el estado y la relación de usuario
        // se modifican únicamente por sus métodos dedicados.
        $permitidos = ['org_nombre_publico', 'org_email', 'org_telefono', 'org_verificado'];
        $data = array_intersect_key($data, array_flip($permitidos));

        if (empty($data)) {
            return FALSE;
        }

        $data['org_updated_at'] = date('Y-m-d H:i:s');

        return $this->db
            ->where($this->primary_key, (int)$id)
            ->where('org_eliminado', 0)
            ->update($this->table, $data);
    }

    /**
     * Borrado lógico de un organizador.
     *
     * @param  int $id
     * @return bool
     */
    public function eliminar($id)
    {
        return $this->db
            ->where($this->primary_key, (int)$id)
            ->where('org_eliminado', 0)
            ->update($this->table, [
                'org_eliminado'  => 1,
                'org_updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Alterna el estado activo/inactivo (1/0) de un organizador.
     *
     * @param  int $id
     * @return array ['ok' => bool, 'nuevo_status' => int, 'organizador' => array]
     */
    public function toggle_status($id)
    {
        $organizador = $this->get_por_id($id);
        if (empty($organizador)) {
            return ['ok' => FALSE, 'nuevo_status' => 0, 'organizador' => []];
        }

        $nuevo_status = ((int)$organizador['org_status'] === 1) ? 0 : 1;

        $ok = $this->db
            ->where($this->primary_key, (int)$id)
            ->where('org_eliminado', 0)
            ->update($this->table, [
                'org_status'     => $nuevo_status,
                'org_updated_at' => date('Y-m-d H:i:s'),
            ]);

        $organizador['org_status'] = $nuevo_status;

        return [
            'ok'           => (bool)$ok,
            'nuevo_status' => $nuevo_status,
            'organizador'  => $organizador,
        ];
    }

    /**
     * Cambia el slug del organizador y su subdominio en una transacción.
     *
     * @param  int    $org_id
     * @param  string $slug
     * @param  string $host
     * @return bool
     */
    public function cambiar_slug($org_id, $slug, $host)
    {
        $slug = strtolower(trim((string)$slug));
        $host = $this->_normalizar_host($host);

        if ($slug === '') {
            return FALSE;
        }

        if ($host === '') {
            $host = $slug . '.' . $this->base_domain();
        }

        if ($this->existe_host($host, $org_id)) {
            return FALSE;
        }

        $this->db->trans_begin();

        try {
            $actualizado = $this->db
                ->where($this->primary_key, (int)$org_id)
                ->where('org_eliminado', 0)
                ->update($this->table, [
                    'org_slug'       => $slug,
                    'org_updated_at' => date('Y-m-d H:i:s'),
                ]);

            if (!$actualizado || !$this->actualizar_dominio($org_id, $host) || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return FALSE;
            }

            $this->db->trans_commit();
            return TRUE;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Organizador_model::cambiar_slug falló al actualizar el tenant.');
            return FALSE;
        }
    }

    /**
     * Obtiene el dominio de tipo subdominio asociado a un organizador.
     *
     * @param  int $org_id
     * @return array|null
     */
    public function get_dominio_por_org($org_id)
    {
        return $this->db
            ->where('dom_org_id', (int)$org_id)
            ->where('dom_tipo', 'subdominio')
            ->order_by('dom_id', 'ASC')
            ->limit(1)
            ->get($this->dominios_table)
            ->row_array();
    }

    /**
     * Actualiza o inserta el subdominio de un organizador.
     *
     * @param  int    $org_id
     * @param  string $host
     * @return bool
     */
    public function actualizar_dominio($org_id, $host)
    {
        $host = $this->_normalizar_host($host);
        if ($host === '') {
            return FALSE;
        }

        $dominio = $this->get_dominio_por_org($org_id);
        $datos = [
            'dom_host'       => $host,
            'dom_tipo'       => 'subdominio',
            'dom_updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($dominio)) {
            // No reactivar por autoservicio un dominio suspendido por admin.
            return $this->db
                ->where('dom_id', (int)$dominio['dom_id'])
                ->update($this->dominios_table, $datos);
        }

        $datos['dom_org_id']     = (int)$org_id;
        $datos['dom_status']     = 1;
        $datos['dom_verificado'] = 1;
        $datos['dom_created_at'] = date('Y-m-d H:i:s');

        return $this->db->insert($this->dominios_table, $datos);
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    /**
     * Normaliza un host: minúsculas, sin puerto, sin punto final y sin www.
     *
     * @param  string $host
     * @return string
     */
    private function _normalizar_host($host)
    {
        $host = strtolower(trim((string)$host));
        if ($host === '') {
            return '';
        }

        $host = preg_replace('/:\d+$/', '', $host);
        $host = rtrim($host, '.');

        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }

        if (!preg_match('/^[a-z0-9.-]{1,191}$/', $host)) {
            return '';
        }

        return $host;
    }
}
