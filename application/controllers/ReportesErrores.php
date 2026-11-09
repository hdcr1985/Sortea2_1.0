<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ReportesErrores
 * ─────────────────────────────────────────────────────────────
 * Visor de solo lectura de los logs de error de CodeIgniter 3.
 *
 * Contrato:
 *   GET  index()   -> vista
 *   POST tabla()   -> DataTables server-side
 *   POST detalle() -> JSON con el detalle de una entrada
 *
 * El men_id se resuelve dinámicamente desde cab_menu; nunca se hardcodea.
 * Las rutas del módulo no se declaran en routes.php.
 */
class ReportesErrores extends MY_Controller
{
    const NIVELES = ['', 'ERROR', 'DEBUG', 'INFO', 'WARNING', 'NOTICE', 'CRITICAL', 'ALERT', 'EMERGENCY', 'ALL'];
    const LONGITUDES = [10, 25, 50, 100];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Reportes_errores_model');
        $this->load->model('Configuracion_general_model');

        $this->page_styles = ['assets/plugins/custom/datatables/datatables.bundle.css'];
        $this->page_vendor_scripts = ['assets/plugins/custom/datatables/datatables.bundle.js'];

        $this->page_title = 'Reporte de Errores';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Reporte de Errores', 'url' => ''],
        ];
    }

    // ─────────────────────────────────────────────────────────
    // Vistas
    // ─────────────────────────────────────────────────────────

    public function index()
    {
        $this->_no_cache();

        $ajustes = $this->_ajustes_reporte();
        $menu_id = $this->Reportes_errores_model->menu_id();

        // El rol 1000 puede ver el aviso de migración pendiente; cualquier otro
        // rol queda denegado aunque el menú aún no exista (deny-by-default).
        $this->autorizar_accion($menu_id, 'consultar', 'No tienes permiso para consultar el reporte de errores.');

        // Sin registro en cab_menu no se parsea nada: la migración del menú
        // y permisos aún está pendiente.
        if ($menu_id <= 0) {
            $this->render('pages/reportes_errores/index', [
                'activo'              => FALSE,
                'disponible'          => FALSE,
                'migracion_pendiente' => TRUE,
                'dias'                => $ajustes['dias'],
                'max_entradas'        => $ajustes['max'],
                'desde'               => $ajustes['desde'],
                'hasta'               => $ajustes['hasta'],
                'total_aprox'         => 0,
            ]);
            return;
        }

        $disponible = $this->Reportes_errores_model->logs_disponibles();

        // total_aprox es informativo y opcional: no se parsean logs en el
        // render para no bloquear la vista (la ventana puede llegar a 90 días).
        // El total real lo entrega tabla() como recordsTotal.
        $total = 0;

        $this->render('pages/reportes_errores/index', [
            'activo'              => $ajustes['activo'],
            'disponible'          => $disponible,
            'migracion_pendiente' => FALSE,
            'dias'                => $ajustes['dias'],
            'max_entradas'        => $ajustes['max'],
            'desde'               => $ajustes['desde'],
            'hasta'               => $ajustes['hasta'],
            'total_aprox'         => $total,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // DataTables server-side
    // ─────────────────────────────────────────────────────────

    public function tabla()
    {
        $this->_no_cache();

        if (strtoupper((string)$this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        if (!$this->input->is_ajax_request()) {
            return $this->json(['ok' => FALSE, 'msg' => 'La solicitud debe ser AJAX.'], 400);
        }

        $menu_id = $this->Reportes_errores_model->menu_id();
        if ($menu_id <= 0) {
            return $this->json(['ok' => FALSE, 'msg' => 'El módulo no está registrado en el menú.'], 403);
        }

        $this->autorizar_accion($menu_id, 'consultar', 'No tienes permiso para consultar el reporte de errores.');

        $ajustes = $this->_ajustes_reporte();

        if (!$ajustes['activo']) {
            return $this->json(['ok' => FALSE, 'msg' => 'El visor de errores está desactivado en la configuración general.'], 403);
        }

        // draw se extrae de forma segura para poder responder el DataTables
        // vacío incluso si el resto de filtros aún no se valida.
        $draw_raw = $this->input->post('draw');
        $draw = is_array($draw_raw) ? 0 : (int)$draw_raw;

        if (!$this->Reportes_errores_model->logs_disponibles()) {
            return $this->json([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'aviso'           => 'El directorio de logs no está disponible o el formato de fecha no es compatible.',
            ]);
        }

        list($filtros, $errores) = $this->_validar_filtros($ajustes['dias'], $ajustes['max']);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $rows = $this->Reportes_errores_model->get_datatable(
            $filtros['start'],
            $filtros['length'],
            $filtros['desde'],
            $filtros['hasta'],
            $ajustes['max'],
            $filtros['search'],
            $filtros['nivel'],
            $filtros['order_col'],
            $filtros['order_dir']
        );

        $this->json([
            'draw'            => $filtros['draw'],
            'recordsTotal'    => $this->Reportes_errores_model->count_todos($filtros['desde'], $filtros['hasta'], $ajustes['max']),
            'recordsFiltered' => $this->Reportes_errores_model->count_filtrados($filtros['desde'], $filtros['hasta'], $ajustes['max'], $filtros['search'], $filtros['nivel']),
            'data'            => $this->_formatear_filas_datatable($rows),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Detalle
    // ─────────────────────────────────────────────────────────

    public function detalle()
    {
        $this->_no_cache();

        if (strtoupper((string)$this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        if (!$this->input->is_ajax_request()) {
            return $this->json(['ok' => FALSE, 'msg' => 'La solicitud debe ser AJAX.'], 400);
        }

        $menu_id = $this->Reportes_errores_model->menu_id();
        if ($menu_id <= 0) {
            return $this->json(['ok' => FALSE, 'msg' => 'El módulo no está registrado en el menú.'], 403);
        }

        $this->autorizar_accion($menu_id, 'consultar', 'No tienes permiso para consultar el reporte de errores.');

        $ajustes = $this->_ajustes_reporte();

        if (!$ajustes['activo']) {
            return $this->json(['ok' => FALSE, 'msg' => 'El visor de errores está desactivado en la configuración general.'], 403);
        }

        if (!$this->Reportes_errores_model->logs_disponibles()) {
            return $this->json(['ok' => FALSE, 'msg' => 'El directorio de logs no está disponible.'], 503);
        }

        $id_raw = $this->input->post('id');

        if (is_array($id_raw)) {
            return $this->json(['ok' => FALSE, 'errores' => ['id' => 'El identificador debe ser un valor escalar.']], 422);
        }

        $id = trim((string)$id_raw);

        if (!preg_match('/^(\d{4}-\d{2}-\d{2})\|(\d+)$/', $id, $m)
            || !$this->_fecha_estricta($m[1])) {
            return $this->json(['ok' => FALSE, 'errores' => ['id' => 'El identificador de la entrada no es válido.']], 422);
        }

        $detalle = $this->Reportes_errores_model->get_detalle($id, $ajustes['desde'], $ajustes['hasta']);

        if ($detalle === NULL) {
            return $this->json(['ok' => FALSE, 'msg' => 'La entrada no existe o está fuera de la ventana de consulta.'], 404);
        }

        $this->json(['ok' => TRUE, 'data' => $detalle]);
    }

    // ─────────────────────────────────────────────────────────
    // Configuración y validación
    // ─────────────────────────────────────────────────────────

    private function _ajustes_reporte()
    {
        $settings = $this->Configuracion_general_model->get_settings();

        $dias = $this->_entero_config($settings['logs_reporte_dias'] ?? '7', 1, 90, 7);
        $max  = $this->_entero_config($settings['logs_reporte_max_entradas'] ?? '500', 50, 5000, 500);

        $hasta = date('Y-m-d');
        $desde = date('Y-m-d', strtotime('-' . ($dias - 1) . ' days'));

        return [
            'activo' => (string)($settings['logs_reporte_activo'] ?? '1') === '1',
            'dias'   => $dias,
            'max'    => $max,
            'desde'  => $desde,
            'hasta'  => $hasta,
        ];
    }

    private function _entero_config($valor, $min, $max, $default)
    {
        $valor = trim((string)$valor);

        if (!preg_match('/^\d+$/', $valor)) {
            return $default;
        }

        $numero = (int)$valor;
        if ($numero < $min) {
            return $min;
        }
        if ($numero > $max) {
            return $max;
        }

        return $numero;
    }

    /**
     * Valida los parámetros DataTables.
     *
     * @return array [filtros, errores]
     */
    private function _validar_filtros($dias, $max_entradas)
    {
        $errores = [];
        $hoy     = date('Y-m-d');
        $desde_def = date('Y-m-d', strtotime('-' . max(0, $dias - 1) . ' days'));

        // draw
        $draw = $this->_post_escalar('draw');
        if ($draw === NULL) {
            $errores['draw'] = 'El parámetro draw debe ser un valor escalar.';
            $draw = '0';
        } elseif ($draw === '') {
            $draw = '0';
        } elseif (!preg_match('/^\d+$/', $draw)) {
            $errores['draw'] = 'El parámetro draw debe ser un entero no negativo.';
        }
        $draw = (int)$draw;

        // start: 0..max_entradas (más allá no hay ventana posible)
        $start = $this->_post_escalar('start');
        if ($start === NULL) {
            $errores['start'] = 'El parámetro start debe ser un valor escalar.';
            $start = '0';
        } elseif ($start === '') {
            $start = '0';
        } elseif (!preg_match('/^\d+$/', $start)) {
            $errores['start'] = 'El parámetro start debe ser un entero no negativo.';
            $start = '0';
        }
        $start = (int)$start;
        if ($start > $max_entradas) {
            $errores['start'] = 'El desplazamiento supera el máximo de entradas configurado.';
        }

        // length: solo 10, 25, 50, 100. -1/vacío => 25.
        $length = $this->_post_escalar('length');
        if ($length === NULL) {
            $errores['length'] = 'El parámetro length debe ser un valor escalar.';
            $length = '25';
        }
        if ($length === '' || $length === '-1') {
            $length = '25';
        }
        if (!preg_match('/^\d+$/', $length)) {
            $errores['length'] = 'El parámetro length debe ser un entero.';
            $length = '25';
        }
        $length = (int)$length;
        if (!in_array($length, self::LONGITUDES, TRUE)) {
            $errores['length'] = 'El tamaño de página debe ser 10, 25, 50 o 100.';
        }

        // search: DataTables envía search[value]; también se acepta escalar.
        $search_raw = $this->input->post('search');
        $search = '';
        if (is_array($search_raw)) {
            if (array_key_exists('value', $search_raw)) {
                if (is_array($search_raw['value']) || is_object($search_raw['value'])) {
                    $errores['search'] = 'El término de búsqueda debe ser un valor escalar.';
                } else {
                    $search = trim((string)$search_raw['value']);
                }
            }
        } elseif (is_object($search_raw)) {
            $errores['search'] = 'El término de búsqueda debe ser un valor escalar.';
        } elseif ($search_raw !== NULL) {
            $search = trim((string)$search_raw);
        }

        if ($this->_largo($search) > 200) {
            $errores['search'] = 'El término de búsqueda no puede superar los 200 caracteres.';
        }

        // order: order[0][column]/order[0][dir] o order_col/order_dir.
        list($order_col, $order_dir, $errores_orden) = $this->_resolver_orden();
        foreach ($errores_orden as $campo => $mensaje) {
            $errores[$campo] = $mensaje;
        }

        // desde / hasta con validación estricta Y-m-d.
        $desde = $this->_post_escalar('desde');
        if ($desde === NULL) {
            $errores['desde'] = 'La fecha desde debe ser un valor escalar.';
            $desde = $desde_def;
        } elseif ($desde === '') {
            $desde = $desde_def;
        } elseif (!$this->_fecha_estricta($desde)) {
            $errores['desde'] = 'La fecha desde debe tener el formato Y-m-d.';
        }

        $hasta = $this->_post_escalar('hasta');
        if ($hasta === NULL) {
            $errores['hasta'] = 'La fecha hasta debe ser un valor escalar.';
            $hasta = $hoy;
        } elseif ($hasta === '') {
            $hasta = $hoy;
        } elseif (!$this->_fecha_estricta($hasta)) {
            $errores['hasta'] = 'La fecha hasta debe tener el formato Y-m-d.';
        }

        if (!isset($errores['desde']) && !isset($errores['hasta'])) {
            if ($desde > $hasta) {
                // Inversión explícita del usuario: sí es un error de entrada.
                $errores['desde'] = 'La fecha desde no puede ser posterior a la fecha hasta.';
            } else {
                $hasta_original = $hasta;
                $minimo = $desde_def; // hoy - (dias - 1)

                // Límites absolutos de la ventana consultable: [minimo, hoy].
                if ($hasta > $hoy) {
                    $hasta = $hoy;
                }
                if ($desde < $minimo) {
                    $desde = $minimo;
                }

                // Rango totalmente fuera de la ventana: se usa el día límite
                // más cercano en lugar de responder 422.
                if ($desde > $hasta) {
                    if ($hasta_original < $minimo) {
                        $desde = $minimo;
                        $hasta = $minimo;
                    } else {
                        $desde = $hoy;
                        $hasta = $hoy;
                    }
                }

                // Recorte defensivo del rango máximo (los límites absolutos ya
                // garantizan que no supere logs_reporte_dias).
                $dt_desde = DateTime::createFromFormat('!Y-m-d', $desde);
                $dt_hasta = DateTime::createFromFormat('!Y-m-d', $hasta);
                if ($dt_desde !== FALSE && $dt_hasta !== FALSE
                    && ((int)$dt_desde->diff($dt_hasta)->days + 1) > $dias) {
                    $desde = date('Y-m-d', strtotime($hasta . ' -' . ($dias - 1) . ' days'));
                }
            }
        }

        // nivel
        $nivel = $this->_post_escalar('nivel');
        if ($nivel === NULL) {
            $errores['nivel'] = 'El nivel debe ser un valor escalar.';
            $nivel = '';
        } else {
            $nivel = strtoupper($nivel);
            if (!in_array($nivel, self::NIVELES, TRUE)) {
                $errores['nivel'] = 'El nivel indicado no es válido.';
            }
        }

        return [[
            'draw'      => $draw,
            'start'     => $start,
            'length'    => $length,
            'search'    => $search,
            'nivel'     => $nivel,
            'desde'     => $desde,
            'hasta'     => $hasta,
            'order_col' => $order_col,
            'order_dir' => $order_dir,
        ], $errores];
    }

    private function _resolver_orden()
    {
        $errores = [];
        $col = '0';
        $dir = 'desc';

        $order = $this->input->post('order');

        if (is_array($order)) {
            if (array_key_exists(0, $order)) {
                $primero = $order[0];

                if (!is_array($primero)) {
                    $errores['order'] = 'El parámetro order no tiene el formato esperado.';
                } else {
                    if (array_key_exists('column', $primero)) {
                        if (is_array($primero['column']) || is_object($primero['column'])) {
                            $errores['order_col'] = 'La columna de orden debe ser un valor escalar.';
                        } else {
                            $col = trim((string)$primero['column']);
                        }
                    }

                    if (array_key_exists('dir', $primero)) {
                        if (is_array($primero['dir']) || is_object($primero['dir'])) {
                            $errores['order_dir'] = 'La dirección de orden debe ser un valor escalar.';
                        } else {
                            $dir = strtolower(trim((string)$primero['dir']));
                        }
                    }
                }
            }
        } elseif ($order !== NULL) {
            $errores['order'] = 'El parámetro order debe ser un arreglo.';
        } else {
            $col_raw = $this->_post_escalar('order_col');
            if ($col_raw === NULL) {
                $errores['order_col'] = 'La columna de orden debe ser un valor escalar.';
            } elseif ($col_raw !== '') {
                $col = $col_raw;
            }

            $dir_raw = $this->_post_escalar('order_dir');
            if ($dir_raw === NULL) {
                $errores['order_dir'] = 'La dirección de orden debe ser un valor escalar.';
            } elseif ($dir_raw !== '') {
                $dir = strtolower($dir_raw);
            }
        }

        if (!preg_match('/^\d+$/', $col) || (int)$col < 0 || (int)$col > 4) {
            if (!isset($errores['order_col'])) {
                $errores['order_col'] = 'La columna de orden no es válida.';
            }
            $col = '0';
        }
        $col = (int)$col;

        if (!in_array($dir, ['asc', 'desc'], TRUE)) {
            if (!isset($errores['order_dir'])) {
                $errores['order_dir'] = 'La dirección de orden debe ser asc o desc.';
            }
            $dir = 'desc';
        }

        return [$col, $dir, $errores];
    }

    // ─────────────────────────────────────────────────────────
    // Formato de salida
    // ─────────────────────────────────────────────────────────

    private function _formatear_filas_datatable(array $rows)
    {
        $data = [];

        foreach ($rows as $item) {
            $nivel = strtoupper((string)($item['nivel'] ?? 'ERROR'));
            $clase = $this->_clase_nivel($nivel);

            $data[] = [
                'id'       => htmlspecialchars((string)($item['id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'fecha'    => htmlspecialchars((string)($item['fecha'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'nivel'    => '<span class="badge badge-light-' . $clase . ' fw-bold">'
                    . htmlspecialchars($nivel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '</span>',
                'mensaje'  => htmlspecialchars((string)($item['mensaje'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'archivo'  => htmlspecialchars((string)($item['archivo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'lineas'   => (int)($item['lineas'] ?? 0),
                'offset'   => (int)($item['offset'] ?? 0),
                'truncado' => !empty($item['truncado']),
            ];
        }

        return $data;
    }

    private function _clase_nivel($nivel)
    {
        switch (strtoupper((string)$nivel)) {
            case 'ERROR':
            case 'CRITICAL':
            case 'ALERT':
            case 'EMERGENCY':
                return 'danger';
            case 'WARNING':
            case 'NOTICE':
                return 'warning';
            case 'INFO':
                return 'info';
            case 'DEBUG':
                return 'secondary';
            default:
                return 'primary';
        }
    }

    // ─────────────────────────────────────────────────────────
    // Utilidades
    // ─────────────────────────────────────────────────────────

    /**
     * Devuelve el POST como string escalar, o NULL si llegó un arreglo/objeto.
     */
    private function _post_escalar($campo)
    {
        $valor = $this->input->post($campo);

        if (is_array($valor) || is_object($valor)) {
            return NULL;
        }

        if ($valor === NULL) {
            return '';
        }

        return trim((string)$valor);
    }

    private function _fecha_estricta($valor)
    {
        if (!is_string($valor) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return FALSE;
        }

        $dt = DateTime::createFromFormat('!Y-m-d', $valor);
        if ($dt === FALSE) {
            return FALSE;
        }

        $errores = DateTime::getLastErrors();
        if (is_array($errores) && (($errores['warning_count'] ?? 0) > 0 || ($errores['error_count'] ?? 0) > 0)) {
            return FALSE;
        }

        return $dt->format('Y-m-d') === $valor;
    }

    private function _largo($texto)
    {
        $texto = (string)$texto;

        if (function_exists('mb_strlen')) {
            try {
                return mb_strlen($texto, 'UTF-8');
            } catch (Throwable $e) {
                // Fallback para entradas con bytes inválidos.
            }
        }

        return strlen($texto);
    }

    /**
     * Evita que el navegador cachee respuestas con datos de logs.
     */
    private function _no_cache()
    {
        $this->output
            ->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_header('Expires: 0')
            ->set_header('X-Content-Type-Options: nosniff');
    }
}
