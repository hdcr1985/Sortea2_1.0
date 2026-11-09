<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Configuracion_correos_model extends CI_Model
{
    private $tabla = 'cab_configuracion_correo';

    private $defaults = [
        'mail_enabled'    => '0',
        'mail_driver'     => 'smtp',
        'mail_host'       => '',
        'mail_port'       => '587',
        'mail_encryption' => 'tls',
        'mail_user'       => '',
        'mail_password'   => '',
        'mail_from_email' => '',
        'mail_from_name'  => 'Notificaciones del Sistema',
        'mail_reply_to'   => '',
        'mail_timeout'    => '15',
        'mail_charset'    => 'utf-8',
        'mailtype'        => 'html',
        'mail_template_default' => '',
        'mail_newline'    => "\r\n",
        'mail_crlf'       => "\r\n",
        'mail_debug'      => '0',
    ];

    private $field_types = [
        'mail_enabled'    => 'bool',
        'mail_driver'     => 'text',
        'mail_host'       => 'text',
        'mail_port'       => 'number',
        'mail_encryption' => 'text',
        'mail_user'       => 'text',
        'mail_password'   => 'password',
        'mail_from_email' => 'text',
        'mail_from_name'  => 'text',
        'mail_reply_to'   => 'text',
        'mail_timeout'    => 'number',
        'mail_charset'    => 'text',
        'mailtype'        => 'text',
        'mail_template_default' => 'text',
        'mail_newline'    => 'text',
        'mail_crlf'       => 'text',
        'mail_debug'      => 'bool',
    ];

    public function tabla_existe()
    {
        return $this->db->table_exists($this->tabla);
    }

    public function get_defaults()
    {
        return $this->defaults;
    }

    public function get_settings()
    {
        $settings = $this->defaults;

        if ($this->tabla_existe()) {
            $rows = $this->db
                ->select('cfg_clave, cfg_valor')
                ->get($this->tabla)
                ->result_array();

            foreach ($rows as $row) {
                $key = (string)($row['cfg_clave'] ?? '');
                if ($key !== '' && array_key_exists($key, $settings)) {
                    $settings[$key] = (string)($row['cfg_valor'] ?? '');
                }
            }
        }

        $settings['mail_enabled_bool'] = $settings['mail_enabled'] === '1';
        $settings['mail_debug_bool'] = $settings['mail_debug'] === '1';
        $settings['mail_password_masked'] = $settings['mail_password'] !== '' ? str_repeat('*', 10) : '';
        $settings['mail_template_view'] = $this->resolve_template_view($settings['mail_template_default']);
        $settings['ci_email_config'] = $this->build_ci_email_config($settings);

        return $settings;
    }

    public function get_available_templates()
    {
        $templates = [];
        $directory = APPPATH . 'views/emails/templates/';

        if (!is_dir($directory)) {
            return $templates;
        }

        return $this->_load_available_templates($templates, $directory);
    }

    public function get_template_editors($promotional = FALSE)
    {
        $templates = $promotional ? $this->get_promo_templates() : $this->get_available_templates();
        $directory = APPPATH . 'views/emails/templates/editable/' . ($promotional ? 'promotions/' : '');

        foreach ($templates as $slug => &$template) {
            $default_path = $directory . $slug . '.html';
            $template['subject'] = $this->_get_template_value(
                $this->_template_setting_key($slug, $promotional, 'subject'),
                $template['name']
            );
            $template['html'] = $this->_get_template_value(
                $this->_template_setting_key($slug, $promotional, 'html'),
                is_file($default_path) ? file_get_contents($default_path) : ''
            );
        }
        unset($template);

        return $templates;
    }

    public function save_template_editors(array $templates, $promotional = FALSE)
    {
        if (!$this->tabla_existe()) {
            return FALSE;
        }

        $available = $promotional ? $this->get_promo_templates() : $this->get_available_templates();
        foreach ($templates as $slug => $values) {
            if (!isset($available[$slug]) || !is_array($values)) {
                return FALSE;
            }

            $subject = trim((string)($values['subject'] ?? ''));
            $html = is_string($values['html'] ?? NULL) ? $values['html'] : '';
            if ((!$promotional && ($subject === '' || strlen($subject) > 180))
                || trim($html) === ''
                || strlen($html) > 200000
                || strpos($html, '<?') !== FALSE
                || preg_match('/<\s*(script|iframe|object|embed|form)\b|<\s*[^>]+\bon[a-z]+\s*=|javascript\s*:/i', $html)) {
                return FALSE;
            }
        }

        foreach ($templates as $slug => $values) {
            $html = is_string($values['html'] ?? NULL) ? $values['html'] : '';
            if ((!$promotional && !$this->_save_template_value($this->_template_setting_key($slug, FALSE, 'subject'), trim((string)($values['subject'] ?? ''))))
                || !$this->_save_template_value($this->_template_setting_key($slug, $promotional, 'html'), $html)) {
                return FALSE;
            }
        }

        return TRUE;
    }

    public function render_template($slug, array $data, $promotional = FALSE)
    {
        $templates = $promotional ? $this->get_promo_templates() : $this->get_available_templates();
        if (!isset($templates[$slug])) {
            return '';
        }

        $editable = $this->get_template_editors($promotional);
        $html = (string)($editable[$slug]['html'] ?? '');
        if ($html === '') {
            $view = $promotional ? $this->resolve_promo_template_view($slug) : $this->resolve_template_view($slug);
            return $view !== '' ? $this->load->view($view, $data, TRUE) : '';
        }

        $raw_tokens = [
            'email_body_html' => $this->_replace_html_text_tokens((string)($data['email_body_html'] ?? ''), $data),
            'intro_lines_html' => $this->_render_text_list($data['intro_lines'] ?? []),
            'steps_html' => $this->_render_detail_list($data['steps'] ?? [], 'step'),
            'tips_html' => $this->_render_detail_list($data['tips'] ?? [], 'tip'),
            'summary_items_html' => $this->_render_detail_list($data['summary_items'] ?? [], 'summary'),
        ];

        return preg_replace_callback('/{{{([a-zA-Z0-9_]+)}}}|{{([a-zA-Z0-9_]+)}}/', function ($matches) use ($data, $raw_tokens) {
            if ($matches[1] !== '') {
                return $raw_tokens[$matches[1]] ?? '';
            }

            $key = $matches[2];
            $value = $data[$key] ?? '';
            if (is_array($value) || is_object($value)) {
                return '';
            }

            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }, $html);
    }

    private function _replace_html_text_tokens($html, array $data)
    {
        return preg_replace_callback('/{{([a-zA-Z0-9_]+)}}/', function ($matches) use ($data) {
            $value = $data[$matches[1]] ?? NULL;
            if (!is_scalar($value)) {
                return $matches[0];
            }

            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }, $html);
    }

    public function get_template_subject($slug, $promotional = FALSE, $default = '')
    {
        return $this->_get_template_value($this->_template_setting_key($slug, $promotional, 'subject'), $default);
    }

    private function _template_setting_key($slug, $promotional, $field)
    {
        return ($promotional ? 'promo_template_' : 'mail_template_') . $field . '_' . $slug;
    }

    private function _get_template_value($key, $default)
    {
        if (!$this->tabla_existe()) {
            return $default;
        }

        $row = $this->db->select('cfg_valor')->where('cfg_clave', $key)->limit(1)->get($this->tabla)->row_array();
        return isset($row['cfg_valor']) ? (string)$row['cfg_valor'] : $default;
    }

    private function _save_template_value($key, $value)
    {
        $exists = $this->db->where('cfg_clave', $key)->count_all_results($this->tabla) > 0;
        $payload = [
            'cfg_clave' => $key,
            'cfg_valor' => $value,
            'cfg_tipo' => 'text',
            'cfg_updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($exists) {
            return $this->db->where('cfg_clave', $key)->update($this->tabla, $payload);
        }

        $payload['cfg_created_at'] = date('Y-m-d H:i:s');
        return $this->db->insert($this->tabla, $payload);
    }

    private function _render_text_list($items)
    {
        if (!is_array($items)) {
            return '';
        }

        $html = '';
        foreach ($items as $item) {
            if (is_scalar($item)) {
                $html .= '<p style="margin:0 0 12px;">' . htmlspecialchars((string)$item, ENT_QUOTES, 'UTF-8') . '</p>';
            }
        }

        return $html;
    }

    private function _render_detail_list($items, $type)
    {
        if (!is_array($items)) {
            return '';
        }

        $html = '';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            if ($type === 'summary') {
                $html .= '<tr><td style="padding:8px 0;">' . htmlspecialchars((string)($item['label'] ?? ''), ENT_QUOTES, 'UTF-8')
                    . '</td><td align="right" style="padding:8px 0;font-weight:bold;">'
                    . htmlspecialchars((string)($item['value'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td></tr>';
                continue;
            }

            $title = htmlspecialchars((string)($item['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $description = htmlspecialchars((string)($item['description'] ?? ''), ENT_QUOTES, 'UTF-8');
            $html .= '<tr><td style="padding:10px 0;"><strong>' . $title . '</strong><br>'
                . '<span style="color:#5e6278;">' . $description . '</span></td></tr>';
        }

        return $html;
    }

    private function _load_available_templates(array $templates, $directory)
    {
        $files = glob($directory . '*.php');
        if ($files === FALSE) {
            return $templates;
        }

        foreach ($files as $file_path) {
            $file_name = basename($file_path);
            if ($file_name === 'index.php') {
                continue;
            }

            $slug = pathinfo($file_name, PATHINFO_FILENAME);
            $templates[$slug] = [
                'slug' => $slug,
                'name' => ucwords(str_replace(['-', '_'], ' ', $slug)),
                'view' => 'emails/templates/' . $slug,
                'file' => 'application/views/emails/templates/' . $file_name,
            ];
        }

        ksort($templates);

        return $templates;
    }

    public function template_exists($slug)
    {
        if ($slug === '') {
            return TRUE;
        }

        $templates = $this->get_available_templates();
        return isset($templates[$slug]);
    }

    public function resolve_template_view($slug)
    {
        $slug = trim((string)$slug);
        if ($slug === '' || !$this->template_exists($slug)) {
            return '';
        }

        return 'emails/templates/' . $slug;
    }

    public function get_promo_templates()
    {
        $templates = [];
        $directory = APPPATH . 'views/emails/templates/promotions/';

        if (!is_dir($directory)) {
            return $templates;
        }

        $files = glob($directory . '*.php');
        if ($files === FALSE) {
            return $templates;
        }

        foreach ($files as $file_path) {
            $file_name = basename($file_path);
            if ($file_name === 'index.php') {
                continue;
            }

            $slug = pathinfo($file_name, PATHINFO_FILENAME);
            $templates[$slug] = [
                'slug' => $slug,
                'name' => ucwords(str_replace(['-', '_'], ' ', $slug)),
                'view' => 'emails/templates/promotions/' . $slug,
                'file' => 'application/views/emails/templates/promotions/' . $file_name,
            ];
        }

        ksort($templates);

        return $templates;
    }

    public function promo_template_exists($slug)
    {
        if ($slug === '') {
            return FALSE;
        }

        $templates = $this->get_promo_templates();
        return isset($templates[$slug]);
    }

    public function resolve_promo_template_view($slug)
    {
        $slug = trim((string)$slug);
        if ($slug === '' || !$this->promo_template_exists($slug)) {
            return '';
        }

        return 'emails/templates/promotions/' . $slug;
    }

    public function save_settings(array $settings)
    {
        if (!$this->tabla_existe()) {
            return FALSE;
        }

        foreach ($this->defaults as $key => $default) {
            if (!array_key_exists($key, $settings)) {
                continue;
            }

            $exists = $this->db
                ->where('cfg_clave', $key)
                ->count_all_results($this->tabla) > 0;

            $payload = [
                'cfg_clave'      => $key,
                'cfg_valor'      => (string)$settings[$key],
                'cfg_tipo'       => $this->field_types[$key] ?? 'text',
                'cfg_updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($exists) {
                $this->db->where('cfg_clave', $key)->update($this->tabla, $payload);
            } else {
                $payload['cfg_created_at'] = date('Y-m-d H:i:s');
                $this->db->insert($this->tabla, $payload);
            }
        }

        return TRUE;
    }

    public function build_ci_email_config(array $settings = [])
    {
        $settings = array_merge($this->defaults, $settings);

        $config = [
            'protocol'    => $settings['mail_driver'],
            'smtp_host'   => $settings['mail_host'],
            'smtp_port'   => (int)$settings['mail_port'],
            'smtp_user'   => $settings['mail_user'],
            'smtp_pass'   => $settings['mail_password'],
            'smtp_crypto' => $settings['mail_encryption'] !== 'none' ? $settings['mail_encryption'] : '',
            'mailtype'    => $settings['mailtype'],
            'charset'     => $settings['mail_charset'],
            'newline'     => $settings['mail_newline'],
            'crlf'        => $settings['mail_crlf'],
            'smtp_timeout'=> (int)$settings['mail_timeout'],
            'useragent'   => env_value('APP_EMAIL_USERAGENT', 'PROYECTO_NUEVO'),
            'wordwrap'    => TRUE,
        ];

        return [
            'enabled'     => $settings['mail_enabled'] === '1',
            'from_email'  => $settings['mail_from_email'],
            'from_name'   => $settings['mail_from_name'],
            'reply_to'    => $settings['mail_reply_to'],
            'template'    => $settings['mail_template_default'],
            'template_view' => $this->resolve_template_view($settings['mail_template_default']),
            'debug'       => $settings['mail_debug'] === '1',
            'config'      => $config,
        ];
    }
}