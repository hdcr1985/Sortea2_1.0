<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MisOrdenes
 * ─────────────────────────────────────────────────────────────
 * M07: órdenes del organizador. URL libre "mis-ordenes"; la organización
 * se resuelve siempre desde la sesión.
 */
class MisOrdenes extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('Orden_model');
        $this->load->model('Organizador_model');

        $this->page_title = 'Órdenes';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Órdenes', 'url' => ''],
        ];
    }

    public function index()
    {
        $perfil = $this->_perfil_org();
        $org_id = (int)$perfil['org_id'];

        $this->Orden_model->expirar_vencidas(NULL, $org_id);
        $ordenes = $this->Orden_model->get_por_org($org_id, 200);

        $this->render('pages/mis_ordenes/index', [
            'url_base' => $this->url_base,
            'perfil'   => $perfil,
            'ordenes'  => $ordenes,
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
        $orden  = $this->Orden_model->get_por_id((int)$id, (int)$perfil['org_id']);

        if (empty($orden)) {
            return $this->json(['ok' => FALSE, 'msg' => 'Orden no encontrada.'], 404);
        }

        $snapshot = [];
        if (!empty($orden['ord_metodo_snapshot'])) {
            $snapshot = json_decode((string)$orden['ord_metodo_snapshot'], TRUE) ?: [];
        }

        $this->json([
            'ok'   => TRUE,
            'data' => [
                'orden'    => $orden,
                'tickets'  => $orden['tickets'] ?? [],
                'snapshot' => $snapshot,
            ],
        ]);
    }

    public function aprobar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $res = $this->Orden_model->aprobar((int)$id, (int)$perfil['org_id']);

        if (!$res['ok']) {
            return $this->_respuesta($res);
        }

        $this->bitacora(
            'ORDEN_APROBAR',
            "Orden aprobada | org: " . (int)$perfil['org_id'] . " | orden: " . (int)$id
            . " | código: " . (string)$res['orden']['ord_codigo']
        );

        $this->json(['ok' => TRUE, 'msg' => 'Orden aprobada. Los números quedaron pagados.', 'orden' => $res['orden']]);
    }

    public function rechazar($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            redirect($this->module_path());
        }
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $perfil = $this->_perfil_org();
        $motivo = trim($this->_post_escalar('motivo'));

        if ($this->_longitud($motivo) > 255) {
            return $this->json([
                'ok'      => FALSE,
                'errores' => ['motivo' => 'El motivo no puede exceder 255 caracteres.'],
            ], 422);
        }

        $res = $this->Orden_model->rechazar((int)$id, (int)$perfil['org_id'], $motivo);

        if (!$res['ok']) {
            return $this->_respuesta($res);
        }

        $this->bitacora(
            'ORDEN_RECHAZAR',
            "Orden rechazada | org: " . (int)$perfil['org_id'] . " | orden: " . (int)$id
            . " | motivo: " . ($motivo !== '' ? $motivo : 'sin motivo')
        );

        $this->json(['ok' => TRUE, 'msg' => 'Orden rechazada. Los números fueron liberados.', 'orden' => $res['orden']]);
    }

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

    private function _respuesta(array $res)
    {
        $motivo = (string)($res['motivo'] ?? 'error');

        if ($motivo === 'no_encontrada') {
            return $this->json(['ok' => FALSE, 'msg' => 'Orden no encontrada.'], 404);
        }

        if ($motivo === 'estado_invalido') {
            return $this->json(['ok' => FALSE, 'msg' => 'La orden no está pendiente de revisión.'], 422);
        }

        if ($motivo === 'sin_tickets') {
            return $this->json(['ok' => FALSE, 'msg' => 'La orden no tiene tickets reservados.'], 422);
        }

        return $this->json(['ok' => FALSE, 'msg' => 'No se pudo completar la operación.'], 500);
    }

    private function _post_escalar($key, $default = '')
    {
        $valor = $this->input->post($key, TRUE);
        return is_scalar($valor) ? (string)$valor : $default;
    }

    private function _longitud($valor)
    {
        return function_exists('mb_strlen') ? mb_strlen((string)$valor, 'UTF-8') : strlen((string)$valor);
    }
}
