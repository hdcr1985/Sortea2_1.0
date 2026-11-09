<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tenant
 * ─────────────────────────────────────────────────────────────
 * Resolución del organizador (tenant) para el núcleo multi-tenant M01.
 *
 * Prioridad:
 *   1. Host real contra rif_dominios (nunca se confía en el subdominio).
 *   2. En entorno local: segmento /sitio/<slug> o query string ?org=<slug>.
 *
 * La librería no renderiza vistas: solo resuelve y expone datos/URLs.
 */
class Tenant
{
    /** @var CI_Controller */
    protected $CI;

    /** @var bool */
    protected $resolved = FALSE;

    /** @var array|null */
    protected $tenant = NULL;

    /** @var string */
    protected $base_url = '';

    /** @var string */
    protected $host = '';

    /** @var string */
    protected $base_domain = '';

    /** @var string Raíz real de la aplicación (assets), antes de reescribir base_url. */
    protected $app_base_url = '';

    /** @var string Base para recursos estáticos del tenant. */
    protected $asset_base = '';

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model('Organizador_model');

        $this->base_domain  = $this->CI->Organizador_model->base_domain();
        $this->app_base_url = rtrim((string)config_item('base_url'), '/') . '/';
    }

    // ─────────────────────────────────────────────────────────
    // RESOLUCIÓN
    // ─────────────────────────────────────────────────────────

    /**
     * Resuelve el tenant de la petición actual.
     *
     * @return bool TRUE si hay tenant resuelto
     */
    public function resolve()
    {
        if ($this->resolved) {
            return TRUE;
        }

        $host = $this->_host_actual();
        if ($host === '') {
            return FALSE;
        }

        // 1. Host real: la autoridad es la base de datos, no el subdominio.
        if (!$this->_es_host_local($host)) {
            $organizador = $this->CI->Organizador_model->get_por_host($host);
            if (empty($organizador)) {
                return FALSE;
            }

            $this->_set_tenant(
                $organizador,
                $this->_scheme() . '://' . $host . '/',
                (string)($organizador['dom_host'] ?? $host),
                FALSE
            );

            return TRUE;
        }

        // 2. Resolución por path: SOLO si está habilitada explícitamente.
        if (!$this->_path_resolution_enabled()) {
            return FALSE;
        }

        // /sitio/<slug> o ?org=<slug>.
        $slug = '';
        if ((string)$this->CI->uri->segment(1) === 'sitio') {
            $slug = (string)$this->CI->uri->segment(2);
        }

        if ($slug === '') {
            $slug = (string)$this->CI->input->get('org', TRUE);
        }

        if ($slug === '') {
            return FALSE;
        }

        return $this->resolve_por_slug($slug);
    }

    /**
     * Resuelve el tenant por slug (usado en entorno local).
     *
     * @param  string $slug
     * @return bool
     */
    public function resolve_por_slug($slug)
    {
        if (!$this->_path_resolution_enabled()) {
            return FALSE;
        }

        $slug = strtolower(trim((string)$slug));
        if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return FALSE;
        }

        $organizador = $this->CI->Organizador_model->get_por_slug($slug);
        if (empty($organizador) || (int)$organizador['org_status'] !== 1) {
            return FALSE;
        }

        // El path solo puede resolver tenants con un subdominio activo.
        $dominio = $this->CI->Organizador_model->get_dominio_por_org((int)$organizador['org_id']);
        if (empty($dominio) || (int)$dominio['dom_status'] !== 1) {
            return FALSE;
        }

        $host = $slug . '.' . $this->base_domain;
        $base = rtrim((string)config_item('base_url'), '/') . '/sitio/' . rawurlencode($slug) . '/';

        // En local el sitio vive bajo /sitio/<slug>/ pero los assets en la raíz.
        $this->_set_tenant($organizador, $base, $host, TRUE);

        return TRUE;
    }

    /**
     * Exige un tenant resuelto o corta con 404.
     *
     * @return bool
     */
    public function require()
    {
        if (!$this->resolved) {
            show_404();
        }

        return TRUE;
    }

    // ─────────────────────────────────────────────────────────
    // ACCESORES
    // ─────────────────────────────────────────────────────────

    public function resolved()
    {
        return $this->resolved;
    }

    public function tenant()
    {
        return $this->tenant;
    }

    public function id()
    {
        return $this->resolved ? (int)$this->tenant['org_id'] : NULL;
    }

    public function slug()
    {
        return $this->resolved ? (string)$this->tenant['org_slug'] : '';
    }

    public function nombre()
    {
        return $this->resolved ? (string)$this->tenant['org_nombre_publico'] : '';
    }

    public function host()
    {
        return $this->resolved ? (string)$this->host : '';
    }

    public function base_url()
    {
        return $this->resolved ? (string)$this->base_url : '';
    }

    public function base_domain()
    {
        return (string)$this->base_domain;
    }

    /**
     * Construye una URL absoluta dentro del tenant.
     *
     * @param  string $path
     * @return string
     */
    public function url($path = '')
    {
        return $this->base_url() . ltrim((string)$path, '/');
    }

    /**
     * Construye una URL de recurso estático.
     *
     * En un host tenant los assets se sirven desde el mismo host. En local
     * viven en la raíz de la aplicación, no bajo /sitio/<slug>/.
     *
     * @param  string $path
     * @return string
     */
    public function asset($path = '')
    {
        $path = ltrim((string)$path, '/');

        return $this->asset_base . $path;
    }

    /**
     * Tema visual del tenant. Valores mínimos hasta la fase M04.
     *
     * @return array
     */
    public function theme()
    {
        return [
            'nombre'           => $this->nombre(),
            'logo_url'         => '',
            'color_primario'   => '#009EF7',
            'color_secundario' => '#181C32',
            'color_fondo'      => '#F9F9F9',
            'texto_hero'       => '',
        ];
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    /**
     * Guarda el tenant resuelto y calcula su URL base.
     *
     * @param  array  $organizador
     * @param  string $base_url
     * @param  string $host
     * @param  bool   $es_local
     * @return void
     */
    protected function _set_tenant(array $organizador, $base_url, $host, $es_local = FALSE)
    {
        $this->tenant     = $organizador;
        $this->base_url   = (string)$base_url;
        $this->host       = (string)$host;
        $this->asset_base = $es_local ? $this->app_base_url : $this->base_url;
        $this->resolved   = TRUE;
    }

    /**
     * Host actual normalizado. Vacío en CLI.
     *
     * @return string
     */
    protected function _host_actual()
    {
        if (PHP_SAPI === 'cli') {
            return '';
        }

        $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
        if ($host === '') {
            return '';
        }

        // IPv6 entre corchetes con puerto: [::1]:8080 o [::1]
        if (substr($host, 0, 1) === '[') {
            if (preg_match('/^\[([a-f0-9:]+)\](?::\d+)?$/', $host, $matches)) {
                $host = $matches[1];
            } else {
                return '';
            }
        } else {
            // IPv4/hostname con puerto: ejemplo.com:8080
            $host = preg_replace('/:\d+$/', '', $host);
        }

        $host = rtrim($host, '.');

        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }

        if ($host === '::1') {
            return $host;
        }

        if (!preg_match('/^[a-z0-9.-]{1,191}$/', $host)) {
            return '';
        }

        return $host;
    }

    /**
     * Habilita /sitio/<slug> y ?org=<slug> solo en desarrollo o por config.
     * En producción debe permanecer deshabilitado.
     *
     * @return bool
     */
    protected function _path_resolution_enabled()
    {
        $default = (defined('ENVIRONMENT') && ENVIRONMENT !== 'production') ? 'true' : 'false';
        $valor   = strtolower(trim((string)env_value('TENANT_ALLOW_PATH_RESOLUTION', $default)));

        return in_array($valor, ['1', 'true', 'yes', 'on'], TRUE);
    }

    /**
     * Determina si el host pertenece al entorno local.
     *
     * @param  string $host
     * @return bool
     */
    protected function _es_host_local($host)
    {
        $host = strtolower(trim((string)$host));
        if ($host === '') {
            return TRUE;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], TRUE)) {
            return TRUE;
        }

        $base_host = strtolower((string)parse_url((string)config_item('base_url'), PHP_URL_HOST));

        return $base_host !== '' && $base_host === $host;
    }

    /**
     * Esquema de la petición actual.
     *
     * @return string
     */
    protected function _scheme()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return 'https';
        }

        $forwarded = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if ($forwarded !== '') {
            $first = explode(',', $forwarded)[0];
            if (trim($first) === 'https') {
                return 'https';
            }
        }

        return 'http';
    }
}
