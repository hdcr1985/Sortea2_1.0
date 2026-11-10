<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MisRifas
 * ─────────────────────────────────────────────────────────────
 * Autoservicio del organizador para el módulo M03.
 *
 * La URL "mis-rifas" está registrada como libre en MY_Controller: cualquier
 * usuario autenticado puede entrar. El organizador SIEMPRE se resuelve desde
 * la sesión y nunca desde el request, por lo que todos los endpoints quedan
 * filtrados por rifa_org_id del perfil.
 */
class MisRifas extends MY_Controller
{
    private $estados = [
        'borrador'                => 'Borrador',
        'pendiente_aprobacion'    => 'Pendiente de aprobación',
        'activa'                  => 'Activa',
        'pausada'                 => 'Pausada',
        'cancelacion_solicitada'  => 'Cancelación solicitada',
        'finalizada'              => 'Finalizada',
        'cancelada'               => 'Cancelada',
        'rechazada'               => 'Rechazada',
    ];

    private $monedas = ['USD', 'EUR', 'VES', 'COP', 'MXN', 'USDT', 'GBP'];

    public function __construct()
    {
        parent::__construct();

        $this->load->model('Rifa_model');
        $this->load->model('Rifa_imagen_model');
        $this->load->model('Rifa_ticket_model');
        $this->load->model('Moneda_model');
        $this->load->model('Org_metodo_model');
        $this->load->model('Organizador_model');
        $this->load->model('Plan_asignacion_model');
        $this->load->model('Numeracion_model');
        $this->load->library('html_sanitizer');

        $this->page_title = 'Mis Rifas';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Mis Rifas', 'url' => ''],
        ];

        $this->page_styles = [
            'assets/plugins/custom/datatables/datatables.bundle.css',
            'assets/plugins/custom/ckeditor/ckeditor-classic.bundle.css',
        ];
        $this->page_vendor_scripts = [
            'assets/plugins/custom/datatables/datatables.bundle.js',
            'assets/plugins/custom/ckeditor/ckeditor-classic.bundle.js',
        ];
    }

    /**
     * GET /mis-rifas
     *
     * @return void
     */
    public function index()
    {
        $perfil = $this->Organizador_model->get_por_usuario((string)($this->usuario['usu_id'] ?? ''));

        if (empty($perfil)) {
            $this->session->set_flashdata('error', 'Primero crea tu vitrina.');
            redirect('mi-vitrina');
            return;
        }

        $plan_info = $this->Plan_asignacion_model->get_estado_por_org((int)$perfil['org_id']);

        $monedas = $this->Moneda_model->get_activas();
        if (empty($monedas)) {
            $monedas = [];
            foreach ($this->monedas as $codigo) {
                $monedas[] = [
                    'mon_codigo'  => $codigo,
                    'mon_nombre'  => $codigo,
                    'mon_simbolo' => $codigo === 'USD' ? '$' : $codigo,
                ];
            }
        }

        $this->render('pages/mis_rifas/index', [
            'url_base'     => $this->url_base,
            'perfil'       => $perfil,
            'plan_info'    => $plan_info,
            'numeraciones' => $this->Numeracion_model->get_activas(),
            'monedas'      => $monedas,
            'metodos_pago' => $this->Org_metodo_model->get_para_rifa((int)$perfil['org_id']),
            'estados'      => $this->estados,
            'max_imagenes' => 3,
        ]);
    }

    /**
     * POST /mis-rifas/tabla (AJAX DataTables server-side)
     *
     * @return void
     */
    public function tabla()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $perfil = $this->_perfil_org();
        $org_id = (int)$perfil['org_id'];

        $draw   = (int)$this->_post_escalar('draw', '0');
        $start  = max(0, (int)$this->_post_escalar('start', '0'));
        $length = (int)$this->_post_escalar('length', '10');
        $length = ($length <= 0) ? 10 : min(100, $length);

        list($search, $order_col, $order_dir) = $this->_datatable_input();

        if (!$this->Rifa_model->tabla_disponible()) {
            return $this->json([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'El módulo de rifas aún no está disponible.',
            ]);
        }

        $total_records    = $this->Rifa_model->count_todos($org_id);
        $filtered_records = $this->Rifa_model->count_filtrados($search, $org_id);
        $rows             = $this->Rifa_model->get_datatable($start, $length, $search, $order_col, $order_dir, $org_id);

        $this->json([
            'draw'            => $draw,
            'recordsTotal'    => $total_records,
            'recordsFiltered' => $filtered_records,
            'data'            => $this->_formatear_filas_datatable($rows),
        ]);
    }

    /**
     * GET /mis-rifas/datos/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function datos($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }

        $perfil = $this->_perfil_org();
        $rifa   = $this->Rifa_model->get_por_id((int)$id, (int)$perfil['org_id']);

        if (empty($rifa)) {
            return $this->json(['ok' => FALSE, 'msg' => 'La rifa no existe o fue eliminada.'], 404);
        }

        $this->json(['ok' => TRUE, 'data' => $this->_formatear_datos($rifa)]);
    }

    /**
     * POST /mis-rifas/crear (AJAX)
     *
     * @return void
     */
    public function crear()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $org_id = (int)$perfil['org_id'];

        $errores_escalares = $this->_validar_campos_escalares($this->_campos_formulario());
        if (!empty($errores_escalares)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores_escalares], 422);
        }

        $data    = $this->_recoger_post();
        $errores = $this->_validar($data, $org_id, 0, 'borrador');

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $metodos_ids    = $this->_recoger_metodos_post();
        $errores_metodos = $this->_validar_metodos_rifa($metodos_ids, $org_id);

        if (!empty($errores_metodos)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores_metodos], 422);
        }

        // Galería: hasta 3 imágenes nuevas. Se conserva además la vía legacy
        // del campo único rifa_imagen cuando la vista no envía el arreglo.
        $multi = $this->_subir_imagenes($org_id, 3);
        if (!$multi['ok']) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['rifa_imagenes' => (string)$multi['error']],
            ], 422);
        }

        $archivos = [];
        foreach ($multi['files'] as $file) {
            $archivos[] = (string)$file['filename'];
        }

        if (empty($archivos)) {
            $legacy = $this->_subir_imagen($org_id);
            if (!$legacy['ok']) {
                return $this->json([
                    'ok'      => FALSE,
                    'errores' => ['rifa_imagen' => (string)$legacy['error']],
                ], 422);
            }

            if (!empty($legacy['filename'])) {
                $archivos[] = (string)$legacy['filename'];
            }
        }

        $portada_index = 0;
        $portada_raw   = $this->input->post('rifa_portada_index', TRUE);

        if ($portada_raw !== NULL && $portada_raw !== '') {
            if (!is_scalar($portada_raw) || !preg_match('/^\d+$/', (string)$portada_raw)) {
                $this->_borrar_archivos($org_id, $archivos);

                return $this->json([
                    'ok'      => FALSE,
                    'errores' => ['rifa_portada_index' => 'La portada seleccionada no es válida.'],
                ], 422);
            }

            $portada_index = (int)$portada_raw;
        }

        if (!empty($archivos) && ($portada_index < 0 || $portada_index >= count($archivos))) {
            $this->_borrar_archivos($org_id, $archivos);

            return $this->json([
                'ok'      => FALSE,
                'errores' => ['rifa_portada_index' => 'Debes elegir una imagen de portada válida.'],
            ], 422);
        }

        $portada = !empty($archivos) ? $archivos[$portada_index] : NULL;

        $guardado = $this->_mapear_data_guardado($data);
        $guardado['rifa_org_id'] = $org_id;
        $guardado['rifa_imagen'] = $portada;

        $nuevo_id = $this->Rifa_model->crear($guardado);

        if (!$nuevo_id) {
            $this->_borrar_archivos($org_id, $archivos);

            if ($this->Rifa_model->existe_slug($guardado['rifa_slug'], $org_id)) {
                return $this->json([
                    'ok'      => FALSE,
                    'errores' => ['rifa_slug' => 'Ese identificador ya está en uso para tu vitrina.'],
                ], 422);
            }

            return $this->json(['ok' => FALSE, 'msg' => 'Error al guardar la rifa en la base de datos.'], 500);
        }

        $galeria_insertada = 0;

        if (!empty($archivos)) {
            if ($this->Rifa_imagen_model->tabla_disponible()) {
                $orden    = 0;
                $fallo_en = NULL;

                foreach ($archivos as $indice => $archivo) {
                    $img_id = $this->Rifa_imagen_model->crear(
                        (int)$nuevo_id,
                        $archivo,
                        ($indice === $portada_index ? 1 : 0),
                        $orden
                    );

                    if (!$img_id) {
                        $fallo_en = (int)$indice;
                        break;
                    }

                    $galeria_insertada++;
                    $orden++;
                }

                if ($fallo_en !== NULL) {
                    // Los archivos no registrados se descartan. La portada
                    // legacy se conserva porque rifa_imagen ya la referencia.
                    for ($i = $fallo_en; $i < count($archivos); $i++) {
                        if ($i !== $portada_index) {
                            $this->_borrar_imagen($org_id, $archivos[$i]);
                        }
                    }

                    log_message('error', 'MisRifas::crear no pudo completar la galería de imágenes (rifa ' . (int)$nuevo_id . ').');
                }
            } else {
                // Sin tabla de galería solo se conserva la portada legacy.
                foreach ($archivos as $indice => $archivo) {
                    if ($indice !== $portada_index) {
                        $this->_borrar_imagen($org_id, $archivo);
                    }
                }
            }
        }

        if (!empty($metodos_ids)) {
            $sync = $this->Org_metodo_model->sincronizar_rifa((int)$nuevo_id, $org_id, $metodos_ids);
            if (!$sync['ok']) {
                log_message('error', 'MisRifas::crear no pudo sincronizar métodos de pago (rifa ' . (int)$nuevo_id . ').');
            }
        }

        $this->bitacora(
            'RIFA_CREAR',
            "Rifa creada: {$guardado['rifa_titulo']} (ID: {$nuevo_id})"
            . " | Slug: {$guardado['rifa_slug']} | Tipo: {$guardado['rifa_tipo']}"
            . " | Imágenes: {$galeria_insertada}"
        );

        $this->json([
            'ok'  => TRUE,
            'msg' => 'Rifa creada correctamente.',
            'id'  => (int)$nuevo_id,
        ]);
    }

    /**
     * POST /mis-rifas/actualizar/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function actualizar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $org_id = (int)$perfil['org_id'];

        $rifa = $this->Rifa_model->get_por_id((int)$id, $org_id);
        if (empty($rifa)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Rifa no encontrada.'], 404);
        }

        $estado_actual = (string)$rifa['rifa_estado'];
        if (in_array($estado_actual, ['finalizada', 'cancelada'], TRUE)) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'La rifa no puede editarse porque ya fue cerrada.',
            ], 422);
        }

        $errores_escalares = $this->_validar_campos_escalares($this->_campos_formulario());
        if (!empty($errores_escalares)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores_escalares], 422);
        }

        $data = $this->_recoger_post();

        // Actualizaciones parciales: conservar modo/visibilidad si el cliente
        // no los envió, en lugar de resetearlos a sus valores por defecto.
        if ($this->input->post('rifa_modo_asignacion') === NULL
            && isset($rifa['rifa_modo_asignacion'])) {
            $data['rifa_modo_asignacion'] = (string)$rifa['rifa_modo_asignacion'];
        }

        if ($this->input->post('rifa_mostrar_reservados') === NULL
            && isset($rifa['rifa_mostrar_reservados'])) {
            $data['rifa_mostrar_reservados'] = (int)$rifa['rifa_mostrar_reservados'];
        }

        // Si la rifa ya tiene números ocupados, no se permite cambiar su
        // numeración/lote/padding: los tickets quedarían huérfanos.
        if (in_array($estado_actual, ['borrador', 'rechazada'], TRUE)
            && $this->Rifa_ticket_model->contar_por_rifa((int)$id) > 0) {
            $errores_numeracion = $this->_errores_numeracion_con_tickets($data, $rifa);

            if (!empty($errores_numeracion)) {
                return $this->json([
                    'ok'      => FALSE,
                    'msg'     => 'No puedes cambiar la numeración, el lote o el padding mientras la rifa tenga números ocupados. Libera los números primero.',
                    'errores' => $errores_numeracion,
                ], 422);
            }
        }

        // Validar el intento estructural del POST crudo ANTES de normalizar
        // las rifas gratuitas pendientes. Así un cambio de precio, lote o
        // numeración no queda oculto por la normalización.
        if (!in_array($estado_actual, ['borrador', 'rechazada'], TRUE)) {
            $errores_estructura_raw = $this->_intentos_estructurales_raw($rifa);
            if (!empty($errores_estructura_raw)) {
                return $this->json(['ok' => FALSE, 'errores' => $errores_estructura_raw], 422);
            }
        }

        // Rifa gratuita aún sin numeración (pendiente de aprobación): los
        // campos de numeración no aplican y no deben disparar el bloqueo
        // estructural al editar título/descripción/fecha.
        if ((string)$rifa['rifa_tipo'] === 'gratuita'
            && empty($rifa['rifa_num_id'])
            && (int)$rifa['rifa_total_numeros'] === 0) {
            $data['rifa_asig_id']         = 0;
            $data['rifa_num_id']          = 0;
            $data['rifa_numeracion_tipo'] = (string)($rifa['rifa_numeracion_tipo'] ?? 'custom');
            $data['rifa_numero_desde']    = (int)($rifa['rifa_numero_desde'] ?? 0);
            $data['rifa_numero_hasta']    = (int)($rifa['rifa_numero_hasta'] ?? 0);
            $data['rifa_padding']         = (int)($rifa['rifa_padding'] ?? 0);
            $data['rifa_total_numeros']   = 0;
            $data['rifa_max_por_compra']  = (int)($rifa['rifa_max_por_compra'] ?? 0);
        }

        // En estados distintos de borrador/rechazada solo se permite editar
        // título, descripción, condiciones, imagen y fecha de sorteo. Si el
        // POST intenta cambiar un campo estructural, se rechaza con 422.
        if (!in_array($estado_actual, ['borrador', 'rechazada'], TRUE)) {
            $errores_estructura = $this->_campos_estructurales_cambiados($data, $rifa);
            if (!empty($errores_estructura)) {
                return $this->json(['ok' => FALSE, 'errores' => $errores_estructura], 422);
            }

            $bloqueados = [
                'rifa_slug', 'rifa_tipo', 'rifa_precio', 'rifa_moneda',
                'rifa_asig_id', 'rifa_num_id',
                'rifa_numeracion_tipo', 'rifa_numero_desde', 'rifa_numero_hasta',
                'rifa_padding', 'rifa_total_numeros', 'rifa_max_por_compra',
                'rifa_reserva_ttl_min', 'rifa_reserva_auto',
            ];

            foreach ($bloqueados as $campo) {
                if (array_key_exists($campo, $rifa)) {
                    $data[$campo] = $rifa[$campo];
                }
            }
        }

        $errores = $this->_validar($data, $org_id, (int)$id, $estado_actual);
        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $metodos_enviados = $this->_metodos_post_enviados();
        $metodos_ids      = $this->_recoger_metodos_post();

        if ($metodos_enviados) {
            $errores_metodos = $this->_validar_metodos_rifa($metodos_ids, $org_id);

            if (empty($errores_metodos)
                && (string)$rifa['rifa_tipo'] === 'paga'
                && in_array($estado_actual, ['activa', 'pausada'], TRUE)
                && empty($metodos_ids)) {
                $errores_metodos['rifa_metodos'] = 'Una rifa paga activa o pausada debe conservar al menos un método de pago.';
            }

            if (!empty($errores_metodos)) {
                return $this->json(['ok' => FALSE, 'errores' => $errores_metodos], 422);
            }
        }

        // Revalidar contra la numeración ya normalizada (p. ej. la derivada
        // del lote) antes de persistir, para no dejar tickets huérfanos.
        if (in_array($estado_actual, ['borrador', 'rechazada'], TRUE)
            && $this->Rifa_ticket_model->contar_por_rifa((int)$id) > 0) {
            $errores_numeracion_final = $this->_errores_numeracion_con_tickets($data, $rifa, FALSE);

            if (!empty($errores_numeracion_final)) {
                return $this->json([
                    'ok'      => FALSE,
                    'msg'     => 'No puedes cambiar la numeración, el lote o el padding mientras la rifa tenga números ocupados. Libera los números primero.',
                    'errores' => $errores_numeracion_final,
                ], 422);
            }
        }

        $galeria_disponible = $this->Rifa_imagen_model->tabla_disponible();
        $total_galeria      = $galeria_disponible
            ? (int)$this->Rifa_imagen_model->contar_por_rifa((int)$id)
            : 0;
        $cupo               = max(0, 3 - $total_galeria);
        $tenia_principal    = $galeria_disponible
            && !empty($this->Rifa_imagen_model->get_principal((int)$id));

        $multi = $this->_subir_imagenes($org_id, $cupo);
        if (!$multi['ok']) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['rifa_imagenes' => (string)$multi['error']],
            ], 422);
        }

        $nuevos = [];
        foreach ($multi['files'] as $file) {
            $nuevos[] = (string)$file['filename'];
        }

        $imagen = [
            'ok'       => TRUE,
            'filename' => $rifa['rifa_imagen'] ?? NULL,
            'error'    => '',
        ];

        if (empty($nuevos)) {
            $imagen = $this->_subir_imagen($org_id, $rifa['rifa_imagen'] ?? NULL);
            if (!$imagen['ok']) {
                return $this->json([
                    'ok'      => FALSE,
                    'errores' => ['rifa_imagen' => (string)$imagen['error']],
                ], 422);
            }
        }

        $reemplaza_legacy = !empty($imagen['filename'])
            && $imagen['filename'] !== ($rifa['rifa_imagen'] ?? NULL);

        $portada_id = (int)$this->_post_escalar('rifa_portada_id', '0');

        if ($portada_id > 0) {
            $imagen_portada = $this->Rifa_imagen_model->get_por_id($portada_id);

            if (empty($imagen_portada) || (int)$imagen_portada['img_rifa_id'] !== (int)$id) {
                $this->_borrar_archivos($org_id, $nuevos);
                if ($reemplaza_legacy) {
                    $this->_borrar_imagen($org_id, $imagen['filename']);
                }

                return $this->json([
                    'ok'      => FALSE,
                    'errores' => ['rifa_portada_id' => 'La imagen de portada no pertenece a esta rifa.'],
                ], 422);
            }
        }

        $portada_index = NULL;
        $portada_raw   = $this->input->post('rifa_portada_index', TRUE);

        if ($portada_raw !== NULL && $portada_raw !== '') {
            if (!is_scalar($portada_raw) || !preg_match('/^\d+$/', (string)$portada_raw)) {
                $this->_borrar_archivos($org_id, $nuevos);
                if ($reemplaza_legacy) {
                    $this->_borrar_imagen($org_id, $imagen['filename']);
                }

                return $this->json([
                    'ok'      => FALSE,
                    'errores' => ['rifa_portada_index' => 'La portada seleccionada no es válida.'],
                ], 422);
            }

            $portada_index = (int)$portada_raw;

            if ($portada_index < 0 || $portada_index >= count($nuevos)) {
                $this->_borrar_archivos($org_id, $nuevos);
                if ($reemplaza_legacy) {
                    $this->_borrar_imagen($org_id, $imagen['filename']);
                }

                return $this->json([
                    'ok'      => FALSE,
                    'errores' => ['rifa_portada_index' => 'Debes elegir una imagen nueva válida como portada.'],
                ], 422);
            }
        }

        $guardado = $this->_mapear_data_guardado($data);

        // Cuando la galería está disponible, la portada legacy se sincroniza
        // después mediante Rifa_imagen_model::reemplazar_principal(). Así, si
        // esa operación falla, rifa_imagen conserva el archivo anterior y no
        // queda apuntando a un archivo sin fila en la galería.
        $guardado['rifa_imagen'] = ($reemplaza_legacy && $galeria_disponible)
            ? ($rifa['rifa_imagen'] ?? NULL)
            : $imagen['filename'];

        if (!$this->Rifa_model->actualizar((int)$id, $guardado, $org_id)) {
            $this->_borrar_archivos($org_id, $nuevos);
            if ($reemplaza_legacy) {
                $this->_borrar_imagen($org_id, $imagen['filename']);
            }

            if ($this->Rifa_model->existe_slug($guardado['rifa_slug'], $org_id, (int)$id)) {
                return $this->json([
                    'ok'      => FALSE,
                    'errores' => ['rifa_slug' => 'Ese identificador ya está en uso para tu vitrina.'],
                ], 422);
            }

            return $this->json(['ok' => FALSE, 'msg' => 'No se pudieron guardar los cambios.'], 500);
        }

        $imagen_anterior = $rifa['rifa_imagen'] ?? NULL;
        $imagen_final    = $imagen['filename'];
        $ids_nuevos      = [];

        if (!empty($nuevos)) {
            if (!$galeria_disponible) {
                $this->_borrar_archivos($org_id, $nuevos);

                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'La galería de imágenes no está disponible en este momento.',
                ], 500);
            }

            $orden = (int)$this->Rifa_imagen_model->siguiente_orden((int)$id);

            foreach ($nuevos as $indice => $archivo) {
                $img_id = $this->Rifa_imagen_model->crear((int)$id, $archivo, 0, $orden);

                if (!$img_id) {
                    $this->_borrar_imagen($org_id, $archivo);
                    $this->_borrar_archivos($org_id, array_slice($nuevos, $indice + 1));

                    log_message('error', 'MisRifas::actualizar no pudo registrar una imagen de la galería (rifa ' . (int)$id . ').');

                    return $this->json([
                        'ok'  => FALSE,
                        'msg' => 'No se pudo completar la galería de imágenes.',
                    ], 500);
                }

                $ids_nuevos[$indice] = (int)$img_id;
                $orden++;
            }
        }

        $legacy_reemplazado = TRUE;

        if ($reemplaza_legacy && $galeria_disponible) {
            $legacy_reemplazado = $this->Rifa_imagen_model->reemplazar_principal(
                (int)$id,
                (string)$imagen['filename']
            );

            if (!$legacy_reemplazado) {
                $this->_borrar_imagen($org_id, $imagen['filename']);
                log_message('error', 'MisRifas::actualizar no pudo sincronizar la portada legacy (rifa ' . (int)$id . ').');

                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'No se pudo actualizar la imagen principal de la rifa.',
                ], 500);
            }
        }

        if ($galeria_disponible) {
            if ($portada_id > 0) {
                if (!$this->Rifa_imagen_model->marcar_principal((int)$id, $portada_id)) {
                    return $this->json([
                        'ok'  => FALSE,
                        'msg' => 'No se pudo marcar la imagen de portada.',
                    ], 500);
                }
            } elseif (!empty($nuevos) && $portada_index !== NULL) {
                $elegido = (int)($ids_nuevos[$portada_index] ?? 0);

                if ($elegido <= 0
                    || !$this->Rifa_imagen_model->marcar_principal((int)$id, $elegido)) {
                    return $this->json([
                        'ok'  => FALSE,
                        'msg' => 'No se pudo marcar la imagen de portada.',
                    ], 500);
                }
            } elseif (!$tenia_principal && !empty($ids_nuevos)) {
                $elegido = (int)reset($ids_nuevos);

                if ($elegido <= 0
                    || !$this->Rifa_imagen_model->marcar_principal((int)$id, $elegido)) {
                    return $this->json([
                        'ok'  => FALSE,
                        'msg' => 'No se pudo marcar la imagen de portada.',
                    ], 500);
                }
            }

            $principal = $this->Rifa_imagen_model->get_principal((int)$id);
            if (!empty($principal)) {
                $imagen_final = (string)$principal['img_archivo'];
            }
        }

        // La imagen anterior se elimina SOLO después del UPDATE exitoso y de
        // que la galería dejó de referenciarla.
        if ($reemplaza_legacy
            && $legacy_reemplazado
            && !empty($imagen_anterior)
            && $imagen_anterior !== $imagen_final) {
            $this->_borrar_imagen($org_id, $imagen_anterior);
        }

        if ($metodos_enviados) {
            $sync = $this->Org_metodo_model->sincronizar_rifa((int)$id, $org_id, $metodos_ids);
            if (!$sync['ok']) {
                log_message('error', 'MisRifas::actualizar no pudo sincronizar métodos de pago (rifa ' . (int)$id . ').');
            }
        }

        $this->bitacora(
            'RIFA_ACTUALIZAR',
            "Rifa actualizada: {$guardado['rifa_titulo']} (ID: {$id})"
            . " | Slug: {$guardado['rifa_slug']} | Estado: {$estado_actual}"
        );

        $this->json([
            'ok'  => TRUE,
            'msg' => 'Rifa actualizada correctamente.',
        ]);
    }

    /**
     * POST /mis-rifas/eliminar/:id (AJAX, borrado lógico)
     *
     * @param  int $id
     * @return void
     */
    public function eliminar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $org_id = (int)$perfil['org_id'];

        $rifa = $this->Rifa_model->get_por_id((int)$id, $org_id);
        if (empty($rifa)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Rifa no encontrada.'], 404);
        }

        if (!in_array((string)$rifa['rifa_estado'], ['borrador', 'rechazada', 'cancelada', 'finalizada'], TRUE)) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'La rifa no puede eliminarse en su estado actual.',
            ], 422);
        }

        if (!$this->Rifa_model->eliminar((int)$id, $org_id)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo eliminar la rifa.'], 500);
        }

        if (!empty($rifa['rifa_imagen'])) {
            $this->_borrar_imagen($org_id, $rifa['rifa_imagen']);
        }

        $this->bitacora(
            'RIFA_ELIMINAR',
            "Rifa eliminada lógicamente: {$rifa['rifa_titulo']} (ID: {$id})"
            . " | Slug: {$rifa['rifa_slug']} | Estado previo: {$rifa['rifa_estado']}"
        );

        $this->json([
            'ok'  => TRUE,
            'msg' => 'Rifa eliminada correctamente.',
        ]);
    }

    /**
     * POST /mis-rifas/publicar/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function publicar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $org_id = (int)$perfil['org_id'];

        $rifa = $this->Rifa_model->get_por_id((int)$id, $org_id);
        if (empty($rifa)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Rifa no encontrada.'], 404);
        }

        if ((string)$rifa['rifa_tipo'] === 'paga'
            && $this->Org_metodo_model->contar_rifa_activos_completos((int)$id) <= 0) {
            return $this->json([
                'ok'     => FALSE,
                'msg'    => 'Selecciona al menos un método de pago activo y completo antes de publicar la rifa.',
                'motivo' => 'sin_metodos_pago',
            ], 422);
        }

        $errores_escalares = $this->_validar_campos_escalares(['motivo']);
        if (!empty($errores_escalares)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores_escalares], 422);
        }

        $motivo = trim($this->_post_escalar('motivo'));
        if ($this->_longitud($motivo) > 255) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['motivo' => 'El motivo no puede exceder los 255 caracteres.'],
            ], 422);
        }

        $res = $this->Rifa_model->publicar((int)$id, $org_id, $motivo);

        if (!$res['ok']) {
            return $this->_respuesta_transicion($res, 'publicar');
        }

        $this->bitacora(
            'RIFA_PUBLICAR',
            "Rifa publicada: {$rifa['rifa_titulo']} (ID: {$id})"
            . " | Tipo: {$rifa['rifa_tipo']} | Estado: {$res['estado']}"
            . ($motivo !== '' ? " | Motivo: {$motivo}" : '')
        );

        if (!empty($res['asig_id'])) {
            $this->bitacora(
                'PLAN_CONSUMO',
                "Consumo de cupo por publicación | Rifa: {$id}"
                . " | asig_id: " . (int)$res['asig_id']
                . " | cons_id: " . (int)($res['cons_id'] ?? 0)
                . " | num_id: " . (int)($res['num_id'] ?? 0)
            );
        }

        $this->json([
            'ok'     => TRUE,
            'msg'    => $res['estado'] === 'pendiente_aprobacion'
                ? 'Rifa enviada a revisión. Quedará activa cuando sea aprobada.'
                : 'Rifa publicada correctamente.',
            'estado' => $res['estado'],
        ]);
    }

    /**
     * POST /mis-rifas/pausar/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function pausar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $org_id = (int)$perfil['org_id'];

        $rifa = $this->Rifa_model->get_por_id((int)$id, $org_id);
        if (empty($rifa)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Rifa no encontrada.'], 404);
        }

        $res = $this->Rifa_model->pausar((int)$id, $org_id);
        if (!$res['ok']) {
            return $this->_respuesta_transicion($res, 'pausar');
        }

        $this->bitacora('RIFA_PAUSAR', "Rifa pausada: {$rifa['rifa_titulo']} (ID: {$id})");

        $this->json(['ok' => TRUE, 'msg' => 'Rifa pausada correctamente.', 'estado' => $res['estado']]);
    }

    /**
     * POST /mis-rifas/reactivar/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function reactivar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $org_id = (int)$perfil['org_id'];

        $rifa = $this->Rifa_model->get_por_id((int)$id, $org_id);
        if (empty($rifa)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Rifa no encontrada.'], 404);
        }

        if ((string)$rifa['rifa_tipo'] === 'paga'
            && $this->Org_metodo_model->contar_rifa_activos_completos((int)$id) <= 0) {
            return $this->json([
                'ok'     => FALSE,
                'msg'    => 'Selecciona al menos un método de pago activo y completo antes de reactivar la rifa.',
                'motivo' => 'sin_metodos_pago',
            ], 422);
        }

        $res = $this->Rifa_model->reactivar((int)$id, $org_id);
        if (!$res['ok']) {
            return $this->_respuesta_transicion($res, 'reactivar');
        }

        $this->bitacora('RIFA_REACTIVAR', "Rifa reactivada: {$rifa['rifa_titulo']} (ID: {$id})");

        $this->json(['ok' => TRUE, 'msg' => 'Rifa reactivada correctamente.', 'estado' => $res['estado']]);
    }

    /**
     * POST /mis-rifas/solicitar-cancelacion/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function solicitar_cancelacion($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $org_id = (int)$perfil['org_id'];

        $rifa = $this->Rifa_model->get_por_id((int)$id, $org_id);
        if (empty($rifa)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Rifa no encontrada.'], 404);
        }

        $errores_escalares = $this->_validar_campos_escalares(['motivo']);
        if (!empty($errores_escalares)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores_escalares], 422);
        }

        $motivo = trim($this->_post_escalar('motivo'));

        if ($motivo === '') {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['motivo' => 'Debes indicar el motivo de la cancelación.'],
            ], 422);
        }

        if ($this->_longitud($motivo) > 255) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['motivo' => 'El motivo no puede exceder los 255 caracteres.'],
            ], 422);
        }

        $res = $this->Rifa_model->solicitar_cancelacion((int)$id, $org_id, $motivo);
        if (!$res['ok']) {
            return $this->_respuesta_transicion($res, 'solicitar_cancelacion');
        }

        $this->bitacora(
            'RIFA_CANCELACION_SOLICITAR',
            "Solicitud de cancelación: {$rifa['rifa_titulo']} (ID: {$id}) | Motivo: {$motivo}"
        );

        $this->json([
            'ok'     => TRUE,
            'msg'    => 'Solicitud de cancelación enviada. Quedará a la espera de aprobación.',
            'estado' => $res['estado'],
        ]);
    }

    /**
     * POST /mis-rifas/finalizar/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function finalizar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $org_id = (int)$perfil['org_id'];

        $rifa = $this->Rifa_model->get_por_id((int)$id, $org_id);
        if (empty($rifa)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Rifa no encontrada.'], 404);
        }

        $res = $this->Rifa_model->finalizar((int)$id, $org_id);
        if (!$res['ok']) {
            return $this->_respuesta_transicion($res, 'finalizar');
        }

        $this->bitacora('RIFA_FINALIZAR', "Rifa finalizada: {$rifa['rifa_titulo']} (ID: {$id})");

        if (!empty($res['asig_id'])) {
            $this->bitacora(
                'PLAN_DEVOLUCION',
                "Devolución de cupo al finalizar | Rifa: {$id}"
                . " | asig_id: " . (int)$res['asig_id']
                . " | cons_id: " . (int)($res['cons_id'] ?? 0)
            );
        }

        $this->json(['ok' => TRUE, 'msg' => 'Rifa finalizada correctamente.', 'estado' => $res['estado']]);
    }

    /**
     * GET /mis-rifas/imagenes/:id (AJAX)
     * Lista la galería de una rifa del organizador en sesión.
     *
     * @param  int $id
     * @return void
     */
    public function imagenes($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'GET') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $rifa   = $this->Rifa_model->get_por_id((int)$id, (int)$perfil['org_id']);

        if (empty($rifa)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Rifa no encontrada.'], 404);
        }

        $this->json([
            'ok'   => TRUE,
            'data' => $this->_listar_imagenes((int)$id),
        ]);
    }

    /**
     * POST /mis-rifas/imagen-subir/:id (AJAX multipart)
     *
     * @param  int $id
     * @return void
     */
    public function imagen_subir($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $org_id = (int)$perfil['org_id'];
        $rifa   = $this->Rifa_model->get_por_id((int)$id, $org_id);

        if (empty($rifa)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Rifa no encontrada.'], 404);
        }

        if (!$this->_estado_galeria_editable((string)$rifa['rifa_estado'])) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'La galería no puede modificarse en el estado actual de la rifa.',
            ], 422);
        }

        if (!$this->Rifa_imagen_model->tabla_disponible()) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'La galería de imágenes no está disponible.',
            ], 500);
        }

        $total = $this->Rifa_imagen_model->contar_por_rifa((int)$id);
        $cupo  = max(0, 3 - $total);

        if ($cupo <= 0) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'Ya alcanzaste el máximo de 3 imágenes para esta rifa.',
            ], 422);
        }

        $multi = $this->_subir_imagenes($org_id, $cupo);
        if (!$multi['ok']) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['rifa_imagenes' => (string)$multi['error']],
            ], 422);
        }

        if (empty($multi['files'])) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['rifa_imagenes' => 'Selecciona al menos una imagen.'],
            ], 422);
        }

        $principal_actual = $this->Rifa_imagen_model->get_principal((int)$id);
        $orden            = (int)$this->Rifa_imagen_model->siguiente_orden((int)$id);
        $ids              = [];
        $archivos         = [];

        foreach ($multi['files'] as $file) {
            $archivos[] = (string)$file['filename'];
        }

        foreach ($archivos as $indice => $archivo) {
            $img_id = $this->Rifa_imagen_model->crear((int)$id, $archivo, 0, $orden);

            if (!$img_id) {
                $this->_borrar_imagen($org_id, $archivo);
                $this->_borrar_archivos($org_id, array_slice($archivos, $indice + 1));

                $limite = $this->Rifa_imagen_model->contar_por_rifa((int)$id) >= 3;

                return $this->json([
                    'ok'  => FALSE,
                    'msg' => $limite
                        ? 'Ya alcanzaste el máximo de 3 imágenes para esta rifa.'
                        : 'No se pudieron guardar las imágenes en la galería.',
                ], $limite ? 422 : 500);
            }

            $ids[] = (int)$img_id;
            $orden++;
        }

        if (empty($principal_actual) && !empty($ids)) {
            $this->Rifa_imagen_model->marcar_principal((int)$id, (int)$ids[0]);
        }

        $this->bitacora(
            'RIFA_IMAGEN_SUBIR',
            "Imágenes subidas a la rifa {$id}: " . count($ids)
            . ' | IDs: ' . implode(',', $ids)
        );

        $this->json([
            'ok'   => TRUE,
            'msg'  => 'Imágenes subidas correctamente.',
            'data' => $this->_listar_imagenes((int)$id),
        ]);
    }

    /**
     * POST /mis-rifas/imagen-portada/:id/:img_id (AJAX)
     *
     * @param  int $id
     * @param  int $img_id
     * @return void
     */
    public function imagen_portada($id = 0, $img_id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $rifa   = $this->Rifa_model->get_por_id((int)$id, (int)$perfil['org_id']);

        if (empty($rifa)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Rifa no encontrada.'], 404);
        }

        if (!$this->_estado_galeria_editable((string)$rifa['rifa_estado'])) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'La portada no puede modificarse en el estado actual de la rifa.',
            ], 422);
        }

        $imagen = $this->Rifa_imagen_model->get_por_id((int)$img_id);

        if (empty($imagen) || (int)$imagen['img_rifa_id'] !== (int)$id) {
            return $this->json(['ok' => FALSE, 'msg' => 'Imagen no encontrada.'], 404);
        }

        if (!$this->Rifa_imagen_model->marcar_principal((int)$id, (int)$img_id)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo marcar la portada.'], 500);
        }

        $this->bitacora(
            'RIFA_IMAGEN_PORTADA',
            "Portada actualizada | Rifa: {$id} | Imagen: " . (int)$img_id
        );

        $this->json([
            'ok'   => TRUE,
            'msg'  => 'Portada actualizada correctamente.',
            'data' => $this->_listar_imagenes((int)$id),
        ]);
    }

    /**
     * POST /mis-rifas/imagen-eliminar/:id/:img_id (AJAX)
     *
     * @param  int $id
     * @param  int $img_id
     * @return void
     */
    public function imagen_eliminar($id = 0, $img_id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $org_id = (int)$perfil['org_id'];
        $rifa   = $this->Rifa_model->get_por_id((int)$id, $org_id);

        if (empty($rifa)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Rifa no encontrada.'], 404);
        }

        if (!$this->_estado_galeria_editable((string)$rifa['rifa_estado'])) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'La galería no puede modificarse en el estado actual de la rifa.',
            ], 422);
        }

        $imagen = $this->Rifa_imagen_model->get_por_id((int)$img_id);

        if (empty($imagen) || (int)$imagen['img_rifa_id'] !== (int)$id) {
            return $this->json(['ok' => FALSE, 'msg' => 'Imagen no encontrada.'], 404);
        }

        $archivo = (string)$imagen['img_archivo'];

        if (!$this->Rifa_imagen_model->eliminar((int)$img_id)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo eliminar la imagen.'], 500);
        }

        $this->_borrar_imagen($org_id, $archivo);

        $this->bitacora(
            'RIFA_IMAGEN_ELIMINAR',
            "Imagen eliminada | Rifa: {$id} | Imagen: " . (int)$img_id
        );

        $this->json([
            'ok'   => TRUE,
            'msg'  => 'Imagen eliminada correctamente.',
            'data' => $this->_listar_imagenes((int)$id),
        ]);
    }

    /**
     * GET /mis-rifas/tickets/:id (AJAX)
     * Resumen de ocupación y rangos bloqueados de una rifa propia.
     *
     * @param  int $id
     * @return void
     */
    public function tickets($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'GET') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $rifa   = $this->Rifa_model->get_por_id((int)$id, (int)$perfil['org_id']);

        if (empty($rifa)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Rifa no encontrada.'], 404);
        }

        if (!$this->Rifa_ticket_model->tabla_disponible()) {
            return $this->json(['ok' => FALSE, 'msg' => 'El inventario de números no está disponible.'], 500);
        }

        $this->json([
            'ok'   => TRUE,
            'data' => [
                'rifa' => [
                    'id'                 => (int)$rifa['rifa_id'],
                    'titulo'             => (string)$rifa['rifa_titulo'],
                    'estado'             => (string)$rifa['rifa_estado'],
                    'total'              => (int)$rifa['rifa_total_numeros'],
                    'desde'              => (int)$rifa['rifa_numero_desde'],
                    'hasta'              => (int)$rifa['rifa_numero_hasta'],
                    'padding'            => (int)$rifa['rifa_padding'],
                    'modo'               => (string)($rifa['rifa_modo_asignacion'] ?? 'manual'),
                    'mostrar_reservados' => (int)($rifa['rifa_mostrar_reservados'] ?? 1),
                ],
                'resumen'    => $this->Rifa_ticket_model->get_resumen((int)$id),
                'bloqueados' => $this->Rifa_ticket_model->get_bloqueados_agrupados((int)$id),
            ],
        ]);
    }

    /**
     * GET /mis-rifas/tickets-mapa/:id (AJAX)
     * Mapa paginado de números con su estado.
     *
     * @param  int $id
     * @return void
     */
    public function tickets_mapa($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'GET') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $rifa   = $this->Rifa_model->get_por_id((int)$id, (int)$perfil['org_id']);

        if (empty($rifa)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Rifa no encontrada.'], 404);
        }

        $offset = $this->input->get('offset', TRUE);
        $limit  = $this->input->get('limit', TRUE);

        $offset = is_scalar($offset) ? max(0, (int)$offset) : 0;
        $limit  = is_scalar($limit) ? (int)$limit : 200;

        $this->json([
            'ok'   => TRUE,
            'data' => $this->Rifa_ticket_model->get_mapa((int)$id, $offset, $limit),
        ]);
    }

    /**
     * POST /mis-rifas/bloquear-numeros/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function bloquear_numeros($id = 0)
    {
        $this->_endpoint_numeros($id, 'bloquear');
    }

    /**
     * POST /mis-rifas/liberar-numeros/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function liberar_numeros($id = 0)
    {
        $this->_endpoint_numeros($id, 'liberar');
    }

    /**
     * POST /mis-rifas/venta-presencial/:id (AJAX)
     *
     * @param  int $id
     * @return void
     */
    public function venta_presencial($id = 0)
    {
        $this->_endpoint_numeros($id, 'venta');
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────

    /**
     * Resuelve el organizador del usuario en sesión o corta la petición.
     *
     * @return array
     */
    private function _perfil_org()
    {
        $usuario_id = (string)($this->usuario['usu_id'] ?? '');
        $perfil     = $this->Organizador_model->get_por_usuario($usuario_id);

        if (!empty($perfil) && (int)($perfil['org_id'] ?? 0) > 0) {
            return $perfil;
        }

        if ($this->input->is_ajax_request()) {
            $this->json(['ok' => FALSE, 'msg' => 'No tienes una vitrina registrada.'], 404);
            $this->output->_display();
            exit;
        }

        $this->session->set_flashdata('error', 'Primero crea tu vitrina.');
        redirect('mi-vitrina');
        exit;
    }

    /**
     * Endpoint común de operaciones sobre números.
     *
     * @param  int    $id
     * @param  string $accion bloquear|liberar|venta
     * @return void
     */
    private function _endpoint_numeros($id, $accion)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $org_id = (int)$perfil['org_id'];
        $rifa   = $this->Rifa_model->get_por_id((int)$id, $org_id);

        if (empty($rifa)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Rifa no encontrada.'], 404);
        }

        if (!$this->Rifa_ticket_model->tabla_disponible()) {
            return $this->json(['ok' => FALSE, 'msg' => 'El inventario de números no está disponible.'], 500);
        }

        $errores = $this->_validar_campos_escalares(['numeros', 'motivo', 'comprador']);
        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $numeros   = trim($this->_post_escalar('numeros'));
        $motivo    = trim($this->_post_escalar('motivo'));
        $comprador = trim($this->_post_escalar('comprador'));

        if (strlen($numeros) > 10000) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['numeros' => 'La lista de números es demasiado larga.'],
            ], 422);
        }

        if ($this->_longitud($motivo) > 255) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['motivo' => 'El motivo no puede exceder los 255 caracteres.'],
            ], 422);
        }

        if ($this->_longitud($comprador) > 150) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['comprador' => 'El nombre del comprador no puede exceder los 150 caracteres.'],
            ], 422);
        }

        $actor = (string)($this->usuario['usu_usuario'] ?? '');

        if ($accion === 'bloquear') {
            $res = $this->Rifa_ticket_model->bloquear((int)$id, $org_id, $numeros, $motivo, $actor);
        } elseif ($accion === 'liberar') {
            $res = $this->Rifa_ticket_model->liberar((int)$id, $org_id, $numeros, $motivo, $actor);
        } else {
            $res = $this->Rifa_ticket_model->venta_presencial(
                (int)$id,
                $org_id,
                $numeros,
                $comprador,
                $motivo,
                $actor
            );
        }

        if (empty($res['ok'])) {
            return $this->_respuesta_tickets($res, $accion);
        }

        $cantidad = (int)($res['cantidad'] ?? 0);

        $acciones_bitacora = [
            'bloquear' => 'RIFA_NUMEROS_BLOQUEAR',
            'liberar'  => 'RIFA_NUMEROS_LIBERAR',
            'venta'    => 'RIFA_VENTA_PRESENCIAL',
        ];

        $numeros_log   = $this->_limpiar_log($numeros);
        $motivo_log    = $this->_limpiar_log($motivo);
        $comprador_log = $this->_limpiar_log($comprador);

        $detalle = "Rifa: {$id} | Números: {$numeros_log} | Cantidad: {$cantidad}";
        if ($accion === 'venta') {
            $detalle .= ' | Comprador: ' . ($comprador_log !== '' ? $comprador_log : 'Venta presencial');
        }
        if ($motivo_log !== '') {
            $detalle .= " | Motivo: {$motivo_log}";
        }

        $this->bitacora($acciones_bitacora[$accion] ?? 'RIFA_NUMEROS_OPERAR', $detalle);

        $mensajes = [
            'bloquear' => 'Números bloqueados correctamente.',
            'liberar'  => 'Números liberados correctamente.',
            'venta'    => 'Venta presencial registrada correctamente.',
        ];

        $this->json([
            'ok'       => TRUE,
            'msg'      => $mensajes[$accion] ?? 'Operación completada.',
            'cantidad' => $cantidad,
        ]);
    }

    /**
     * Respuesta HTTP/JSON de una operación de números.
     *
     * @param  array  $res
     * @param  string $accion
     * @return void
     */
    private function _respuesta_tickets(array $res, $accion)
    {
        $motivo = (string)($res['motivo'] ?? 'error');

        if ($motivo === 'no_encontrada') {
            return $this->json(['ok' => FALSE, 'msg' => 'Rifa no encontrada.', 'motivo' => $motivo], 404);
        }

        if ($motivo === 'no_disponible' || $motivo === 'error') {
            return $this->json([
                'ok'     => FALSE,
                'msg'    => 'No se pudo completar la operación. Inténtalo nuevamente.',
                'motivo' => $motivo,
            ], 500);
        }

        if ($motivo === 'conflictos') {
            return $this->json([
                'ok'               => FALSE,
                'msg'              => 'No se pudo completar la operación porque algunos números no están disponibles para esta acción.',
                'motivo'           => $motivo,
                'conflictos'       => $res['conflictos'] ?? [],
                'conflictos_total' => $res['conflictos_total'] ?? count($res['conflictos'] ?? []),
            ], 422);
        }

        $mensajes = [
            'estado_invalido' => 'La rifa no permite esta operación en su estado actual.',
            'sin_numeracion'  => 'La rifa todavía no tiene una numeración definida.',
            'invalido'        => (string)($res['error'] ?? 'El rango de números no es válido.'),
            'cantidad_invalida' => 'La cantidad de números no es válida.',
            'rango_demasiado_grande' => 'La numeración es demasiado grande para esta operación.',
            'sin_disponibilidad' => 'No hay suficientes números disponibles.',
            'modo_invalido'   => 'La rifa no está configurada para asignación automática.',
        ];

        return $this->json([
            'ok'     => FALSE,
            'msg'    => $mensajes[$motivo] ?? 'No se pudo completar la operación.',
            'motivo' => $motivo,
        ], 422);
    }

    /**
     * Compara el POST crudo contra la rifa actual para detectar intentos de
     * cambio estructural antes de cualquier normalización. Solo evalúa campos
     * realmente enviados (los disabled no se envían).
     *
     * @param  array $rifa
     * @return array
     */
    private function _intentos_estructurales_raw(array $rifa)
    {
        $errores = [];
        $mapa = [
            'rifa_slug'            => 'string',
            'rifa_tipo'            => 'string',
            'rifa_precio'          => 'float',
            'rifa_moneda'          => 'string',
            'rifa_asig_id'         => 'int',
            'rifa_num_id'          => 'int',
            'rifa_numeracion_tipo' => 'string',
            'rifa_numero_desde'    => 'int',
            'rifa_numero_hasta'    => 'int',
            'rifa_padding'         => 'int',
            'rifa_total_numeros'   => 'int',
            'rifa_max_por_compra'  => 'int',
            'rifa_reserva_ttl_min' => 'int',
            'rifa_reserva_auto'    => 'int',
        ];

        $gratuita_pendiente = ((string)($rifa['rifa_tipo'] ?? '') === 'gratuita'
            && empty($rifa['rifa_num_id'])
            && (int)($rifa['rifa_total_numeros'] ?? 0) === 0);

        foreach ($mapa as $campo => $tipo) {
            $post = $this->input->post($campo);
            if ($post === NULL) {
                continue;
            }

            if ($gratuita_pendiente
                && in_array($campo, ['rifa_max_por_compra', 'rifa_reserva_ttl_min', 'rifa_reserva_auto'], TRUE)) {
                continue;
            }

            $actual = $rifa[$campo] ?? NULL;
            $cambio = FALSE;

            if ($tipo === 'float') {
                $cambio = round((float)$post, 2) !== round((float)$actual, 2);
            } elseif ($tipo === 'int') {
                $cambio = (int)$post !== (int)$actual;
            } else {
                $nuevo_txt  = strtolower(trim((string)$post));
                $actual_txt = strtolower(trim((string)$actual));
                $cambio     = $nuevo_txt !== $actual_txt;
            }

            if ($cambio) {
                $errores[$campo] = 'No puedes modificar este campo en el estado actual.';
            }
        }

        return $errores;
    }

    /**
     * Detecta intentos de cambiar campos estructurales en estados no editables.
     * Solo evalúa campos realmente enviados en el POST (los deshabilitados no
     * se envían y no deben generar un falso positivo).
     *
     * @param  array $data
     * @param  array $rifa
     * @return array
     */
    private function _campos_estructurales_cambiados(array $data, array $rifa)
    {
        $errores = [];
        $mapa = [
            'rifa_slug'            => 'string',
            'rifa_tipo'            => 'string',
            'rifa_precio'          => 'float',
            'rifa_moneda'          => 'string',
            'rifa_asig_id'         => 'int',
            'rifa_num_id'          => 'int',
            'rifa_numeracion_tipo' => 'string',
            'rifa_numero_desde'    => 'int',
            'rifa_numero_hasta'    => 'int',
            'rifa_padding'         => 'int',
            'rifa_total_numeros'   => 'int',
            'rifa_max_por_compra'  => 'int',
            'rifa_reserva_ttl_min' => 'int',
            'rifa_reserva_auto'    => 'int',
        ];

        foreach ($mapa as $campo => $tipo) {
            if ($this->input->post($campo) === NULL) {
                continue;
            }

            $nuevo  = $data[$campo] ?? NULL;
            $actual = $rifa[$campo] ?? NULL;
            $cambio = FALSE;

            if ($tipo === 'float') {
                $cambio = round((float)$nuevo, 2) !== round((float)$actual, 2);
            } elseif ($tipo === 'int') {
                $cambio = (int)$nuevo !== (int)$actual;
            } else {
                $cambio = (string)$nuevo !== (string)$actual;
            }

            if ($cambio) {
                $errores[$campo] = 'No puedes modificar este campo en el estado actual.';
            }
        }

        return $errores;
    }

    /**
     * Detecta cambios de numeración/lote cuando ya existen tickets ocupados.
     *
     * @param  array $data
     * @param  array $rifa
     * @return array
     */
    private function _errores_numeracion_con_tickets(array $data, array $rifa, $solo_enviados = TRUE)
    {
        $errores = [];
        $mapa = [
            'rifa_tipo'            => 'string',
            'rifa_asig_id'         => 'int',
            'rifa_num_id'          => 'int',
            'rifa_numeracion_tipo' => 'string',
            'rifa_numero_desde'    => 'int',
            'rifa_numero_hasta'    => 'int',
            'rifa_padding'         => 'int',
            'rifa_total_numeros'   => 'int',
        ];

        foreach ($mapa as $campo => $tipo) {
            if ($solo_enviados && $this->input->post($campo) === NULL) {
                continue;
            }

            $nuevo  = $data[$campo] ?? NULL;
            $actual = $rifa[$campo] ?? NULL;

            $cambio = ($tipo === 'int')
                ? (int)$nuevo !== (int)$actual
                : (string)$nuevo !== (string)$actual;

            if ($cambio) {
                $errores[$campo] = 'No puedes cambiar la numeración o el lote mientras existan números ocupados.';
            }
        }

        return $errores;
    }

    /**
     * Indica si el formulario envió el bloque de métodos de pago.
     *
     * @return bool
     */
    private function _metodos_post_enviados()
    {
        return $this->input->post('rifa_metodos_present') !== NULL
            || $this->input->post('rifa_metodos') !== NULL;
    }

    /**
     * @return array IDs de métodos de pago seleccionados.
     */
    private function _recoger_metodos_post()
    {
        $raw = $this->input->post('rifa_metodos');

        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $valor) {
            if (is_scalar($valor) && (int)$valor > 0) {
                $ids[] = (int)$valor;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array $ids
     * @param  int   $org_id
     * @return array
     */
    private function _validar_metodos_rifa(array $ids, $org_id)
    {
        $errores = [];

        foreach ($ids as $id) {
            $metodo = $this->Org_metodo_model->get_por_id((int)$id, (int)$org_id);

            if (empty($metodo)
                || (int)$metodo['orgmet_activo'] !== 1
                || (int)$metodo['met_status'] !== 1
                || (int)$metodo['met_eliminado'] !== 0
                || empty($metodo['completo'])) {
                $errores['rifa_metodos'] = 'Uno de los métodos de pago seleccionados no está disponible o está incompleto.';
                break;
            }
        }

        return $errores;
    }

    /**
     * Recoge y normaliza los campos del formulario.
     *
     * @return array
     */
    private function _recoger_post()
    {
        $tipo = strtolower(trim($this->_post_escalar('rifa_tipo')));

        $raw_precio = str_replace(',', '.', trim($this->_post_escalar('rifa_precio')));
        $precio     = 0.00;

        if ($tipo !== 'gratuita' && $raw_precio !== '' && is_numeric($raw_precio)) {
            $precio_float = (float)$raw_precio;
            if (is_finite($precio_float)) {
                $precio = round($precio_float, 2);
            }
        }

        $moneda = strtoupper(trim($this->_post_escalar('rifa_moneda')));
        if ($moneda === '') {
            $moneda = 'USD';
        }

        $numeracion_tipo = strtolower(trim($this->_post_escalar('rifa_numeracion_tipo')));
        if ($numeracion_tipo === '') {
            $numeracion_tipo = '2';
        }

        $desde   = (int)$this->_post_escalar('rifa_numero_desde', '0');
        $hasta   = (int)$this->_post_escalar('rifa_numero_hasta', '0');
        $padding = (int)$this->_post_escalar('rifa_padding', '0');

        switch ($numeracion_tipo) {
            case '2':
                $desde = 0;
                $hasta = 99;
                $padding = 2;
                break;

            case '3':
                $desde = 0;
                $hasta = 999;
                $padding = 3;
                break;

            case '4':
                $desde = 0;
                $hasta = 9999;
                $padding = 4;
                break;

            default:
                break;
        }

        $total = ($hasta > $desde) ? ($hasta - $desde + 1) : 0;

        $auto = $this->_post_escalar('rifa_reserva_auto');

        $modo = strtolower(trim($this->_post_escalar('rifa_modo_asignacion', 'manual')));
        if (!in_array($modo, ['manual', 'automatica'], TRUE)) {
            $modo = 'manual';
        }

        $mostrar_reservados = $this->_post_escalar('rifa_mostrar_reservados', '1');
        $mostrar_reservados = in_array($mostrar_reservados, ['1', 'on', 'true'], TRUE) ? 1 : 0;

        return [
            'rifa_titulo'          => trim($this->_post_escalar('rifa_titulo')),
            'rifa_slug'            => strtolower(trim($this->_post_escalar('rifa_slug'))),
            'rifa_descripcion'     => trim($this->_post_html('rifa_descripcion')),
            'rifa_condiciones'     => trim($this->_post_escalar('rifa_condiciones')),
            'rifa_tipo'            => $tipo,
            'rifa_precio'          => $precio,
            'rifa_moneda'          => $moneda,
            'rifa_asig_id'         => max(0, (int)$this->_post_escalar('rifa_asig_id', '0')),
            'rifa_num_id'          => max(0, (int)$this->_post_escalar('rifa_num_id', '0')),
            'rifa_numeracion_tipo' => $numeracion_tipo,
            'rifa_numero_desde'    => $desde,
            'rifa_numero_hasta'    => $hasta,
            'rifa_padding'         => $padding,
            'rifa_total_numeros'   => $total,
            'rifa_max_por_compra'  => (int)$this->_post_escalar('rifa_max_por_compra', '0'),
            'rifa_reserva_ttl_min' => (int)$this->_post_escalar('rifa_reserva_ttl_min', '0'),
            'rifa_reserva_auto'    => in_array($auto, ['1', 'on', 'true'], TRUE) ? 1 : 0,
            'rifa_modo_asignacion' => $modo,
            'rifa_mostrar_reservados' => $mostrar_reservados,
            'rifa_fecha_sorteo'    => $this->_normalizar_fecha($this->_post_escalar('rifa_fecha_sorteo')),
        ];
    }

    /**
     * Valida y normaliza los datos de una rifa.
     *
     * @param  array  $data
     * @param  int    $org_id
     * @param  int    $id
     * @param  string $estado_actual
     * @return array
     */
    private function _validar(array &$data, $org_id, $id = 0, $estado_actual = '')
    {
        $errores = [];

        if (in_array((string)$estado_actual, ['finalizada', 'cancelada'], TRUE)) {
            return ['_general' => 'La rifa no puede editarse en su estado actual.'];
        }

        $titulo = trim((string)($data['rifa_titulo'] ?? ''));
        if ($titulo === '') {
            $errores['rifa_titulo'] = 'El título es obligatorio.';
        } elseif ($this->_longitud($titulo) < 3) {
            $errores['rifa_titulo'] = 'El título debe tener al menos 3 caracteres.';
        } elseif ($this->_longitud($titulo) > 150) {
            $errores['rifa_titulo'] = 'El título no puede exceder los 150 caracteres.';
        }

        $slug = strtolower(trim((string)($data['rifa_slug'] ?? '')));
        if ($slug === '') {
            $slug = $this->_slugify($titulo);
            $data['rifa_slug'] = $slug;
        }

        if ($slug === '') {
            $errores['rifa_slug'] = 'El identificador (slug) es obligatorio.';
        } elseif ($this->_longitud($slug) < 3 || $this->_longitud($slug) > 120) {
            $errores['rifa_slug'] = 'El identificador debe tener entre 3 y 120 caracteres.';
        } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $errores['rifa_slug'] = 'El identificador solo admite minúsculas, números y guiones simples.';
        } elseif ($this->Rifa_model->existe_slug($slug, (int)$org_id, (int)$id)) {
            $errores['rifa_slug'] = 'Ese identificador ya está en uso para tu vitrina.';
        }

        if ($this->_longitud((string)($data['rifa_descripcion'] ?? '')) > 20000) {
            $errores['rifa_descripcion'] = 'La descripción no puede exceder los 20000 caracteres.';
        }

        if ($this->_longitud((string)($data['rifa_condiciones'] ?? '')) > 5000) {
            $errores['rifa_condiciones'] = 'Las condiciones no pueden exceder los 5000 caracteres.';
        }

        $tipo = (string)($data['rifa_tipo'] ?? '');
        if (!in_array($tipo, ['paga', 'gratuita'], TRUE)) {
            $errores['rifa_tipo'] = 'El tipo de rifa no es válido.';
        } else {
            $precio = (float)($data['rifa_precio'] ?? 0);

            if ($tipo === 'paga') {
                if ($precio <= 0) {
                    $errores['rifa_precio'] = 'El precio debe ser mayor que cero para una rifa paga.';
                } elseif ($precio > 9999999999.99) {
                    $errores['rifa_precio'] = 'El precio excede el máximo permitido.';
                }
            } elseif ($precio !== 0.0) {
                $errores['rifa_precio'] = 'Una rifa gratuita debe tener precio cero.';
            }
        }

        $moneda = (string)($data['rifa_moneda'] ?? '');
        $monedas_validas = $this->Moneda_model->get_codigos_activos();

        if (empty($monedas_validas)) {
            $errores['rifa_moneda'] = 'El catálogo de monedas no está disponible. Contacta al administrador.';
        } elseif (!preg_match('/^[A-Z0-9]{3,10}$/', $moneda) || !in_array($moneda, $monedas_validas, TRUE)) {
            $errores['rifa_moneda'] = 'La moneda no está permitida.';
        }

        $es_edicion_bloqueada = ($id > 0 && !in_array((string)$estado_actual, ['', 'borrador', 'rechazada'], TRUE));

        $num_tipo = (string)($data['rifa_numeracion_tipo'] ?? '2');
        $desde    = (int)($data['rifa_numero_desde'] ?? 0);
        $hasta    = (int)($data['rifa_numero_hasta'] ?? 0);
        $padding  = (int)($data['rifa_padding'] ?? 0);
        $total    = (int)($data['rifa_total_numeros'] ?? 0);

        if ($tipo === 'gratuita') {
            // Sin plan ni lote: la numeración se define al aprobarse (M09).
            $data['rifa_asig_id']         = 0;
            $data['rifa_num_id']          = 0;
            $data['rifa_numeracion_tipo'] = '2';
            $data['rifa_numero_desde']    = 0;
            $data['rifa_numero_hasta']    = 0;
            $data['rifa_padding']         = 0;
            $data['rifa_total_numeros']   = 0;

            $num_tipo = '2';
            $desde = $hasta = $padding = $total = 0;
        } elseif ($tipo === 'paga' && $es_edicion_bloqueada) {
            // Los campos estructurales ya fueron preservados desde la BD.
            $data['rifa_asig_id'] = (int)($data['rifa_asig_id'] ?? 0);
            $data['rifa_num_id']  = (int)($data['rifa_num_id'] ?? 0);

            if ((int)$data['rifa_asig_id'] <= 0 || (int)$data['rifa_num_id'] <= 0) {
                $errores['rifa_asig_id'] = 'La rifa no tiene un lote y numeración válidos.';
            }

            $num_tipo = (string)($data['rifa_numeracion_tipo'] ?? '2');
            $desde    = (int)($data['rifa_numero_desde'] ?? 0);
            $hasta    = (int)($data['rifa_numero_hasta'] ?? 0);
            $padding  = (int)($data['rifa_padding'] ?? 0);
            $total    = (int)($data['rifa_total_numeros'] ?? 0);
        } elseif ($tipo === 'paga') {
            $asig_id = (int)($data['rifa_asig_id'] ?? 0);
            $num_id  = (int)($data['rifa_num_id'] ?? 0);

            if ($asig_id <= 0) {
                $errores['rifa_asig_id'] = 'Debes seleccionar un lote disponible de tu plan.';
            } else {
                $lote = $this->Plan_asignacion_model->get_lote_por_org((int)$org_id, $asig_id);

                if (empty($lote)) {
                    $errores['rifa_asig_id'] = 'El lote seleccionado no está activo o venció.';
                } else {
                    $ilimitado = !empty($lote['ilimitado']);

                    if (!$ilimitado && $id === 0 && (int)($lote['restantes'] ?? 0) <= 0) {
                        $errores['rifa_asig_id'] = 'El lote seleccionado no tiene cupos disponibles.';
                    }

                    if ($ilimitado) {
                        if ($num_id <= 0) {
                            $errores['rifa_num_id'] = 'Selecciona una numeración activa para el lote ilimitado.';
                        } else {
                            $numeracion = $this->Numeracion_model->get_por_id($num_id);

                            if (empty($numeracion) || (int)$numeracion['num_status'] !== 1) {
                                $errores['rifa_num_id'] = 'La numeración seleccionada no está activa.';
                            } else {
                                $this->_aplicar_numeracion($data, $numeracion);
                            }
                        }
                    } else {
                        $asig_num_id = isset($lote['asig_num_id']) && $lote['asig_num_id'] !== NULL
                            ? (int)$lote['asig_num_id']
                            : 0;

                        if ($asig_num_id <= 0) {
                            $errores['rifa_asig_id'] = 'El lote no tiene una numeración asociada.';
                        } else {
                            $numeracion = $this->Numeracion_model->get_por_id($asig_num_id);

                            if (empty($numeracion)) {
                                $errores['rifa_asig_id'] = 'La numeración del lote no existe.';
                            } else {
                                // La rifa hereda la numeración del lote. Cualquier
                                // numeración enviada por el usuario se ignora.
                                $data['rifa_num_id'] = $asig_num_id;
                                $this->_aplicar_numeracion($data, $numeracion);
                            }
                        }
                    }
                }
            }

            $num_tipo = (string)($data['rifa_numeracion_tipo'] ?? '2');
            $desde    = (int)($data['rifa_numero_desde'] ?? 0);
            $hasta    = (int)($data['rifa_numero_hasta'] ?? 0);
            $padding  = (int)($data['rifa_padding'] ?? 0);
            $total    = (int)($data['rifa_total_numeros'] ?? 0);

            if ($total > 0) {
                if ($hasta <= $desde || $hasta > 999999) {
                    $errores['rifa_numero_hasta'] = 'El rango de numeración no es válido.';
                } elseif ($padding < 1 || $padding > 6) {
                    $errores['rifa_padding'] = 'El relleno debe estar entre 1 y 6 dígitos.';
                } elseif ($total !== ($hasta - $desde + 1)) {
                    $errores['rifa_total_numeros'] = 'El total no coincide con el rango de numeración.';
                }
            }
        }

        $max = (int)($data['rifa_max_por_compra'] ?? 0);
        if ($tipo === 'paga') {
            if ($max < 1 || ($total > 0 && $max > $total)) {
                $errores['rifa_max_por_compra'] = 'El máximo por compra debe estar entre 1 y el total de números.';
            }
        } elseif ($max < 0 || ($total > 0 && $max > $total)) {
            $errores['rifa_max_por_compra'] = 'El máximo por compra no es válido.';
        }

        $ttl = (int)($data['rifa_reserva_ttl_min'] ?? -1);
        if ($ttl < 0 || $ttl > 10080) {
            $errores['rifa_reserva_ttl_min'] = 'La reserva debe estar entre 0 y 10080 minutos.';
        }

        if (!in_array((int)($data['rifa_reserva_auto'] ?? -1), [0, 1], TRUE)) {
            $errores['rifa_reserva_auto'] = 'La reserva automática no es válida.';
        }

        if (!in_array((string)($data['rifa_modo_asignacion'] ?? ''), ['manual', 'automatica'], TRUE)) {
            $errores['rifa_modo_asignacion'] = 'El modo de asignación no es válido.';
        }

        if (!in_array((int)($data['rifa_mostrar_reservados'] ?? -1), [0, 1], TRUE)) {
            $errores['rifa_mostrar_reservados'] = 'La visibilidad de números reservados no es válida.';
        }

        $fecha = (string)($data['rifa_fecha_sorteo'] ?? '');
        if ($fecha !== '' && !$this->_fecha_valida($fecha)) {
            $errores['rifa_fecha_sorteo'] = 'La fecha de sorteo no es válida.';
        }

        // Defensa adicional: en estados no editables no se aceptan cambios en
        // los campos estructurales, aunque el controlador ya los preserva.
        if ($id > 0 && !in_array((string)$estado_actual, ['', 'borrador', 'rechazada'], TRUE)) {
            $actual = $this->Rifa_model->get_por_id((int)$id, (int)$org_id);

            if (!empty($actual)) {
                $comparables = [
                    'rifa_slug', 'rifa_tipo', 'rifa_precio', 'rifa_moneda',
                    'rifa_asig_id', 'rifa_num_id',
                    'rifa_numeracion_tipo', 'rifa_numero_desde', 'rifa_numero_hasta',
                    'rifa_padding', 'rifa_total_numeros', 'rifa_max_por_compra',
                    'rifa_reserva_ttl_min', 'rifa_reserva_auto',
                ];

                foreach ($comparables as $campo) {
                    if (isset($errores[$campo])) {
                        continue;
                    }

                    if (in_array($campo, ['rifa_asig_id', 'rifa_num_id'], TRUE)) {
                        $cambio = (int)($data[$campo] ?? 0) !== (int)($actual[$campo] ?? 0);
                    } else {
                        $cambio = (string)($data[$campo] ?? '') !== (string)($actual[$campo] ?? '');
                    }

                    if ($cambio) {
                        $errores[$campo] = 'No puedes modificar este campo en el estado actual.';
                    }
                }
            }
        }

        return $errores;
    }

    /**
     * Sobrescribe los campos de numeración de la rifa con el catálogo.
     *
     * @param  array $data
     * @param  array $numeracion
     * @return void
     */
    private function _aplicar_numeracion(array &$data, array $numeracion)
    {
        $desde    = (int)($numeracion['num_desde'] ?? 0);
        $hasta    = (int)($numeracion['num_hasta'] ?? 0);
        $padding  = (int)($numeracion['num_padding'] ?? 0);
        $cantidad = (int)($numeracion['num_cantidad'] ?? 0);

        if ($cantidad <= 0) {
            $cantidad = max(0, $hasta - $desde + 1);
        }

        if ($desde === 0 && $hasta === 99 && $padding === 2) {
            $tipo = '2';
        } elseif ($desde === 0 && $hasta === 999 && $padding === 3) {
            $tipo = '3';
        } elseif ($desde === 0 && $hasta === 9999 && $padding === 4) {
            $tipo = '4';
        } else {
            $tipo = 'custom';
        }

        $data['rifa_numeracion_tipo'] = $tipo;
        $data['rifa_numero_desde']    = $desde;
        $data['rifa_numero_hasta']    = $hasta;
        $data['rifa_padding']         = $padding;
        $data['rifa_total_numeros']   = $cantidad;
    }

    /**
     * Mapea los datos validados al esquema de rif_rifas.
     *
     * @param  array $data
     * @return array
     */
    private function _mapear_data_guardado(array $data)
    {
        $tipo = (string)$data['rifa_tipo'];

        if (!isset($this->html_sanitizer)) {
            $this->load->library('html_sanitizer');
        }

        $descripcion = trim((string)$this->html_sanitizer->sanitize(
            (string)($data['rifa_descripcion'] ?? '')
        ));

        return [
            'rifa_titulo'          => (string)$data['rifa_titulo'],
            'rifa_slug'            => (string)$data['rifa_slug'],
            'rifa_descripcion'     => $descripcion !== '' ? $descripcion : NULL,
            'rifa_condiciones'     => $data['rifa_condiciones'] !== '' ? (string)$data['rifa_condiciones'] : NULL,
            'rifa_tipo'            => $tipo,
            'rifa_precio'          => $tipo === 'gratuita' ? 0.00 : round((float)$data['rifa_precio'], 2),
            'rifa_moneda'          => (string)$data['rifa_moneda'],
            'rifa_asig_id'         => (int)($data['rifa_asig_id'] ?? 0) > 0 ? (int)($data['rifa_asig_id'] ?? 0) : NULL,
            'rifa_num_id'          => (int)($data['rifa_num_id'] ?? 0) > 0 ? (int)($data['rifa_num_id'] ?? 0) : NULL,
            'rifa_numeracion_tipo' => (string)$data['rifa_numeracion_tipo'],
            'rifa_numero_desde'    => (int)$data['rifa_numero_desde'],
            'rifa_numero_hasta'    => (int)$data['rifa_numero_hasta'],
            'rifa_padding'         => (int)$data['rifa_padding'],
            'rifa_total_numeros'   => (int)$data['rifa_total_numeros'],
            'rifa_max_por_compra'  => (int)$data['rifa_max_por_compra'],
            'rifa_reserva_ttl_min' => (int)$data['rifa_reserva_ttl_min'],
            'rifa_reserva_auto'    => (int)$data['rifa_reserva_auto'],
            'rifa_modo_asignacion' => (string)$data['rifa_modo_asignacion'],
            'rifa_mostrar_reservados' => (int)$data['rifa_mostrar_reservados'],
            'rifa_fecha_sorteo'    => $data['rifa_fecha_sorteo'] !== '' ? (string)$data['rifa_fecha_sorteo'] : NULL,
        ];
    }

    /**
     * Procesa la imagen opcional de la rifa.
     *
     * @param  int         $org_id
     * @param  string|null $actual
     * @return array ['ok' => bool, 'filename' => string|null, 'error' => string]
     */
    private function _subir_imagen($org_id, $actual = NULL)
    {
        if (!isset($_FILES['rifa_imagen'])) {
            return ['ok' => TRUE, 'filename' => $actual, 'error' => ''];
        }

        $archivo = $_FILES['rifa_imagen'];

        if (!is_array($archivo) || is_array($archivo['name'] ?? NULL)) {
            return ['ok' => FALSE, 'filename' => $actual, 'error' => 'Archivo de imagen inválido.'];
        }

        $error = (int)($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => TRUE, 'filename' => $actual, 'error' => ''];
        }

        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => FALSE, 'filename' => $actual, 'error' => 'No se pudo recibir la imagen. Inténtalo nuevamente.'];
        }

        if ((int)($archivo['size'] ?? 0) <= 0 || (int)$archivo['size'] > 4 * 1024 * 1024) {
            return ['ok' => FALSE, 'filename' => $actual, 'error' => 'La imagen no puede superar 4 MB.'];
        }

        $tmp = (string)($archivo['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => FALSE, 'filename' => $actual, 'error' => 'Archivo inválido para la imagen.'];
        }

        $mime = $this->_detectar_mime($tmp);
        $permitidos = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($permitidos[$mime])) {
            return ['ok' => FALSE, 'filename' => $actual, 'error' => 'Solo se permiten imágenes JPG, PNG o WEBP.'];
        }

        $directorio = $this->_directorio_rifas($org_id);
        if (!is_dir($directorio) && !@mkdir($directorio, 0755, TRUE) && !is_dir($directorio)) {
            return ['ok' => FALSE, 'filename' => $actual, 'error' => 'No se pudo preparar el almacenamiento de la imagen.'];
        }

        $nombre = 'rifa-' . (int)$org_id . '-' . bin2hex(random_bytes(8)) . '.' . $permitidos[$mime];
        $ruta   = $directorio . DIRECTORY_SEPARATOR . $nombre;

        if (!@move_uploaded_file($tmp, $ruta)) {
            return ['ok' => FALSE, 'filename' => $actual, 'error' => 'No se pudo guardar la imagen.'];
        }

        return ['ok' => TRUE, 'filename' => $nombre, 'error' => ''];
    }

    /**
     * Procesa una subida múltiple del campo rifa_imagenes[].
     *
     * @param  int $org_id
     * @param  int $max
     * @return array ['ok' => bool, 'files' => array, 'error' => string]
     */
    private function _subir_imagenes($org_id, $max = 3)
    {
        $max = max(0, (int)$max);

        if (!isset($_FILES['rifa_imagenes'])) {
            return ['ok' => TRUE, 'files' => [], 'error' => ''];
        }

        $archivo = $_FILES['rifa_imagenes'];

        if (!is_array($archivo) || !isset($archivo['name'])) {
            return ['ok' => FALSE, 'files' => [], 'error' => 'Archivos de imagen inválidos.'];
        }

        $nombres  = is_array($archivo['name']) ? $archivo['name'] : [$archivo['name']];
        $tmps     = isset($archivo['tmp_name']) && is_array($archivo['tmp_name'])
            ? $archivo['tmp_name']
            : [$archivo['tmp_name'] ?? ''];
        $errores  = isset($archivo['error']) && is_array($archivo['error'])
            ? $archivo['error']
            : [$archivo['error'] ?? UPLOAD_ERR_NO_FILE];
        $tamanos  = isset($archivo['size']) && is_array($archivo['size'])
            ? $archivo['size']
            : [$archivo['size'] ?? 0];

        $total = 0;
        foreach ($nombres as $nombre) {
            if (is_array($nombre)) {
                return ['ok' => FALSE, 'files' => [], 'error' => 'Archivos de imagen inválidos.'];
            }

            if (trim((string)$nombre) !== '') {
                $total++;
            }
        }

        if ($total === 0) {
            return ['ok' => TRUE, 'files' => [], 'error' => ''];
        }

        if ($max <= 0) {
            return ['ok' => FALSE, 'files' => [], 'error' => 'No hay cupo disponible para más imágenes.'];
        }

        if ($total > $max) {
            return [
                'ok'    => FALSE,
                'files' => [],
                'error' => 'Solo puedes subir hasta ' . $max . ' imagen(es) adicional(es).',
            ];
        }

        $permitidos = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        $directorio = $this->_directorio_rifas($org_id);
        if (!is_dir($directorio) && !@mkdir($directorio, 0755, TRUE) && !is_dir($directorio)) {
            return ['ok' => FALSE, 'files' => [], 'error' => 'No se pudo preparar el almacenamiento de las imágenes.'];
        }

        $subidos = [];

        foreach (array_keys($nombres) as $indice) {
            $nombre = $nombres[$indice] ?? '';

            if (!is_scalar($nombre) || trim((string)$nombre) === '') {
                continue;
            }

            $error = (int)($errores[$indice] ?? UPLOAD_ERR_NO_FILE);

            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                $this->_borrar_archivos($org_id, $subidos);
                return ['ok' => FALSE, 'files' => [], 'error' => 'No se pudo recibir una de las imágenes. Inténtalo nuevamente.'];
            }

            $tamano = (int)($tamanos[$indice] ?? 0);

            if ($tamano <= 0 || $tamano > 4 * 1024 * 1024) {
                $this->_borrar_archivos($org_id, $subidos);
                return ['ok' => FALSE, 'files' => [], 'error' => 'Cada imagen no puede superar 4 MB.'];
            }

            $tmp = (string)($tmps[$indice] ?? '');

            if ($tmp === '' || !is_uploaded_file($tmp)) {
                $this->_borrar_archivos($org_id, $subidos);
                return ['ok' => FALSE, 'files' => [], 'error' => 'Archivo inválido para una de las imágenes.'];
            }

            $mime = $this->_detectar_mime($tmp);

            if (!isset($permitidos[$mime])) {
                $this->_borrar_archivos($org_id, $subidos);
                return ['ok' => FALSE, 'files' => [], 'error' => 'Solo se permiten imágenes JPG, PNG o WEBP.'];
            }

            $nombre_final = 'rifa-' . (int)$org_id . '-' . bin2hex(random_bytes(8)) . '.' . $permitidos[$mime];
            $ruta         = $directorio . DIRECTORY_SEPARATOR . $nombre_final;

            if (!@move_uploaded_file($tmp, $ruta)) {
                $this->_borrar_archivos($org_id, $subidos);
                return ['ok' => FALSE, 'files' => [], 'error' => 'No se pudo guardar una de las imágenes.'];
            }

            $subidos[] = ['filename' => $nombre_final];
        }

        return ['ok' => TRUE, 'files' => $subidos, 'error' => ''];
    }

    /**
     * Borra una lista de archivos de la carpeta del organizador.
     *
     * @param  int   $org_id
     * @param  array $archivos
     * @return void
     */
    private function _borrar_archivos($org_id, array $archivos)
    {
        foreach ($archivos as $archivo) {
            if (is_array($archivo)) {
                $archivo = $archivo['filename'] ?? '';
            }

            $this->_borrar_imagen($org_id, (string)$archivo);
        }
    }

    /**
     * Lista normalizada de la galería para respuestas JSON.
     *
     * @param  int $rifa_id
     * @return array
     */
    private function _listar_imagenes($rifa_id)
    {
        $filas = $this->Rifa_imagen_model->get_por_rifa((int)$rifa_id);
        $lista = [];

        foreach ($filas as $fila) {
            $img_id = (int)$fila['img_id'];

            $lista[] = [
                'img_id'    => $img_id,
                'url'       => base_url('media/rifa-imagen/' . $img_id),
                'principal' => ((int)$fila['img_principal'] === 1),
                'orden'     => (int)$fila['img_orden'],
            ];
        }

        return $lista;
    }

    /**
     * Indica si la galería puede modificarse en el estado de la rifa.
     *
     * @param  string $estado
     * @return bool
     */
    private function _estado_galeria_editable($estado)
    {
        return !in_array((string)$estado, ['finalizada', 'cancelada'], TRUE);
    }

    /**
     * Borra una imagen de la carpeta del organizador.
     *
     * @param  int    $org_id
     * @param  string $archivo
     * @return void
     */
    private function _borrar_imagen($org_id, $archivo)
    {
        if (empty($archivo)) {
            return;
        }

        $archivo = basename((string)$archivo);
        if ($archivo === '' || $archivo === '.' || $archivo === '..') {
            return;
        }

        $ruta = $this->_directorio_rifas($org_id) . DIRECTORY_SEPARATOR . $archivo;

        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }

    /**
     * Formatea las filas para DataTables del organizador.
     *
     * @param  array $rows
     * @return array
     */
    private function _formatear_filas_datatable(array $rows)
    {
        $data = [];

        foreach ($rows as $item) {
            $id      = (int)$item['rifa_id'];
            $titulo  = htmlspecialchars((string)$item['rifa_titulo'], ENT_QUOTES, 'UTF-8');
            $slug    = htmlspecialchars((string)$item['rifa_slug'], ENT_QUOTES, 'UTF-8');
            $tipo    = (string)($item['rifa_tipo'] ?? 'paga');
            $estado  = (string)($item['rifa_estado'] ?? 'borrador');
            $imagen  = (string)($item['rifa_imagen'] ?? '');

            $miniatura = '';
            if ($imagen !== '') {
                $url = htmlspecialchars(base_url('media/rifa/' . $id), ENT_QUOTES, 'UTF-8');
                $miniatura = '<img src="' . $url . '" data-rifa-thumb="1" data-src="' . $url . '"'
                    . ' alt="' . $titulo . '" class="me-3"'
                    . ' style="width:48px;height:48px;object-fit:cover;border-radius:.475rem;cursor:zoom-in;flex-shrink:0;">';
            } else {
                $miniatura = '<div class="me-3 d-flex align-items-center justify-content-center bg-light-primary text-primary"'
                    . ' style="width:48px;height:48px;border-radius:.475rem;flex-shrink:0;">'
                    . '<i class="ki-duotone ki-gift fs-3"><span class="path1"></span><span class="path2"></span></i>'
                    . '</div>';
            }

            $rifaHtml = '<div class="d-flex align-items-center">' . $miniatura
                . '<div class="d-flex flex-column">'
                . '<span class="fw-bold text-gray-900 fs-6">' . $titulo . '</span>'
                . '<span class="text-muted fw-semibold fs-7">' . $slug . '</span>'
                . '</div></div>';

            $tipoHtml = $this->_tipo_html($item);

            $num_label = trim((string)($item['num_label'] ?? ''));
            $texto_num = $num_label !== '' ? $num_label : $this->_numeracion_texto($item);

            if ($tipo === 'gratuita' && $num_label === '' && (int)($item['rifa_total_numeros'] ?? 0) === 0) {
                $texto_num = 'Pendiente de numeración';
            }

            $numeracionHtml = '<div class="d-flex flex-column">'
                . '<span class="fw-semibold text-gray-900 fs-7">' . htmlspecialchars($texto_num, ENT_QUOTES, 'UTF-8') . '</span>'
                . '<span class="text-muted fs-8">' . number_format((int)($item['rifa_total_numeros'] ?? 0), 0, '.', ',') . ' números</span>'
                . '</div>';

            $estadoHtml = $this->_estado_badge($estado);

            $sorteoHtml = '<span class="fw-semibold text-gray-900 fs-7">'
                . htmlspecialchars($this->_fecha($item['rifa_fecha_sorteo'] ?? ''), ENT_QUOTES, 'UTF-8')
                . '</span>';

            $data[] = [
                'rifa'       => $rifaHtml,
                'tipo'       => $tipoHtml,
                'numeracion' => $numeracionHtml,
                'estado'     => $estadoHtml,
                'sorteo'     => $sorteoHtml,
                'acciones'   => $this->_acciones_html($item),
                'asig_id'    => isset($item['asig_id']) && $item['asig_id'] !== NULL ? (int)$item['asig_id'] : NULL,
                'num_label'  => (string)($item['num_label'] ?? ''),
            ];
        }

        return $data;
    }

    /**
     * Agrega etiquetas y URLs al registro de una rifa.
     *
     * @param  array $rifa
     * @return array
     */
    private function _formatear_datos(array $rifa)
    {
        $id     = (int)$rifa['rifa_id'];
        $estado = (string)($rifa['rifa_estado'] ?? 'borrador');
        $tipo   = (string)($rifa['rifa_tipo'] ?? 'paga');

        $imagenes         = $this->_listar_imagenes($id);
        $tiene_principal  = FALSE;

        foreach ($imagenes as $imagen_item) {
            if (!empty($imagen_item['principal'])) {
                $tiene_principal = TRUE;
                break;
            }
        }

        $rifa['imagenes']    = $imagenes;
        $rifa['portada_url'] = (!empty($rifa['rifa_imagen']) || $tiene_principal)
            ? base_url('media/rifa/' . $id)
            : '';
        $rifa['imagen_url']  = $rifa['portada_url'];
        $rifa['estado_label'] = $this->estados[$estado] ?? $estado;
        $rifa['tipo_label'] = ($tipo === 'gratuita') ? 'Gratuita' : 'Paga';
        $rifa['modo_asignacion_label'] = (string)($rifa['rifa_modo_asignacion'] ?? 'manual') === 'automatica'
            ? 'Automática (aleatoria)'
            : 'Manual';
        $rifa['mostrar_reservados_label'] = (int)($rifa['rifa_mostrar_reservados'] ?? 1) === 1
            ? 'Sí'
            : 'Solo disponibles';
        $rifa['numeracion_label'] = $this->_numeracion_texto($rifa);
        $rifa['precio_fmt'] = number_format((float)($rifa['rifa_precio'] ?? 0), 2, '.', ',');
        $rifa['fecha_sorteo_fmt'] = $this->_fecha($rifa['rifa_fecha_sorteo'] ?? '');
        $rifa['publicada_at_fmt'] = $this->_fecha($rifa['rifa_publicada_at'] ?? '');

        $rifa['asig_id'] = isset($rifa['asig_id']) && $rifa['asig_id'] !== NULL ? (int)$rifa['asig_id'] : NULL;
        $rifa['num_label'] = (string)($rifa['num_label'] ?? '');
        $rifa['asig_plan_nombre'] = (string)($rifa['asig_plan_nombre'] ?? '');
        $rifa['lote_label'] = trim($rifa['asig_plan_nombre'] . ' · ' . $rifa['num_label'], ' ·');

        if ($rifa['lote_label'] === '') {
            $rifa['lote_label'] = $tipo === 'gratuita' ? 'Pendiente de numeración' : 'Sin lote';
        }

        $rifa['metodos_pago']         = $this->Org_metodo_model->get_ids_por_rifa($id);
        $rifa['metodos_pago_detalle'] = $this->Org_metodo_model->get_rifa_metodos($id);

        return $rifa;
    }

    /**
     * Badge HTML de tipo/precio.
     *
     * @param  array $rifa
     * @return string
     */
    private function _tipo_html(array $rifa)
    {
        if ((string)($rifa['rifa_tipo'] ?? 'paga') === 'gratuita') {
            return '<span class="badge badge-light-success fw-bold px-3 py-2">Gratuita</span>';
        }

        $moneda = htmlspecialchars((string)($rifa['rifa_moneda'] ?? 'USD'), ENT_QUOTES, 'UTF-8');
        $precio = number_format((float)($rifa['rifa_precio'] ?? 0), 2, '.', ',');

        return '<div class="d-flex flex-column">'
            . '<span class="badge badge-light-primary fw-bold px-3 py-2 align-self-start">Paga</span>'
            . '<span class="text-muted fs-8 mt-1">' . $precio . ' ' . $moneda . '</span>'
            . '</div>';
    }

    /**
     * Badge HTML de estado.
     *
     * @param  string $estado
     * @return string
     */
    private function _estado_badge($estado)
    {
        $mapa = [
            'borrador'               => ['badge-light-secondary', 'Borrador'],
            'pendiente_aprobacion'   => ['badge-light-warning', 'Pendiente'],
            'activa'                 => ['badge-light-success', 'Activa'],
            'pausada'                => ['badge-light-warning', 'Pausada'],
            'cancelacion_solicitada' => ['badge-light-info', 'Cancelación'],
            'finalizada'             => ['badge-light-primary', 'Finalizada'],
            'cancelada'              => ['badge-light-danger', 'Cancelada'],
            'rechazada'              => ['badge-light-danger', 'Rechazada'],
        ];

        $item = $mapa[(string)$estado] ?? ['badge-light-secondary', (string)$estado];

        return '<span class="badge ' . $item[0] . ' fw-bold px-3 py-2">'
            . htmlspecialchars($item[1], ENT_QUOTES, 'UTF-8') . '</span>';
    }

    /**
     * Botones de acción según estado.
     *
     * @param  array $rifa
     * @return string
     */
    private function _acciones_html(array $rifa)
    {
        $id      = (int)$rifa['rifa_id'];
        $estado  = (string)($rifa['rifa_estado'] ?? 'borrador');
        $titulo  = htmlspecialchars((string)($rifa['rifa_titulo'] ?? ''), ENT_QUOTES, 'UTF-8');
        $tiene_numeros = (int)($rifa['rifa_total_numeros'] ?? 0) > 0;
        $html    = '<div class="d-flex justify-content-end align-items-center gap-2">';

        $boton = function ($clase, $accion, $icono, $titulo_btn) use ($id, $titulo) {
            return '<button type="button" class="btn btn-sm btn-icon ' . $clase . ' ' . $accion . ' shadow-xs"'
                . ' data-id="' . $id . '" data-titulo="' . $titulo . '"'
                . ' data-bs-toggle="tooltip" data-bs-placement="top"'
                . ' title="' . $titulo_btn . '" aria-label="' . $titulo_btn . '">'
                . '<i class="ki-duotone ' . $icono . ' fs-4">'
                . '<span class="path1"></span><span class="path2"></span><span class="path3"></span>'
                . '</i></button>';
        };

        switch ($estado) {
            case 'borrador':
            case 'rechazada':
                $html .= $boton('btn-light-primary', 'btn-editar', 'ki-pencil', 'Editar rifa');
                if ($tiene_numeros) {
                    $html .= $boton('btn-light-info', 'btn-numeros', 'ki-abstract-26', 'Gestionar números');
                }
                $html .= $boton('btn-light-success', 'btn-publicar', 'ki-check-circle', 'Publicar rifa');
                $html .= $boton('btn-light-danger', 'btn-eliminar', 'ki-trash', 'Eliminar rifa');
                break;

            case 'activa':
                if ($tiene_numeros) {
                    $html .= $boton('btn-light-info', 'btn-numeros', 'ki-abstract-26', 'Gestionar números');
                }
                $html .= $boton('btn-light-warning', 'btn-pausar', 'ki-pause', 'Pausar rifa');
                $html .= $boton('btn-light-info', 'btn-solicitar-cancelacion', 'ki-cross-circle', 'Solicitar cancelación');
                $html .= $boton('btn-light-primary', 'btn-finalizar', 'ki-flag', 'Finalizar rifa');
                break;

            case 'pausada':
                if ($tiene_numeros) {
                    $html .= $boton('btn-light-info', 'btn-numeros', 'ki-abstract-26', 'Gestionar números');
                }
                $html .= $boton('btn-light-success', 'btn-reactivar', 'ki-arrows-circle', 'Reactivar rifa');
                $html .= $boton('btn-light-info', 'btn-solicitar-cancelacion', 'ki-cross-circle', 'Solicitar cancelación');
                $html .= $boton('btn-light-primary', 'btn-finalizar', 'ki-flag', 'Finalizar rifa');
                $html .= $boton('btn-light-primary', 'btn-editar', 'ki-pencil', 'Editar rifa (limitado)');
                break;

            case 'cancelacion_solicitada':
                $html .= '<button type="button" class="btn btn-sm btn-icon btn-light-secondary btn-ver" disabled'
                    . ' data-id="' . $id . '" data-bs-toggle="tooltip" data-bs-placement="top"'
                    . ' title="Cancelación en revisión" aria-label="Cancelación en revisión">'
                    . '<i class="ki-duotone ki-time fs-4"><span class="path1"></span><span class="path2"></span></i>'
                    . '</button>';
                break;

            case 'finalizada':
            case 'cancelada':
                $html .= $boton('btn-light-danger', 'btn-eliminar', 'ki-trash', 'Eliminar rifa');
                break;

            case 'pendiente_aprobacion':
            default:
                $html .= $boton('btn-light-info', 'btn-ver', 'ki-eye', 'Ver rifa');
                break;
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Respuesta JSON/HTTP para una transición fallida.
     *
     * @param  array  $res
     * @param  string $accion
     * @return void
     */
    private function _respuesta_transicion(array $res, $accion)
    {
        $motivo = (string)($res['motivo'] ?? 'error');
        $estado = (string)($res['estado'] ?? '');

        if ($motivo === 'no_encontrada') {
            return $this->json(['ok' => FALSE, 'msg' => 'Rifa no encontrada.', 'motivo' => $motivo], 404);
        }

        if ($motivo === 'error') {
            return $this->json([
                'ok'     => FALSE,
                'msg'    => 'No se pudo completar la operación. Inténtalo nuevamente.',
                'motivo' => $motivo,
            ], 500);
        }

        $mensajes = [
            'sin_plan'             => 'No tienes un plan activo. Adquiere o solicita un plan para publicar rifas pagas.',
            'sin_cupo'             => 'Tu plan no tiene cupos disponibles para publicar otra rifa.',
            'sin_lote'             => 'Selecciona el lote de tu plan y su numeración antes de publicar.',
            'asignacion_invalida'  => 'El lote seleccionado ya no está disponible o no pertenece a tu plan.',
            'vencida'              => 'Tu plan está vencido. Renueva tu plan para continuar.',
            'ya_consumido'         => 'Esta rifa ya consumió un cupo de tu plan.',
            'estado_invalido'      => 'La rifa no se encuentra en un estado compatible con esta acción.',
            'fecha_sorteo'         => 'Debes definir una fecha de sorteo futura antes de publicar.',
            'numeracion_invalida'  => 'La numeración de la rifa no es válida. Revísala antes de publicar.',
            'precio_invalido'      => 'Una rifa paga debe tener un precio mayor que cero.',
            'titulo_invalido'      => 'El título de la rifa no es válido.',
            'sin_portada'          => 'Debes subir una imagen de portada antes de publicar la rifa.',
            'sin_metodos_pago'     => 'Selecciona al menos un método de pago activo y completo antes de publicar la rifa.',
            'no_disponible'        => 'El módulo de rifas aún no está disponible.',
        ];

        $msg = $mensajes[$motivo] ?? 'No se pudo completar la operación.';

        return $this->json([
            'ok'     => FALSE,
            'msg'    => $msg,
            'motivo' => $motivo,
            'estado' => $estado,
        ], 422);
    }

    /**
     * Texto legible de la numeración.
     *
     * @param  array $rifa
     * @return string
     */
    private function _numeracion_texto(array $rifa)
    {
        $desde   = (int)($rifa['rifa_numero_desde'] ?? 0);
        $hasta   = (int)($rifa['rifa_numero_hasta'] ?? 0);
        $padding = (int)($rifa['rifa_padding'] ?? 1);

        if ($padding < 1 || $padding > 20) {
            $padding = max(1, strlen((string)max($desde, $hasta)));
        }

        return str_pad((string)$desde, $padding, '0', STR_PAD_LEFT)
            . '–' . str_pad((string)$hasta, $padding, '0', STR_PAD_LEFT);
    }

    /**
     * Fecha legible o texto por defecto.
     *
     * @param  string|null $valor
     * @return string
     */
    private function _fecha($valor)
    {
        if (empty($valor)) {
            return 'Sin fecha';
        }

        $timestamp = strtotime((string)$valor);

        return $timestamp ? date('d/m/Y H:i', $timestamp) : (string)$valor;
    }

    /**
     * Normaliza una fecha datetime-local a Y-m-d H:i:s.
     *
     * @param  string $valor
     * @return string
     */
    private function _normalizar_fecha($valor)
    {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return '';
        }

        $valor = str_replace('T', ' ', $valor);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            $valor .= ' 00:00:00';
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $valor)) {
            $valor .= ':00';
        }

        return $valor;
    }

    /**
     * Valida estrictamente una fecha Y-m-d H:i:s.
     *
     * @param  string $valor
     * @return bool
     */
    private function _fecha_valida($valor)
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', (string)$valor, $m)) {
            return FALSE;
        }

        if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            return FALSE;
        }

        if ((int)$m[4] > 23 || (int)$m[5] > 59 || (int)$m[6] > 59) {
            return FALSE;
        }

        return strtotime((string)$valor) !== FALSE;
    }

    /**
     * Genera un slug a partir del título.
     *
     * @param  string $texto
     * @return string
     */
    private function _slugify($texto)
    {
        $texto = strtolower(trim((string)$texto));

        $mapa = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ];

        $texto = strtr($texto, $mapa);
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
        $texto = trim((string)$texto, '-');

        if ($texto === '') {
            return '';
        }

        $texto = substr($texto, 0, 120);
        $texto = rtrim($texto, '-');

        if (strlen($texto) < 3) {
            $texto = 'rifa-' . $texto;
            $texto = rtrim(substr($texto, 0, 120), '-');
        }

        return $texto;
    }

    /**
     * Directorio de imágenes de rifas del organizador.
     *
     * @param  int $org_id
     * @return string
     */
    private function _directorio_rifas($org_id)
    {
        return rtrim(FCPATH, '\\/')
            . DIRECTORY_SEPARATOR . 'upload'
            . DIRECTORY_SEPARATOR . 'rifas'
            . DIRECTORY_SEPARATOR . (int)$org_id;
    }

    /**
     * Detecta el MIME real de un archivo.
     *
     * @param  string $ruta
     * @return string
     */
    private function _detectar_mime($ruta)
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
                return $info['mime'];
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($ruta);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        return 'application/octet-stream';
    }

    /**
     * Campos POST del formulario de rifa.
     *
     * @return array
     */
    private function _campos_formulario()
    {
        return [
            'rifa_titulo',
            'rifa_slug',
            'rifa_descripcion',
            'rifa_condiciones',
            'rifa_tipo',
            'rifa_precio',
            'rifa_moneda',
            'rifa_asig_id',
            'rifa_num_id',
            'rifa_numeracion_tipo',
            'rifa_numero_desde',
            'rifa_numero_hasta',
            'rifa_padding',
            'rifa_max_por_compra',
            'rifa_reserva_ttl_min',
            'rifa_reserva_auto',
            'rifa_modo_asignacion',
            'rifa_mostrar_reservados',
            'rifa_fecha_sorteo',
            'rifa_portada_id',
            'rifa_portada_index',
        ];
    }

    /**
     * Rechaza campos POST enviados como array o valores no escalares.
     *
     * @param  array $campos
     * @return array
     */
    private function _validar_campos_escalares(array $campos)
    {
        $errores = [];

        foreach ($campos as $campo) {
            if (is_array($this->input->post($campo, TRUE))) {
                $errores[$campo] = 'Valor inválido.';
            }
        }

        return $errores;
    }

    /**
     * @param  string $key
     * @param  string $default
     * @return string
     */
    private function _post_escalar($key, $default = '')
    {
        $valor = $this->input->post($key, TRUE);
        return is_scalar($valor) ? (string)$valor : $default;
    }

    /**
     * Lee un campo HTML crudo (sin filtro XSS de CI). El HTML se normaliza
     * después con Html_sanitizer antes de persistirse.
     *
     * @param  string $key
     * @param  string $default
     * @return string
     */
    private function _post_html($key, $default = '')
    {
        $valor = $this->input->post($key, FALSE);
        return is_scalar($valor) ? (string)$valor : $default;
    }

    /**
     * Normaliza search/order de DataTables sin castear arrays.
     *
     * @return array [search, order_col, order_dir]
     */
    private function _datatable_input()
    {
        $searchData = $this->input->post('search');
        $search     = '';

        if (is_array($searchData)
            && isset($searchData['value'])
            && is_scalar($searchData['value'])) {
            $search = trim((string)$searchData['value']);
        }

        $order     = $this->input->post('order');
        $order_col = 0;
        $order_dir = 'asc';

        if (is_array($order) && isset($order[0]) && is_array($order[0])) {
            if (isset($order[0]['column']) && is_scalar($order[0]['column'])) {
                $order_col = (int)$order[0]['column'];
            }
            if (isset($order[0]['dir']) && is_scalar($order[0]['dir'])) {
                $order_dir = (string)$order[0]['dir'];
            }
        }

        return [$search, $order_col, $order_dir];
    }

    /**
     * Normaliza texto para bitácora: sin saltos de línea ni controles.
     *
     * @param  string $texto
     * @return string
     */
    private function _limpiar_log($texto)
    {
        $texto = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$texto);
        $texto = preg_replace('/\s+/u', ' ', (string)$texto);
        $texto = trim((string)$texto);

        return function_exists('mb_substr')
            ? mb_substr($texto, 0, 255, 'UTF-8')
            : substr($texto, 0, 255);
    }

    /**
     * Longitud segura en caracteres.
     *
     * @param  string $valor
     * @return int
     */
    private function _longitud($valor)
    {
        return function_exists('mb_strlen') ? mb_strlen((string)$valor, 'UTF-8') : strlen((string)$valor);
    }
}
