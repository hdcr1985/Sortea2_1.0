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
            'media_base'    => $this->tenant->asset('media/'),
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

    public function aleatorios_local($org_slug = '', $rifa_slug = '')
    {
        $this->_verificar_org_slug($org_slug);
        $this->_aleatorios((string)$rifa_slug);
    }

    public function aleatorios_host($rifa_slug = '')
    {
        $this->_aleatorios((string)$rifa_slug);
    }

    public function captura_local($org_slug = '', $codigo = '')
    {
        $this->_verificar_org_slug($org_slug);
        $this->_captura((string)$codigo);
    }

    public function captura_host($codigo = '')
    {
        $this->_captura((string)$codigo);
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
                    'orgmet_id'     => (int)$detalle['orgmet_id'],
                    'nombre'        => (string)$detalle['met_nombre'],
                    'descripcion'   => (string)($detalle['met_descripcion'] ?? ''),
                    'instrucciones' => (string)($detalle['met_instrucciones'] ?? ''),
                    'icono'         => (string)($detalle['met_icono'] ?? 'ki-bill'),
                    'imagen'        => !empty($detalle['met_imagen'])
                        ? $this->tenant->asset('media/metodo/' . (int)$detalle['met_id'])
                        : '',
                ];
            }
        }

        $imagenes = [];
        if ($this->db->table_exists('rif_rifa_imagenes')) {
            foreach ($this->Rifa_imagen_model->get_por_rifa((int)$rifa['rifa_id']) as $img) {
                $imagenes[] = [
                    'id'        => (int)$img['img_id'],
                    'url'       => $this->tenant->asset('media/rifa-imagen/' . (int)$img['img_id']),
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
            'media_base'          => $this->tenant->asset('media/'),
            'csrf_name'          => $csrf_name,
            'csrf_hash'          => $csrf_hash,
            'checkout_token'     => $checkout_token,
            'disponibilidad_url' => base_url('rifa/' . $slug . '/disponibilidad'),
            'comprar_url'        => base_url('rifa/' . $slug . '/comprar'),
            'aleatorios_url'     => base_url('rifa/' . $slug . '/aleatorios'),
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

        $limite = $this->_limite_publico('disponibilidad', (int)$rifa['rifa_id'], 120, 60);
        if (empty($limite['ok'])) {
            return $this->_respuesta_limite_publico($limite);
        }

        $offset = $this->input->get('offset', TRUE);
        $limit  = $this->input->get('limit', TRUE);
        $buscar = $this->input->get('buscar', TRUE);

        $offset = is_scalar($offset) ? max(0, (int)$offset) : 0;
        $limit  = is_scalar($limit) ? (int)$limit : 200;
        $buscar = is_scalar($buscar) ? trim((string)$buscar) : '';

        $data = $buscar !== ''
            ? $this->Rifa_ticket_model->buscar_numero_publico(
                (int)$rifa['rifa_id'],
                $buscar,
                (int)$rifa['rifa_mostrar_reservados']
            )
            : $this->Rifa_ticket_model->get_mapa_publico(
                (int)$rifa['rifa_id'],
                $offset,
                $limit,
                (int)$rifa['rifa_mostrar_reservados']
            );

        $this->json([
            'ok'   => TRUE,
            'data' => $data,
        ]);
    }

    private function _aleatorios($rifa_slug)
    {
        if (strtoupper($this->input->method(TRUE)) !== 'GET') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $org_id = (int)$this->tenant->id();
        $rifa   = $this->Rifa_model->get_por_slug($rifa_slug, $org_id);

        if (empty($rifa) || (string)$rifa['rifa_estado'] !== 'activa') {
            return $this->json(['ok' => FALSE, 'msg' => 'Rifa no encontrada.'], 404);
        }

        $limite = $this->_limite_publico('aleatorios', (int)$rifa['rifa_id'], 30, 60);
        if (empty($limite['ok'])) {
            return $this->_respuesta_limite_publico($limite);
        }

        $cantidad = $this->input->get('cantidad', TRUE);
        $cantidad = is_scalar($cantidad) ? (int)$cantidad : 0;
        $cantidad = max(1, min(max(1, (int)$rifa['rifa_max_por_compra']), $cantidad));

        $res = $this->Rifa_ticket_model->get_aleatorios_publicos(
            (int)$rifa['rifa_id'],
            $cantidad
        );

        if (empty($res['ok'])) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'No hay suficientes números disponibles para la selección al azar.',
            ], 409);
        }

        $this->json([
            'ok'   => TRUE,
            'data' => $res,
        ]);
    }

    private function _captura($codigo)
    {
        $org_id = (int)$this->tenant->id();
        $orden  = $this->Orden_model->get_por_codigo((string)$codigo, $org_id);

        if (empty($orden) || empty($orden['ord_pago_captura'])) {
            show_404();
        }

        $archivo = basename((string)$orden['ord_pago_captura']);
        $ruta    = rtrim(FCPATH, '\\/')
            . DIRECTORY_SEPARATOR . 'upload'
            . DIRECTORY_SEPARATOR . 'ordenes'
            . DIRECTORY_SEPARATOR . (int)$orden['ord_org_id']
            . DIRECTORY_SEPARATOR . $archivo;

        if (!is_file($ruta)) {
            show_404();
        }

        $mime = $this->_detectar_mime_pago($ruta);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], TRUE)) {
            show_404();
        }

        if (ob_get_level() > 0) {
            ob_clean();
        }

        $this->output
            ->set_content_type($mime)
            ->set_header('Content-Length: ' . filesize($ruta))
            ->set_header('Cache-Control: private, no-store, max-age=0')
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_header('X-Robots-Tag: noindex, nofollow')
            ->set_output(file_get_contents($ruta));
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

        $pago_soporte = trim((string)$this->_post_escalar('pago_soporte'));

        if ($pago_soporte === '') {
            return $this->json([
                'ok'      => FALSE,
                'msg'     => 'Indica el soporte o referencia del pago.',
                'errores' => ['pago_soporte' => 'El soporte de pago es obligatorio.'],
            ], 422);
        }

        if ($this->_longitud($pago_soporte) > 120) {
            return $this->json([
                'ok'      => FALSE,
                'msg'     => 'El soporte de pago no puede exceder 120 caracteres.',
                'errores' => ['pago_soporte' => 'Máximo 120 caracteres.'],
            ], 422);
        }

        $precheck = $this->_validar_precheckout($rifa, $org_id);
        if (empty($precheck['ok'])) {
            return $this->json([
                'ok'      => FALSE,
                'msg'     => (string)($precheck['msg'] ?? 'Revisa los datos de la compra.'),
                'errores' => isset($precheck['errores']) && is_array($precheck['errores'])
                    ? $precheck['errores']
                    : [],
            ], 422);
        }

        $limite_subida = $this->_limite_subida_checkout($rifa_id);
        if (empty($limite_subida['ok'])) {
            $retry = max(1, (int)($limite_subida['retry'] ?? 300));
            $this->output->set_header('Retry-After: ' . $retry);
            return $this->json([
                'ok'    => FALSE,
                'msg'   => 'Demasiados intentos con comprobante. Espera unos minutos e inténtalo nuevamente.',
                'retry' => $retry,
            ], 429);
        }

        $captura = $this->_subir_captura_pago($org_id);
        if (!$captura['ok']) {
            return $this->json([
                'ok'      => FALSE,
                'msg'     => (string)$captura['error'],
                'errores' => ['pago_captura' => (string)$captura['error']],
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
            (string)$this->input->user_agent(),
            [
                'soporte' => $pago_soporte,
                'captura' => $captura['archivo'],
                'mime'    => $captura['mime'],
                'size'    => $captura['size'],
            ]
        );

        if (!$res['ok']) {
            $this->_borrar_captura_pago($org_id, (string)$captura['archivo']);
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
            'solo_pagas'          => [422, 'Esta rifa no tiene un precio de ticket disponible.'],
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
            'captura_url'   => $this->_url_captura_orden($orden),
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

    private function _limite_publico($accion, $rifa_id, $max, $ventana)
    {
        return $this->_consumir_rate_limit(
            'public:' . preg_replace('/[^a-z_]/i', '', (string)$accion),
            (int)$rifa_id,
            (int)$max,
            (int)$ventana
        );
    }

    private function _respuesta_limite_publico(array $limite)
    {
        $retry = max(1, (int)($limite['retry'] ?? 60));
        $this->output->set_header('Retry-After: ' . $retry);

        return $this->json([
            'ok'    => FALSE,
            'msg'   => 'Demasiadas solicitudes. Espera unos segundos e inténtalo nuevamente.',
            'retry' => $retry,
        ], 429);
    }

    private function _limite_subida_checkout($rifa_id)
    {
        return $this->_consumir_rate_limit('upload', (int)$rifa_id, 5, 300);
    }

    private function _consumir_rate_limit($accion, $rifa_id, $max, $ventana)
    {
        // Para el rate limit se usa la IP de la conexión directa. No se confía
        // en X-Forwarded-For / CF-Connecting-IP sin una allowlist de proxy.
        $ip = trim((string)$this->input->server('REMOTE_ADDR'));
        if ($ip === '') {
            $ip = 'unknown';
        }

        $clave = substr($accion . ':' . (int)$rifa_id . ':' . $ip, 0, 150);
        $ahora = time();

        if ($this->db->table_exists('rif_rate_limits')) {
            $inicio_ventana = date('Y-m-d H:i:s', $ahora - (int)$ventana);

            $this->db->query(
                "INSERT INTO rif_rate_limits (rate_clave, rate_inicio, rate_count)
                 VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE
                   rate_count = IF(rate_inicio < ?, 1, rate_count + 1),
                   rate_inicio = IF(rate_inicio < ?, VALUES(rate_inicio), rate_inicio)",
                [$clave, date('Y-m-d H:i:s', $ahora), $inicio_ventana, $inicio_ventana]
            );

            $fila = $this->db
                ->select('rate_inicio, rate_count')
                ->where('rate_clave', $clave)
                ->limit(1)
                ->get('rif_rate_limits')
                ->row_array();

            if (empty($fila)) {
                return ['ok' => TRUE, 'retry' => 0];
            }

            if ((int)$fila['rate_count'] > (int)$max) {
                return [
                    'ok'    => FALSE,
                    'retry' => max(1, (int)$ventana - ($ahora - strtotime((string)$fila['rate_inicio']))),
                ];
            }

            if (mt_rand(1, 200) === 1) {
                $this->db
                    ->where('rate_inicio <', date('Y-m-d H:i:s', $ahora - 86400))
                    ->delete('rif_rate_limits');
            }

            return ['ok' => TRUE, 'retry' => 0];
        }

        // Fallback si la migracion aun no fue aplicada.
        $clave_session = 'public_rate_' . preg_replace('/[^a-z_]/i', '', (string)$accion) . '_' . (int)$rifa_id;
        $hist = $this->session->userdata($clave_session);

        if (!is_array($hist)) {
            $hist = [];
        }

        $hist = array_values(array_filter($hist, function ($marca) use ($ahora, $ventana) {
            return (int)$marca > ($ahora - (int)$ventana);
        }));

        if (count($hist) >= (int)$max) {
            return [
                'ok'    => FALSE,
                'retry' => max(1, (int)$ventana - ($ahora - min($hist))),
            ];
        }

        $hist[] = $ahora;
        $this->session->set_userdata($clave_session, $hist);

        return ['ok' => TRUE, 'retry' => 0];
    }

    private function _validar_precheckout(array $rifa, $org_id)
    {
        $orgmet_id = (int)$this->_post_escalar('orgmet_id', '0');

        if ($orgmet_id <= 0) {
            return [
                'ok'      => FALSE,
                'msg'     => 'Selecciona un método de pago.',
                'errores' => ['orgmet_id' => 'Selecciona un método de pago.'],
            ];
        }

        $metodo = $this->Org_metodo_model->get_por_id($orgmet_id, $org_id);

        if (empty($metodo)
            || (int)($metodo['orgmet_activo'] ?? 0) !== 1
            || empty($metodo['completo'])) {
            return [
                'ok'      => FALSE,
                'msg'     => 'El método de pago seleccionado no está disponible.',
                'errores' => ['orgmet_id' => 'El método de pago no está disponible.'],
            ];
        }

        $vinculado = FALSE;
        foreach ($this->Org_metodo_model->get_rifa_metodos((int)$rifa['rifa_id']) as $metodo_rifa) {
            if ((int)$metodo_rifa['rm_orgmet_id'] === $orgmet_id
                && (int)$metodo_rifa['orgmet_activo'] === 1
                && (int)$metodo_rifa['met_status'] === 1
                && (int)$metodo_rifa['met_eliminado'] === 0) {
                $vinculado = TRUE;
                break;
            }
        }

        if (!$vinculado) {
            return [
                'ok'      => FALSE,
                'msg'     => 'El método de pago no pertenece a esta rifa.',
                'errores' => ['orgmet_id' => 'El método de pago no pertenece a esta rifa.'],
            ];
        }

        $max = max(1, (int)$rifa['rifa_max_por_compra']);

        if ((string)$rifa['rifa_modo_asignacion'] === 'manual') {
            $raw = trim((string)$this->_post_escalar('numeros'));

            if ($raw === '') {
                return [
                    'ok'      => FALSE,
                    'msg'     => 'Selecciona al menos un número.',
                    'errores' => ['numeros' => 'Selecciona al menos un número.'],
                ];
            }

            $partes = explode(',', $raw);

            if (count($partes) > $max) {
                return [
                    'ok'      => FALSE,
                    'msg'     => 'Superaste el máximo de números por orden.',
                    'errores' => ['numeros' => 'Máximo ' . $max . ' números por orden.'],
                ];
            }

            $desde = (int)$rifa['rifa_numero_desde'];
            $hasta = (int)$rifa['rifa_numero_hasta'];

            foreach ($partes as $parte) {
                $parte = trim((string)$parte);
                if ($parte === '' || !ctype_digit($parte)
                    || (int)$parte < $desde || (int)$parte > $hasta) {
                    return [
                        'ok'      => FALSE,
                        'msg'     => 'La selección de números no es válida.',
                        'errores' => ['numeros' => 'La selección de números no es válida.'],
                    ];
                }
            }
        } else {
            $cantidad = (int)$this->_post_escalar('cantidad', '0');

            if ($cantidad < 1 || $cantidad > $max) {
                return [
                    'ok'      => FALSE,
                    'msg'     => 'Indica una cantidad entre 1 y ' . $max . '.',
                    'errores' => ['cantidad' => 'Indica una cantidad entre 1 y ' . $max . '.'],
                ];
            }
        }

        return ['ok' => TRUE];
    }

    private function _longitud($valor)
    {
        return function_exists('mb_strlen')
            ? mb_strlen((string)$valor, 'UTF-8')
            : strlen((string)$valor);
    }

    private function _subir_captura_pago($org_id)
    {
        if (!isset($_FILES['pago_captura']) || !is_array($_FILES['pago_captura'])) {
            return ['ok' => FALSE, 'error' => 'Adjunta la captura del pago.', 'archivo' => '', 'mime' => '', 'size' => 0];
        }

        $archivo = $_FILES['pago_captura'];

        if (!is_array($archivo) || is_array($archivo['name'] ?? NULL)) {
            return ['ok' => FALSE, 'error' => 'El archivo de captura no es válido.', 'archivo' => '', 'mime' => '', 'size' => 0];
        }

        $error = (int)($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => FALSE, 'error' => 'Adjunta la captura del pago.', 'archivo' => '', 'mime' => '', 'size' => 0];
        }

        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => FALSE, 'error' => 'No se pudo recibir la captura del pago.', 'archivo' => '', 'mime' => '', 'size' => 0];
        }

        $size = (int)($archivo['size'] ?? 0);
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            return ['ok' => FALSE, 'error' => 'La captura debe pesar menos de 5 MB.', 'archivo' => '', 'mime' => '', 'size' => 0];
        }

        $tmp = (string)($archivo['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => FALSE, 'error' => 'El archivo de captura no es válido.', 'archivo' => '', 'mime' => '', 'size' => 0];
        }

        $mime = $this->_detectar_mime_pago($tmp);
        $permitidos = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($permitidos[$mime])) {
            return ['ok' => FALSE, 'error' => 'La captura debe ser JPG, PNG o WEBP.', 'archivo' => '', 'mime' => '', 'size' => 0];
        }

        $dir = rtrim(FCPATH, '\\/')
            . DIRECTORY_SEPARATOR . 'upload'
            . DIRECTORY_SEPARATOR . 'ordenes'
            . DIRECTORY_SEPARATOR . (int)$org_id;

        if (!is_dir($dir) && !@mkdir($dir, 0755, TRUE) && !is_dir($dir)) {
            return ['ok' => FALSE, 'error' => 'No se pudo preparar el almacenamiento de la captura.', 'archivo' => '', 'mime' => '', 'size' => 0];
        }

        $nombre = 'pago_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $permitidos[$mime];
        $destino = $dir . DIRECTORY_SEPARATOR . $nombre;

        if (!@move_uploaded_file($tmp, $destino)) {
            return ['ok' => FALSE, 'error' => 'No se pudo guardar la captura del pago.', 'archivo' => '', 'mime' => '', 'size' => 0];
        }

        @chmod($destino, 0644);

        return [
            'ok'      => TRUE,
            'error'   => '',
            'archivo' => $nombre,
            'mime'    => $mime,
            'size'    => $size,
        ];
    }

    private function _borrar_captura_pago($org_id, $archivo)
    {
        $archivo = basename(trim((string)$archivo));
        if ($archivo === '') {
            return;
        }

        $ruta = rtrim(FCPATH, '\\/')
            . DIRECTORY_SEPARATOR . 'upload'
            . DIRECTORY_SEPARATOR . 'ordenes'
            . DIRECTORY_SEPARATOR . (int)$org_id
            . DIRECTORY_SEPARATOR . $archivo;

        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }

    private function _detectar_mime_pago($ruta)
    {
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $ruta) ?: 'application/octet-stream';
                if (PHP_VERSION_ID < 80500 && function_exists('finfo_close')) {
                    @finfo_close($finfo);
                }
                unset($finfo);
                return $mime;
            }
        }

        if (function_exists('getimagesize')) {
            $info = @getimagesize($ruta);
            if ($info !== FALSE && !empty($info['mime'])) {
                return (string)$info['mime'];
            }
        }

        return 'application/octet-stream';
    }

    private function _url_captura_orden(array $orden)
    {
        if (empty($orden['ord_pago_captura']) || empty($orden['ord_codigo'])) {
            return '';
        }

        $archivo = basename((string)$orden['ord_pago_captura']);
        $ruta    = rtrim(FCPATH, '\\/')
            . DIRECTORY_SEPARATOR . 'upload'
            . DIRECTORY_SEPARATOR . 'ordenes'
            . DIRECTORY_SEPARATOR . (int)($orden['ord_org_id'] ?? 0)
            . DIRECTORY_SEPARATOR . $archivo;

        if (!is_file($ruta)) {
            return '';
        }

        return base_url('orden/' . rawurlencode((string)$orden['ord_codigo']) . '/captura');
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
