<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Publico_Controller no usa el prefijo MY_, por lo que CI no lo autocarga.
require_once APPPATH . 'core/Publico_Controller.php';

/**
 * Publico
 * ─────────────────────────────────────────────────────────────
 * Sitio público multi-tenant M07: home del organizador, ficha de rifa,
 * disponibilidad y checkout.
 */
class Publico extends Publico_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('Rifa_model');
        $this->load->model('Rifa_ticket_model');
        $this->load->model('Rifa_imagen_model');
        $this->load->model('Org_metodo_model');
        $this->load->model('Orden_model');
        $this->load->library('session');
    }

    /**
     * GET /sitio/<slug> o raíz de un host tenant.
     */
    public function index($slug = NULL)
    {
        if (!$this->tenant->resolved() && $slug !== NULL && $slug !== '') {
            $this->tenant->resolve_por_slug($slug);
        }

        if (!$this->tenant->resolved()) {
            show_404();
        }

        $this->tenant_data = $this->tenant->tenant();
        $this->config->set_item('base_url', $this->tenant->base_url());
        $org_id = (int)$this->tenant->id();

        $rifas = $this->db
            ->select('r.*, (SELECT COUNT(*) FROM rif_tickets t WHERE t.tick_rifa_id = r.rifa_id) AS ocupados', FALSE)
            ->where('r.rifa_org_id', $org_id)
            ->where('r.rifa_estado', 'activa')
            ->where('r.rifa_eliminado', 0)
            ->order_by('r.rifa_fecha_sorteo', 'ASC')
            ->get('rif_rifas r')
            ->result_array();

        foreach ($rifas as &$rifa) {
            $rifa['disponibles'] = max(0, (int)$rifa['rifa_total_numeros'] - (int)$rifa['ocupados']);
            $rifa['url'] = base_url('rifa/' . rawurlencode((string)$rifa['rifa_slug']));
        }
        unset($rifa);

        $this->render_publico('publico/index', [
            'tenant'        => $this->tenant_data,
            'tenant_data'   => $this->tenant_data,
            'nombre'        => $this->tenant->nombre(),
            'rifas'         => $rifas,
            'url_principal' => $this->_url_principal(),
            'anio'          => (int)date('Y'),
            'page_title'    => $this->tenant->nombre() . ' | Rifas',
        ]);
    }

    public function rifa_local($org_slug = '', $rifa_slug = '')
    {
        $this->_verificar_org_slug($org_slug);
        $this->_render_rifa((string)$rifa_slug);
    }

    public function rifa_host($rifa_slug = '')
    {
        $this->_render_rifa((string)$rifa_slug);
    }

    public function disponibilidad_local($org_slug = '', $rifa_slug = '')
    {
        $this->_verificar_org_slug($org_slug);
        $this->_disponibilidad((string)$rifa_slug);
    }

    public function disponibilidad_host($rifa_slug = '')
    {
        $this->_disponibilidad((string)$rifa_slug);
    }

    public function comprar_local($org_slug = '', $rifa_slug = '')
    {
        $this->_verificar_org_slug($org_slug);
        $this->_comprar((string)$rifa_slug);
    }

    public function comprar_host($rifa_slug = '')
    {
        $this->_comprar((string)$rifa_slug);
    }

    public function orden_local($org_slug = '', $codigo = '')
    {
        $this->_verificar_org_slug($org_slug);
        $this->_orden((string)$codigo);
    }

    public function orden_host($codigo = '')
    {
        $this->_orden((string)$codigo);
    }

    // ─────────────────────────────────────────────────────────
    // PRIVADOS
    // ─────────────────────────────────────────────────────────

    private function _render_rifa($rifa_slug)
    {
        $org_id = (int)$this->tenant->id();
        $rifa   = $this->Rifa_model->get_por_slug($rifa_slug, $org_id);

        if (empty($rifa) || (string)$rifa['rifa_estado'] !== 'activa') {
            show_404();
        }

        $this->Orden_model->expirar_vencidas((int)$rifa['rifa_id']);
        $resumen = $this->Rifa_ticket_model->get_resumen((int)$rifa['rifa_id']);

        $metodos = [];
        foreach ($this->Org_metodo_model->get_rifa_metodos((int)$rifa['rifa_id']) as $metodo) {
            $detalle = $this->Org_metodo_model->get_por_id((int)$metodo['rm_orgmet_id'], $org_id);

            if (!empty($detalle)
                && (int)$detalle['orgmet_activo'] === 1
                && !empty($detalle['completo'])) {
                $metodos[] = [
                    'orgmet_id'    => (int)$detalle['orgmet_id'],
                    'nombre'       => (string)$detalle['met_nombre'],
                    'descripcion'  => (string)($detalle['met_descripcion'] ?? ''),
                    'icono'        => (string)($detalle['met_icono'] ?? 'ki-bill'),
                    'imagen'       => !empty($detalle['met_imagen'])
                        ? base_url('media/metodo/' . (int)$detalle['met_id'])
                        : '',
                ];
            }
        }

        $imagenes = [];
        if ($this->db->table_exists('rif_rifa_imagenes')) {
            foreach ($this->Rifa_imagen_model->get_por_rifa((int)$rifa['rifa_id']) as $img) {
                $imagenes[] = [
                    'id'        => (int)$img['img_id'],
                    'url'       => base_url('media/rifa-imagen/' . (int)$img['img_id']),
                    'principal' => (int)$img['img_principal'] === 1,
                ];
            }
        }

        $csrf_name = $this->security->get_csrf_token_name();
        $csrf_hash = $this->security->get_csrf_hash();
        $slug      = rawurlencode((string)$rifa['rifa_slug']);

        $checkout_token = bin2hex(random_bytes(16));
        $tokens = $this->session->userdata('checkout_tokens');
        if (!is_array($tokens)) {
            $tokens = [];
        }

        $rifa_key = (int)$rifa['rifa_id'];
        if (!isset($tokens[$rifa_key]) || !is_array($tokens[$rifa_key])) {
            $tokens[$rifa_key] = [];
        }
        $tokens[$rifa_key][] = $checkout_token;
        $tokens[$rifa_key] = array_slice($tokens[$rifa_key], -10);

        if (count($tokens) > 5) {
            $tokens = array_slice($tokens, -5, NULL, TRUE);
        }

        $this->session->set_userdata('checkout_tokens', $tokens);

        $this->render_publico('publico/rifa', [
            'tenant'             => $this->tenant_data,
            'tenant_data'        => $this->tenant_data,
            'nombre'             => $this->tenant->nombre(),
            'rifa'               => $rifa,
            'resumen'            => $resumen,
            'metodos'            => $metodos,
            'imagenes'           => $imagenes,
            'csrf_name'          => $csrf_name,
            'csrf_hash'          => $csrf_hash,
            'checkout_token'     => $checkout_token,
            'disponibilidad_url' => base_url('rifa/' . $slug . '/disponibilidad'),
            'comprar_url'        => base_url('rifa/' . $slug . '/comprar'),
            'url_principal'      => $this->_url_principal(),
            'anio'               => (int)date('Y'),
            'page_title'         => (string)$rifa['rifa_titulo'] . ' | ' . $this->tenant->nombre(),
        ]);
    }

    private function _disponibilidad($rifa_slug)
    {
        $org_id = (int)$this->tenant->id();
        $rifa   = $this->Rifa_model->get_por_slug($rifa_slug, $org_id);

        if (empty($rifa) || (string)$rifa['rifa_estado'] !== 'activa') {
            return $this->json(['ok' => FALSE, 'msg' => 'Rifa no encontrada.'], 404);
        }

        $offset = $this->input->get('offset', TRUE);
        $limit  = $this->input->get('limit', TRUE);

        $offset = is_scalar($offset) ? max(0, (int)$offset) : 0;
        $limit  = is_scalar($limit) ? (int)$limit : 200;

        $this->json([
            'ok'   => TRUE,
            'data' => $this->Rifa_ticket_model->get_mapa_publico(
                (int)$rifa['rifa_id'],
                $offset,
                $limit,
                (int)$rifa['rifa_mostrar_reservados']
            ),
        ]);
    }

    private function _comprar($rifa_slug)
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $org_id = (int)$this->tenant->id();
        $rifa   = $this->Rifa_model->get_por_slug($rifa_slug, $org_id);

        if (empty($rifa) || (string)$rifa['rifa_estado'] !== 'activa') {
            return $this->json(['ok' => FALSE, 'msg' => 'La rifa no está disponible.'], 404);
        }

        $rifa_id      = (int)$rifa['rifa_id'];
        $tokens       = $this->session->userdata('checkout_tokens');
        $posted_token = (string)$this->_post_escalar('checkout_token');
        $validos      = is_array($tokens) && isset($tokens[$rifa_id]) && is_array($tokens[$rifa_id])
            ? $tokens[$rifa_id]
            : [];

        $token_valido = FALSE;
        foreach ($validos as $token) {
            if (is_string($token) && $posted_token !== '' && hash_equals($token, $posted_token)) {
                $token_valido = TRUE;
                break;
            }
        }

        if (!$token_valido) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'La sesión de compra expiró. Recarga la rifa e inténtalo nuevamente.',
            ], 422);
        }

        $ultimo_intento = (int)$this->session->userdata('checkout_last_at_' . $rifa_id);
        if ($ultimo_intento > 0 && (time() - $ultimo_intento) < 10) {
            $retry = 10 - (time() - $ultimo_intento);
            $this->output->set_header('Retry-After: ' . max(1, $retry));

            return $this->json([
                'ok'    => FALSE,
                'msg'   => 'Espera unos segundos antes de crear otra reserva.',
                'retry' => max(1, $retry),
            ], 429);
        }

        $ip = trim((string)$this->input->server('REMOTE_ADDR'));
        if ($ip === '') {
            $ip = (string)app_resolver_client_ip($this->input);
        }

        $comprador = [
            'nombre'    => (string)$this->_post_escalar('nombre'),
            'email'     => (string)$this->_post_escalar('email'),
            'telefono'  => (string)$this->_post_escalar('telefono'),
            'documento' => (string)$this->_post_escalar('documento'),
            'notas'     => (string)$this->_post_escalar('notas'),
        ];

        if ($comprador['nombre'] === '' || !filter_var($comprador['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'ok'      => FALSE,
                'msg'     => 'Completa tu nombre y un correo válido.',
                'errores' => [
                    'nombre' => $comprador['nombre'] === '' ? 'El nombre es obligatorio.' : '',
                    'email'  => filter_var($comprador['email'], FILTER_VALIDATE_EMAIL) ? '' : 'El correo no es válido.',
                ],
            ], 422);
        }

        $res = $this->Orden_model->crear(
            (int)$rifa['rifa_id'],
            $org_id,
            $comprador,
            (int)$this->_post_escalar('orgmet_id', '0'),
            (string)$this->_post_escalar('numeros'),
            (int)$this->_post_escalar('cantidad', '0'),
            $ip,
            (string)$this->input->user_agent()
        );

        if (!$res['ok']) {
            return $this->_respuesta_compra($res, $rifa);
        }

        $orden = $res['orden'];
        $url   = base_url('orden/' . rawurlencode((string)$orden['ord_codigo']));

        $tokens = $this->session->userdata('checkout_tokens');
        if (is_array($tokens) && isset($tokens[$rifa_id]) && is_array($tokens[$rifa_id])) {
            $restantes = [];
            foreach ($tokens[$rifa_id] as $token) {
                if (!is_string($token) || !hash_equals($token, $posted_token)) {
                    $restantes[] = $token;
                }
            }

            if (empty($restantes)) {
                unset($tokens[$rifa_id]);
            } else {
                $tokens[$rifa_id] = $restantes;
            }

            $this->session->set_userdata('checkout_tokens', $tokens);
        }
        $this->session->set_userdata('checkout_last_at_' . $rifa_id, time());

        $this->json([
            'ok'     => TRUE,
            'msg'    => 'Reserva creada correctamente.',
            'orden'  => [
                'codigo' => (string)$orden['ord_codigo'],
                'url'    => $url,
                'total'  => (float)$orden['ord_total'],
                'moneda' => (string)$orden['ord_moneda'],
            ],
            'tickets' => $res['tickets'],
        ]);
    }

    private function _respuesta_compra(array $res, array $rifa = [])
    {
        $motivo = (string)($res['motivo'] ?? 'error');

        if ($motivo === 'rate_limit' || $motivo === 'limite_inventario') {
            $retry = max(30, (int)($res['retry'] ?? 300));
            $this->output->set_header('Retry-After: ' . $retry);

            return $this->json([
                'ok'    => FALSE,
                'msg'   => (string)($res['msg'] ?? 'Demasiados intentos. Intenta más tarde.'),
                'retry' => $retry,
            ], 429);
        }

        $mapa = [
            'no_encontrada'       => [404, 'La rifa no existe.'],
            'no_activa'           => [422, 'La rifa no está activa.'],
            'rifa_cerrada'        => [422, 'La rifa ya cerró.'],
            'solo_pagas'          => [422, 'Esta rifa no requiere compra.'],
            'comprador_invalido'  => [422, 'Completa tu nombre y un correo válido.'],
            'metodo_invalido'     => [422, 'Selecciona un método de pago válido.'],
            'cantidad_invalida'   => [422, 'Indica una cantidad válida.'],
            'sin_disponibilidad'  => [409, 'No hay suficientes números disponibles.'],
            'no_disponible'       => [500, 'El checkout no está disponible.'],
        ];

        if ($motivo === 'max_compra') {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'Puedes comprar hasta ' . (int)($res['max'] ?? 1) . ' números por orden.',
            ], 422);
        }

        if ($motivo === 'numeros_ocupados') {
            $conflictos = $res['conflictos'] ?? [];

            if ((int)($rifa['rifa_mostrar_reservados'] ?? 1) === 0) {
                $conflictos = array_map(function ($conflicto) {
                    return [
                        'numero' => (string)($conflicto['numero'] ?? ''),
                        'estado' => 'no_disponible',
                    ];
                }, $conflictos);
            }

            return $this->json([
                'ok'          => FALSE,
                'msg'         => 'Algunos números ya no están disponibles. Actualiza la lista.',
                'conflictos'  => $conflictos,
            ], 409);
        }

        if ($motivo === 'numeros_invalidos') {
            return $this->json(['ok' => FALSE, 'msg' => 'La selección de números no es válida.'], 422);
        }

        list($status, $msg) = $mapa[$motivo] ?? [500, 'No se pudo crear la reserva.'];

        return $this->json(['ok' => FALSE, 'msg' => $msg], $status);
    }

    private function _orden($codigo)
    {
        $org_id = (int)$this->tenant->id();
        $orden  = $this->Orden_model->get_por_codigo($codigo, $org_id);

        if (empty($orden)) {
            show_404();
        }

        if (in_array((string)$orden['ord_estado'], ['pendiente', 'en_revision'], TRUE)) {
            $this->Orden_model->expirar_vencidas((int)$orden['ord_rifa_id']);
            $orden = $this->Orden_model->get_por_codigo($codigo, $org_id);
        }

        $snapshot = [];
        if (!empty($orden['ord_metodo_snapshot'])) {
            $snapshot = json_decode((string)$orden['ord_metodo_snapshot'], TRUE) ?: [];
        }

        $this->output->set_header('X-Robots-Tag: noindex, nofollow');
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $this->render_publico('publico/orden', [
            'tenant'        => $this->tenant_data,
            'tenant_data'   => $this->tenant_data,
            'nombre'        => $this->tenant->nombre(),
            'orden'         => $orden,
            'snapshot'      => $snapshot,
            'robots'        => 'noindex, nofollow',
            'url_principal' => $this->_url_principal(),
            'anio'          => (int)date('Y'),
            'page_title'    => 'Orden ' . (string)$orden['ord_codigo'] . ' | ' . $this->tenant->nombre(),
        ]);
    }

    private function _verificar_org_slug($org_slug)
    {
        if (strtolower(trim((string)$org_slug)) !== (string)$this->tenant->slug()) {
            show_404();
        }
    }

    private function _post_escalar($key, $default = '')
    {
        $valor = $this->input->post($key, TRUE);
        return is_scalar($valor) ? (string)$valor : $default;
    }

    private function _url_principal()
    {
        $principal = trim((string)env_value('APP_BASE_URL', ''));

        if ($principal === '') {
            $principal = (string)config_item('base_url');
        }

        return rtrim($principal, '/') . '/';
    }
}
