<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Configuracion_tema_model extends CI_Model
{
    private $tabla = 'cab_configuracion_tema';

    private $defaults = [
        'project_name'             => 'My System',
        'footer_text'              => 'All rights reserved.',
        'footer_support_label'     => 'Support',
        'footer_support_url'       => '#',
        'font_family'              => 'Inter, sans-serif',
        'google_font_family'       => '',
        'font_size_px'             => '14',
        'sidebar_width_px'         => '225',
        'dashboard_logo_height_px' => '25',
        'sidebar_font_weight'      => '400',
        'primary_color'            => '#012832',
        'button_hover_color'       => '#03333F',
        'header_bg_color'          => '#FFFFFF',
        'header_text_color'        => '#181C32',
        'sidebar_bg_color'         => '#000000',
        'sidebar_text_color'       => '#626264',
        'sidebar_active_bg_color'  => '#012832',
        'sidebar_active_text_color'=> '#FFFFFF',
        'footer_bg_color'          => '#FFFFFF',
        'footer_text_color'        => '#5E6278',
        'login_card_bg_color'      => '#FFFFFF',
        // Colores Modo Oscuro (Metronic Demo 1 & Demo 10)
        'dark_primary_color'            => '#1B84FF',
        'dark_button_hover_color'       => '#056EE9',
        'dark_header_bg_color'          => '#0D0E12',
        'dark_header_text_color'        => '#FFFFFF',
        'dark_sidebar_bg_color'         => '#0F1014',
        'dark_sidebar_text_color'       => '#9A9CAE',
        'dark_sidebar_active_bg_color'  => '#1B84FF',
        'dark_sidebar_active_text_color'=> '#FFFFFF',
        'dark_footer_bg_color'          => '#0D0E12',
        'dark_footer_text_color'        => '#78829D',
        'dark_body_bg_color'            => '#0F1014',
        'dark_login_card_bg_color'      => '#15171C',
        'login_welcome_title'      => 'My System',
        'login_welcome_subtitle'   => 'Sign in with your credentials to continue.',
        'login_form_title'         => 'Sign in',
        'login_form_subtitle'      => 'Enter your sign-in credentials',
        'public_registration_enabled' => '0',
        'public_registration_role_id' => '',
        'google_auth_enabled'      => '0',
        'google_client_id'         => '',
        'google_client_secret'     => '',
        'apple_auth_enabled'       => '0',
        'apple_client_id'          => '',
        'apple_team_id'            => '',
        'apple_key_id'             => '',
        'apple_client_secret'      => '',
        'dashboard_logo'           => '',
        'dashboard_logo_compact'   => '',
        'login_logo'               => '',
        'login_logo_dark'          => '',
        'login_background'         => '',
        'login_background_dark'    => '',
        'favicon_image'            => '',
    ];

    private $file_keys = [
        'dashboard_logo',
        'dashboard_logo_compact',
        'login_logo',
        'login_logo_dark',
        'login_background',
        'login_background_dark',
        'favicon_image',
    ];

    public function tabla_existe()
    {
        return $this->db->table_exists($this->tabla);
    }

    public function get_defaults()
    {
        return $this->defaults;
    }

    public function get_file_keys()
    {
        return $this->file_keys;
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

        $settings['app_name'] = $settings['project_name'];
        $settings['dashboard_logo_url'] = $this->resolve_asset_url($settings['dashboard_logo'], 'assets/media/logos/default-dark.svg');
        $settings['dashboard_logo_compact_url'] = $this->resolve_asset_url($settings['dashboard_logo_compact'], 'assets/media/logos/default-small.svg');
        $settings['login_logo_url'] = $this->resolve_asset_url($settings['login_logo'], (!empty($settings['dashboard_logo']) ? 'media/theme/' . rawurlencode($settings['dashboard_logo']) : 'assets/media/logos/default.svg'));
        $settings['login_logo_dark_url'] = $this->resolve_asset_url($settings['login_logo_dark'], (!empty($settings['dashboard_logo']) ? 'media/theme/' . rawurlencode($settings['dashboard_logo']) : 'assets/media/logos/default-dark.svg'));
        $settings['login_background_url'] = $this->resolve_asset_url($settings['login_background'], 'assets/media/auth/bg10.jpeg');
        $settings['login_background_dark_url'] = $this->resolve_asset_url($settings['login_background_dark'], 'assets/media/auth/bg10-dark.jpeg');
        $settings['favicon_url'] = $this->resolve_asset_url($settings['favicon_image'], 'assets/media/logos/favicon.ico');
        $settings['resolved_font_family'] = $this->resolve_font_family($settings);
        $settings['google_font_url'] = $this->build_google_font_url($settings);
        $settings['theme_css'] = $this->build_theme_css($settings);

        return $settings;
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
                'cfg_tipo'       => in_array($key, $this->file_keys, TRUE) ? 'file' : 'text',
                'cfg_updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($exists) {
                $saved = $this->db->where('cfg_clave', $key)->update($this->tabla, $payload);
            } else {
                $payload['cfg_created_at'] = date('Y-m-d H:i:s');
                $saved = $this->db->insert($this->tabla, $payload);
            }

            if (!$saved) {
                return FALSE;
            }
        }

        return TRUE;
    }

    public function resolve_asset_url($value, $default_path = '')
    {
        $value = trim((string)$value);
        if ($value === '') {
            return $default_path !== '' ? base_url($default_path) : '';
        }

        return base_url('media/theme/' . rawurlencode($value));
    }

    public function build_theme_css(array $settings)
    {
        $primary_rgb = $this->hex_to_rgb($settings['primary_color']);
        $dark_primary_rgb = $this->hex_to_rgb($settings['dark_primary_color'] ?? '#1B84FF');
        $sidebar_bg_rgb = $this->hex_to_rgb($settings['sidebar_bg_color']);
        $dark_sidebar_bg_rgb = $this->hex_to_rgb($settings['dark_sidebar_bg_color'] ?? '#0F1014');
        $sidebar_text_rgb = $this->hex_to_rgb($settings['sidebar_text_color']);
        $dark_sidebar_text_rgb = $this->hex_to_rgb($settings['dark_sidebar_text_color'] ?? '#9A9CAE');
        $sidebar_active_bg_rgb = $this->hex_to_rgb($settings['sidebar_active_bg_color']);
        $dark_sidebar_active_bg_rgb = $this->hex_to_rgb($settings['dark_sidebar_active_bg_color'] ?? '#1B84FF');

        $font_size = max(12, min(20, (int)$settings['font_size_px']));
        $sidebar_width = max(200, min(360, (int)$settings['sidebar_width_px']));
        $dashboard_logo_height = max(16, min(80, (int)($settings['dashboard_logo_height_px'] ?? 25)));
        $font_family = $settings['resolved_font_family'] ?? $this->defaults['font_family'];
        $sidebar_font_weight = in_array((string)($settings['sidebar_font_weight'] ?? '400'), ['400', '500', '600', '700'], TRUE)
            ? (string)$settings['sidebar_font_weight']
            : '400';

        $dark_primary = $settings['dark_primary_color'] ?? '#1B84FF';
        $dark_button_hover = $settings['dark_button_hover_color'] ?? '#056EE9';
        $dark_header_bg = $settings['dark_header_bg_color'] ?? '#0D0E12';
        $dark_header_text = $settings['dark_header_text_color'] ?? '#FFFFFF';
        $dark_sidebar_bg = $settings['dark_sidebar_bg_color'] ?? '#0F1014';
        $dark_sidebar_text = $settings['dark_sidebar_text_color'] ?? '#9A9CAE';
        $dark_sidebar_active_bg = $settings['dark_sidebar_active_bg_color'] ?? '#1B84FF';
        $dark_sidebar_active_text = $settings['dark_sidebar_active_text_color'] ?? '#FFFFFF';
        $dark_footer_bg = $settings['dark_footer_bg_color'] ?? '#0D0E12';
        $dark_footer_text = $settings['dark_footer_text_color'] ?? '#78829D';
        $dark_body_bg = $settings['dark_body_bg_color'] ?? '#0F1014';
        $dark_login_card_bg = $settings['dark_login_card_bg_color'] ?? '#15171C';

        return "
:root,
[data-bs-theme=\"light\"] {
    --bs-primary: {$settings['primary_color']};
    --bs-primary-rgb: {$primary_rgb};
    --app-font-family: {$font_family};
    --app-font-size: {$font_size}px;
    --app-sidebar-width: {$sidebar_width}px;
    --app-dashboard-logo-height: {$dashboard_logo_height}px;
    --app-sidebar-font-weight: {$sidebar_font_weight};
    --app-sidebar-bg: {$settings['sidebar_bg_color']};
    --app-sidebar-bg-rgb: {$sidebar_bg_rgb};
    --app-sidebar-text: {$settings['sidebar_text_color']};
    --app-sidebar-text-rgb: {$sidebar_text_rgb};
    --app-sidebar-active-bg: {$settings['sidebar_active_bg_color']};
    --app-sidebar-active-bg-rgb: {$sidebar_active_bg_rgb};
    --app-sidebar-active-text: {$settings['sidebar_active_text_color']};
    --app-header-bg: {$settings['header_bg_color']};
    --app-header-text: {$settings['header_text_color']};
    --app-footer-bg: {$settings['footer_bg_color']};
    --app-footer-text: {$settings['footer_text_color']};
    --app-login-card-bg: {$settings['login_card_bg_color']};
}

[data-bs-theme=\"dark\"] {
    --bs-primary: {$dark_primary};
    --bs-primary-rgb: {$dark_primary_rgb};
    --app-sidebar-bg: {$dark_sidebar_bg};
    --app-sidebar-bg-rgb: {$dark_sidebar_bg_rgb};
    --app-sidebar-text: {$dark_sidebar_text};
    --app-sidebar-text-rgb: {$dark_sidebar_text_rgb};
    --app-sidebar-active-bg: {$dark_sidebar_active_bg};
    --app-sidebar-active-bg-rgb: {$dark_sidebar_active_bg_rgb};
    --app-sidebar-active-text: {$dark_sidebar_active_text};
    --app-header-bg: {$dark_header_bg};
    --app-header-text: {$dark_header_text};
    --app-footer-bg: {$dark_footer_bg};
    --app-footer-text: {$dark_footer_text};
    --app-login-card-bg: {$dark_login_card_bg};
    --bs-app-bg-color: {$dark_body_bg};
    --bs-body-bg: {$dark_body_bg};
}

body {
    font-family: var(--app-font-family);
    font-size: var(--app-font-size);
}

#kt_app_sidebar,
#kt_app_sidebar .menu-link,
#kt_app_sidebar .menu-title,
#kt_app_sidebar .btn-label {
    font-family: var(--app-font-family) !important;
    font-weight: var(--app-sidebar-font-weight) !important;
}

#kt_app_sidebar .app-sidebar-logo .app-sidebar-logo-default {
    height: var(--app-dashboard-logo-height) !important;
}

#kt_app_header,
#kt_app_header .app-container,
#kt_app_header_container,
#kt_app_header_wrapper {
    background-color: var(--app-header-bg) !important;
    transition: background-color 0.25s ease, color 0.25s ease, border-color 0.25s ease !important;
}

[data-bs-theme=\"light\"] #kt_app_header {
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05) !important;
    border-bottom: 1px solid var(--bs-border-color, #eff2f5) !important;
}

[data-bs-theme=\"dark\"] #kt_app_header {
    box-shadow: none !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.07) !important;
}

#kt_app_header,
#kt_app_header h1,
#kt_app_header h2,
#kt_app_header h3,
#kt_app_header h4,
#kt_app_header h5,
#kt_app_header h6,
#kt_app_header .btn:not(.btn-primary):not(.btn-danger):not(.btn-success),
#kt_app_header .text-gray-700,
#kt_app_header .text-gray-800,
#kt_app_header .text-muted,
#kt_app_header .app-header,
#kt_app_header .ki-duotone:not(.text-primary):not(.text-danger):not(.text-success),
#kt_app_header .menu-link:not(.active),
#kt_app_header .menu-text {
    color: var(--app-header-text) !important;
}

#kt_app_footer {
    background-color: var(--app-footer-bg) !important;
}

#kt_app_footer,
#kt_app_footer a,
#kt_app_footer .text-muted,
#kt_app_footer .text-gray-800 {
    color: var(--app-footer-text) !important;
}

[data-bs-theme=\"light\"] .btn-primary,
[data-bs-theme=\"light\"] .btn.btn-primary {
    --bs-btn-bg: {$settings['primary_color']};
    --bs-btn-border-color: {$settings['primary_color']};
    --bs-btn-hover-bg: {$settings['button_hover_color']};
    --bs-btn-hover-border-color: {$settings['button_hover_color']};
    --bs-btn-active-bg: {$settings['primary_color']};
    --bs-btn-active-border-color: {$settings['primary_color']};
}

[data-bs-theme=\"dark\"] .btn-primary,
[data-bs-theme=\"dark\"] .btn.btn-primary {
    --bs-btn-bg: {$dark_primary};
    --bs-btn-border-color: {$dark_primary};
    --bs-btn-hover-bg: {$dark_button_hover};
    --bs-btn-hover-border-color: {$dark_button_hover};
    --bs-btn-active-bg: {$dark_primary};
    --bs-btn-active-border-color: {$dark_primary};
}

[data-bs-theme=\"dark\"] body {
    background-color: var(--bs-app-bg-color, #0F1014) !important;
}

.app-blank .bg-body {
    background-color: var(--app-login-card-bg) !important;
}
";
    }

    private function resolve_font_family(array $settings)
    {
        $font_family = trim((string)($settings['font_family'] ?? ''));
        if ($font_family === '__custom_google__') {
            $custom = trim((string)($settings['google_font_family'] ?? ''));
            if ($custom !== '') {
                return "'" . str_replace("'", '', $custom) . "', sans-serif";
            }
        }

        return $font_family !== '' ? $font_family : $this->defaults['font_family'];
    }

    private function build_google_font_url(array $settings)
    {
        $font_family = trim((string)($settings['font_family'] ?? ''));
        $google_family = '';

        if ($font_family === '__custom_google__') {
            $google_family = trim((string)($settings['google_font_family'] ?? ''));
        } else {
            $base_family = trim((string)preg_replace('/,.*/', '', $font_family));
            $google_supported = [
                'Inter', 'Roboto', 'Poppins', 'Montserrat', 'Nunito', 'Lato', 'Open Sans',
                'Source Sans 3', 'Work Sans', 'DM Sans', 'Mulish', 'Ubuntu', 'Rubik',
                'Manrope', 'Libre Franklin', 'Barlow', 'Oswald', 'PT Sans', 'Merriweather',
                'Playfair Display', 'Lora', 'Cormorant Garamond', 'JetBrains Mono', 'Fira Code',
                'IBM Plex Mono'
            ];

            if (in_array($base_family, $google_supported, TRUE)) {
                $google_family = $base_family;
            }
        }

        if ($google_family === '') {
            return '';
        }

        $encoded_family = str_replace(' ', '+', $google_family);
        return 'https://fonts.googleapis.com/css2?family=' . $encoded_family . ':wght@300;400;500;600;700&display=swap';
    }

    private function hex_to_rgb($hex)
    {
        $hex = ltrim((string)$hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return '27, 132, 255';
        }

        return hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2));
    }
}