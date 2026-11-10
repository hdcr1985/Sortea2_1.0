<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MisMetodosPago
 * ─────────────────────────────────────────────────────────────
 * M05: configuración de métodos de pago del organizador.
 *
 * URL libre "mis-metodos-pago". El organizador se resuelve siempre desde
 * la sesión y nunca desde el request.
 */
class MisMetodosPago extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('Org_metodo_model');
        $this->load->model('Organizador_model');

        $this->page_title = 'Mis Métodos de Pago';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Mis Métodos de Pago', 'url' => ''],
        ];
    }

    public function index()
    {
        $perfil = $this->_perfil_org();
        $org_id = (int)$perfil['org_id'];

        $this->render('pages/mis_metodos_pago/index', [
            'url_base'    => $this->url_base,
            'perfil'      => $perfil,
            'metodos'     => $this->Org_metodo_model->get_por_org($org_id),
            'disponibles' => $this->Org_metodo_model->get_globales_disponibles($org_id),
        ]);
    }

    public function datos($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'GET') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $data   = $this->Org_metodo_model->get_por_id((int)$id, (int)$perfil['org_id']);

        if (empty($data)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Método de pago no encontrado.'], 404);
        }

        $this->json(['ok' => TRUE, 'data' => $data]);
    }

    public function configurar()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $met_id = (int)$this->_post_escalar('met_id', '0');

        $orgmet_id = $this->Org_metodo_model->configurar_global((int)$perfil['org_id'], $met_id);
        if (!$orgmet_id) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo configurar el método de pago.'], 422);
        }

        $data = $this->Org_metodo_model->get_por_id((int)$orgmet_id, (int)$perfil['org_id']);

        $this->bitacora(
            'ORG_METODO_CONFIGURAR',
            "Método global configurado | org: " . (int)$perfil['org_id']
            . " | met_id: {$met_id} | orgmet_id: {$orgmet_id}"
        );

        $this->json([
            'ok'   => TRUE,
            'msg'  => 'Método agregado. Completa sus valores para usarlo en tus rifas.',
            'data' => $data,
        ]);
    }

    public function guardar_valores($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $id     = (int)$id;

        $valores = $this->input->post('valores');
        if (!is_array($valores)) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['valores' => 'Los valores enviados no son válidos.'],
            ], 422);
        }

        $res = $this->Org_metodo_model->guardar_valores($id, (int)$perfil['org_id'], $valores);

        if (!$res['ok']) {
            if (($res['motivo'] ?? '') === 'validacion') {
                return $this->json(['ok' => FALSE, 'errores' => $res['errores'] ?? []], 422);
            }

            $status = (($res['motivo'] ?? '') === 'no_encontrada') ? 404 : 500;
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudieron guardar los valores.'], $status);
        }

        $data = $this->Org_metodo_model->get_por_id($id, (int)$perfil['org_id']);

        $this->bitacora(
            'ORG_METODO_VALORES',
            "Valores de método actualizados | org: " . (int)$perfil['org_id'] . " | orgmet_id: {$id}"
        );

        $this->json([
            'ok'   => TRUE,
            'msg'  => 'Datos del método guardados correctamente.',
            'data' => $data,
        ]);
    }

    public function crear()
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $data   = $this->_recoger_post();
        $campos = $this->_recoger_campos();
        $errores = $this->_validar_custom($data, $campos);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $data['met_created_by'] = isset($this->usuario['usu_id']) ? (int)$this->usuario['usu_id'] : NULL;
        $orgmet_id = $this->Org_metodo_model->crear_custom((int)$perfil['org_id'], $data, $campos);

        if (!$orgmet_id) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo crear el método propio.'], 500);
        }

        $this->bitacora(
            'ORG_METODO_CREAR',
            "Método propio creado | org: " . (int)$perfil['org_id']
            . " | orgmet_id: {$orgmet_id} | Nombre: {$data['met_nombre']}"
        );

        $this->json([
            'ok'   => TRUE,
            'msg'  => 'Método propio creado. Ahora completa sus valores.',
            'id'   => (int)$orgmet_id,
            'data' => $this->Org_metodo_model->get_por_id((int)$orgmet_id, (int)$perfil['org_id']),
        ]);
    }

    public function actualizar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $id     = (int)$id;
        $actual = $this->Org_metodo_model->get_por_id($id, (int)$perfil['org_id']);

        if (empty($actual) || (string)$actual['met_scope'] !== 'organizador') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método propio no encontrado.'], 404);
        }

        $data   = $this->_recoger_post();
        $campos = $this->_recoger_campos();
        $errores = $this->_validar_custom($data, $campos);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        if ($this->Org_metodo_model->tiene_rifas_activas($id)
            && $this->Org_metodo_model->campos_cambiaron((int)$actual['met_id'], $campos)) {
            return $this->json([
                'ok'  => FALSE,
                'msg' => 'No puedes modificar los campos de un método vinculado a una rifa activa o pausada. Desactiva o cierra esas rifas primero.',
            ], 422);
        }

        if (!$this->Org_metodo_model->actualizar_custom($id, (int)$perfil['org_id'], $data, $campos)) {
            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo actualizar el método propio.'], 500);
        }

        $this->bitacora(
            'ORG_METODO_ACTUALIZAR',
            "Método propio actualizado | org: " . (int)$perfil['org_id']
            . " | orgmet_id: {$id} | Nombre: {$data['met_nombre']}"
        );

        $this->json([
            'ok'   => TRUE,
            'msg'  => 'Método propio actualizado correctamente.',
            'data' => $this->Org_metodo_model->get_por_id($id, (int)$perfil['org_id']),
        ]);
    }

    public function estado($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $id     = (int)$id;
        $actual = $this->Org_metodo_model->get_por_id($id, (int)$perfil['org_id']);

        if (empty($actual)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Método de pago no encontrado.'], 404);
        }

        $res = $this->Org_metodo_model->toggle_activo($id, (int)$perfil['org_id']);
        if (!$res['ok']) {
            if (($res['motivo'] ?? '') === 'en_uso') {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'No puedes desactivar un método usado por una rifa activa.',
                ], 422);
            }

            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo cambiar el estado.'], 500);
        }

        $estado = ((int)$res['nuevo_status'] === 1) ? 'activado' : 'desactivado';
        $this->bitacora(
            'ORG_METODO_STATUS',
            "Método {$estado} | org: " . (int)$perfil['org_id'] . " | orgmet_id: {$id}"
        );

        $this->json([
            'ok'           => TRUE,
            'msg'          => "El método fue {$estado} correctamente.",
            'nuevo_status' => (int)$res['nuevo_status'],
        ]);
    }

    public function eliminar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $id     = (int)$id;
        $actual = $this->Org_metodo_model->get_por_id($id, (int)$perfil['org_id']);

        if (empty($actual) || (string)$actual['met_scope'] !== 'organizador') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método propio no encontrado.'], 404);
        }

        $res = $this->Org_metodo_model->eliminar_custom($id, (int)$perfil['org_id']);
        if (!$res['ok']) {
            if (($res['motivo'] ?? '') === 'en_uso') {
                return $this->json([
                    'ok'  => FALSE,
                    'msg' => 'No puedes eliminar un método vinculado a una rifa. Desactívalo en su lugar.',
                ], 422);
            }

            return $this->json(['ok' => FALSE, 'msg' => 'No se pudo eliminar el método.'], 500);
        }

        $this->bitacora(
            'ORG_METODO_ELIMINAR',
            "Método propio eliminado | org: " . (int)$perfil['org_id'] . " | orgmet_id: {$id}"
        );

        $this->json(['ok' => TRUE, 'msg' => 'Método propio eliminado correctamente.']);
    }

    // ─────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────

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

    private function _recoger_post()
    {
        return [
            'met_nombre'        => trim($this->_post_escalar('met_nombre')),
            'met_descripcion'   => trim($this->_post_escalar('met_descripcion')),
            'met_instrucciones' => trim($this->_post_html('met_instrucciones')),
            'met_orden'         => (int)$this->_post_escalar('met_orden', '1'),
        ];
    }

    private function _recoger_campos()
    {
        $raw = $this->input->post('campos');
        if (!is_array($raw)) {
            return [];
        }

        $campos = [];
        foreach ($raw as $campo) {
            if (!is_array($campo)) {
                continue;
            }

            $campos[] = [
                'campo_id'          => (int)($campo['campo_id'] ?? 0),
                'campo_clave'       => trim((string)($campo['campo_clave'] ?? '')),
                'campo_etiqueta'    => trim((string)($campo['campo_etiqueta'] ?? '')),
                'campo_tipo'        => trim((string)($campo['campo_tipo'] ?? 'text')),
                'campo_requerido'   => in_array((string)($campo['campo_requerido'] ?? '0'), ['1', 'on', 'true'], TRUE) ? 1 : 0,
                'campo_placeholder' => trim((string)($campo['campo_placeholder'] ?? '')),
                'campo_ayuda'       => trim((string)($campo['campo_ayuda'] ?? '')),
                'campo_opciones'    => trim((string)($campo['campo_opciones'] ?? '')),
                'campo_orden'       => (int)($campo['campo_orden'] ?? 1),
            ];
        }

        return $campos;
    }

    private function _validar_custom(array $data, array $campos)
    {
        $errores = [];

        if ($this->_longitud((string)($data['met_nombre'] ?? '')) < 2
            || $this->_longitud((string)($data['met_nombre'] ?? '')) > 120) {
            $errores['met_nombre'] = 'El nombre debe tener entre 2 y 120 caracteres.';
        }

        if ($this->_longitud((string)($data['met_descripcion'] ?? '')) > 255) {
            $errores['met_descripcion'] = 'La descripción no puede exceder 255 caracteres.';
        }

        if ($this->_longitud((string)($data['met_instrucciones'] ?? '')) > 5000) {
            $errores['met_instrucciones'] = 'Las instrucciones no pueden exceder 5000 caracteres.';
        }

        if ((int)($data['met_orden'] ?? -1) < 0 || (int)($data['met_orden'] ?? -1) > 9999) {
            $errores['met_orden'] = 'El orden debe estar entre 0 y 9999.';
        }

        if (empty($campos)) {
            $errores['campos'] = 'Agrega al menos un campo al método propio.';
        } elseif (count($campos) > 50) {
            $errores['campos'] = 'No puedes agregar más de 50 campos.';
        } else {
            $tipos = ['text', 'email', 'tel', 'number', 'url', 'textarea', 'select'];
            foreach ($campos as $i => $campo) {
                if ($this->_longitud((string)$campo['campo_etiqueta']) < 1
                    || $this->_longitud((string)$campo['campo_etiqueta']) > 120) {
                    $errores['campos_' . $i . '_etiqueta'] = 'Cada campo debe tener una etiqueta de 1 a 120 caracteres.';
                }

                if ($this->_longitud((string)$campo['campo_clave']) > 60) {
                    $errores['campos_' . $i . '_clave'] = 'La clave del campo no puede exceder 60 caracteres.';
                }

                if ($this->_longitud((string)$campo['campo_placeholder']) > 150) {
                    $errores['campos_' . $i . '_placeholder'] = 'El placeholder no puede exceder 150 caracteres.';
                }

                if ($this->_longitud((string)$campo['campo_ayuda']) > 255) {
                    $errores['campos_' . $i . '_ayuda'] = 'La ayuda no puede exceder 255 caracteres.';
                }

                if ($this->_longitud((string)$campo['campo_opciones']) > 2000) {
                    $errores['campos_' . $i . '_opciones'] = 'Las opciones no pueden exceder 2000 caracteres.';
                }

                if (!in_array((string)$campo['campo_tipo'], $tipos, TRUE)) {
                    $errores['campos_' . $i . '_tipo'] = 'El tipo de campo no es válido.';
                }

                if ((string)$campo['campo_tipo'] === 'select'
                    && trim((string)$campo['campo_opciones']) === '') {
                    $errores['campos_' . $i . '_opciones'] = 'Un campo de selección necesita opciones.';
                }

                if ((int)$campo['campo_orden'] < 0 || (int)$campo['campo_orden'] > 9999) {
                    $errores['campos_' . $i . '_orden'] = 'El orden del campo no es válido.';
                }
            }
        }

        return $errores;
    }

    private function _post_escalar($key, $default = '')
    {
        $valor = $this->input->post($key, TRUE);
        return is_scalar($valor) ? (string)$valor : $default;
    }

    private function _post_html($key, $default = '')
    {
        $valor = $this->input->post($key, FALSE);
        return is_scalar($valor) ? (string)$valor : $default;
    }

    private function _longitud($valor)
    {
        return function_exists('mb_strlen') ? mb_strlen((string)$valor, 'UTF-8') : strlen((string)$valor);
    }
}
