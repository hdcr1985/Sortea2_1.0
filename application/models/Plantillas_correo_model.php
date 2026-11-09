<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Plantillas_correo_model extends CI_Model
{
    protected $table = 'cab_plantillas_correo';
    protected $primary_key = 'plc_id';

    protected $columnas_ordenables = [
        0 => 'plc_id',
        1 => 'plc_codigo',
        2 => 'plc_nombre',
        3 => 'plc_asunto',
        4 => 'plc_status',
        5 => 'plc_updated_at',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->asegurar_tabla();
    }

    /**
     * Asegura que la tabla exista y tenga los registros base cargados (Auto-curación).
     */
    public function asegurar_tabla()
    {
        if (!$this->db->table_exists($this->table)) {
            $this->db->query("CREATE TABLE IF NOT EXISTS `{$this->table}` (
                `plc_id` INT AUTO_INCREMENT PRIMARY KEY,
                `plc_codigo` VARCHAR(50) NOT NULL UNIQUE,
                `plc_nombre` VARCHAR(100) NOT NULL,
                `plc_descripcion` VARCHAR(255) NULL,
                `plc_asunto` VARCHAR(255) NOT NULL,
                `plc_preheader` VARCHAR(255) NULL,
                `plc_titulo` VARCHAR(200) NULL,
                `plc_subtitulo` VARCHAR(255) NULL,
                `plc_cuerpo` LONGTEXT NOT NULL,
                `plc_boton_texto` VARCHAR(100) NULL,
                `plc_boton_url` VARCHAR(255) NULL,
                `plc_footer_texto` VARCHAR(255) NULL,
                `plc_variables_disponibles` TEXT NULL,
                `plc_status` TINYINT(1) NOT NULL DEFAULT 1,
                `plc_eliminado` TINYINT(1) NOT NULL DEFAULT 0,
                `plc_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `plc_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_plc_status` (`plc_status`),
                KEY `idx_plc_eliminado` (`plc_eliminado`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $this->_sembrar_plantillas_default();
        }
    }

    private function _sembrar_plantillas_default()
    {
        $defaults = [
            [
                'plc_codigo' => 'BIENVENIDA',
                'plc_nombre' => 'New User Welcome',
                'plc_descripcion' => 'Sent automatically when a new user registers or is added to the system.',
                'plc_asunto' => 'Welcome to {empresa_nombre}! Your account is ready',
                'plc_preheader' => 'Welcome to our platform.',
                'plc_titulo' => 'Welcome to {empresa_nombre}!',
                'plc_subtitulo' => 'We are glad to welcome you to our community.',
                'plc_cuerpo' => '<p>Hello <strong>{usuario_nombre}</strong>,</p><p>Your account has been created successfully with the username <strong>{usuario_usuario}</strong>. You can now access the platform and use all the tools and features available to streamline your operations.</p><p>If you have any questions or need technical assistance, our support team is always ready to help.</p>',
                'plc_boton_texto' => 'Go to Dashboard',
                'plc_boton_url' => '{enlace_accion}',
                'plc_footer_texto' => 'This email is generated automatically by {empresa_nombre}. Please do not reply directly to this message.',
                'plc_variables_disponibles' => '{"{usuario_nombre}":"Full user name","{usuario_usuario}":"Username/login","{usuario_correo}":"Email address","{enlace_accion}":"Dashboard or home link","{empresa_nombre}":"Company name","{empresa_correo}":"Support email","{empresa_telefono}":"Contact phone","{fecha_actual}":"Current date (d/m/Y)","{ano_actual}":"Current year (Y)"}',
            ],
            [
                'plc_codigo' => 'RECUPERAR_CLAVE',
                'plc_nombre' => 'Password Reset',
                'plc_descripcion' => 'Sent when the user requests to reset a forgotten password.',
                'plc_asunto' => 'Reset your password - {empresa_nombre}',
                'plc_preheader' => 'Instructions to recover your access.',
                'plc_titulo' => 'Password Reset',
                'plc_subtitulo' => 'We received a request to change your password.',
                'plc_cuerpo' => '<p>Hello <strong>{usuario_nombre}</strong>,</p><p>We received a request to reset the access password associated with your account (<strong>{usuario_usuario}</strong>).</p><p>To set a new secure password, click the button below. This link is valid for a limited time and will expire automatically for security reasons.</p><p><em>If you did not request this change, you can ignore this message; your current password will remain safe and unchanged.</em></p>',
                'plc_boton_texto' => 'Reset Password',
                'plc_boton_url' => '{enlace_accion}',
                'plc_footer_texto' => 'For your security, never share your access credentials with third parties.',
                'plc_variables_disponibles' => '{"{usuario_nombre}":"User name","{usuario_usuario}":"Username","{usuario_correo}":"Email address","{enlace_accion}":"Temporary link to change password","{empresa_nombre}":"Company name","{fecha_actual}":"Current date","{ano_actual}":"Current year"}',
            ],
            [
                'plc_codigo' => 'VERIFICAR_CORREO',
                'plc_nombre' => 'Email Verification',
                'plc_descripcion' => 'Sent after public registration to validate the email address and activate the account.',
                'plc_asunto' => 'Confirm your email to activate your account - {empresa_nombre}',
                'plc_preheader' => 'Confirm your email address.',
                'plc_titulo' => 'Confirm Your Account',
                'plc_subtitulo' => 'Just one more step to complete your registration.',
                'plc_cuerpo' => '<p>Hello <strong>{usuario_nombre}</strong>,</p><p>Thank you for registering with <strong>{empresa_nombre}</strong>. To help keep your account secure and activate your access to the system, please confirm your email address by clicking the button below:</p>',
                'plc_boton_texto' => 'Confirmar y Activar Cuenta',
                'plc_boton_url' => '{enlace_accion}',
                'plc_footer_texto' => 'If you did not create this account, please disregard this email.',
                'plc_variables_disponibles' => '{"{usuario_nombre}":"User name","{usuario_usuario}":"Created username","{usuario_correo}":"Email to verify","{enlace_accion}":"Unique activation link","{empresa_nombre}":"Company name","{fecha_actual}":"Current date","{ano_actual}":"Current year"}',
            ],
            [
                'plc_codigo' => 'LOGIN_2FA',
                'plc_nombre' => '2FA Security Code',
                'plc_descripcion' => 'Sent during sign-in when two-step verification is required.',
                'plc_asunto' => 'Your sign-in verification code: {codigo_verificacion}',
                'plc_preheader' => 'Temporary security code to access your account.',
                'plc_titulo' => 'Verification Code',
                'plc_subtitulo' => 'Use this code to complete your sign-in.',
                'plc_cuerpo' => '<p>Hello <strong>{usuario_nombre}</strong>,</p><p>A sign-in attempt was detected on your account. To verify your identity, enter the following security code on the sign-in screen:</p><div style="text-align: center; margin: 25px 0;"><span style="display: inline-block; font-size: 32px; font-weight: bold; letter-spacing: 6px; padding: 12px 24px; background: #F1F1F4; border-radius: 8px; color: #181C32;">{codigo_verificacion}</span></div><p>This code is single-use and will expire in a few minutes. Do not share this code with anyone.</p>',
                'plc_boton_texto' => NULL,
                'plc_boton_url' => NULL,
                'plc_footer_texto' => 'If you did not try to sign in, we recommend changing your password immediately.',
                'plc_variables_disponibles' => '{"{usuario_nombre}":"User name","{codigo_verificacion}":"6-digit numeric code","{usuario_usuario}":"Username","{empresa_nombre}":"Company name","{fecha_actual}":"Current date","{ano_actual}":"Current year"}',
            ],
            [
                'plc_codigo' => 'AVISO_SEGURIDAD_IP',
                'plc_nombre' => 'Sign-In Alert',
                'plc_descripcion' => 'Security notification sent when a sign-in from a new device or IP is detected.',
                'plc_asunto' => 'Security Alert: New sign-in to your account - {empresa_nombre}',
                'plc_preheader' => 'Recent activity notice for your account.',
                'plc_titulo' => 'New Sign-In Detected',
                'plc_subtitulo' => 'We verified a recent connection to your account.',
                'plc_cuerpo' => '<p>Hello <strong>{usuario_nombre}</strong>,</p><p>We want to let you know that a new sign-in was recorded on your <strong>{empresa_nombre}</strong> account with the following details:</p><ul style="line-height: 1.8; color: #3F4254;"><li><strong>Date and Time:</strong> {fecha_actual}</li><li><strong>IP Address:</strong> {ip_direccion}</li><li><strong>Browser / Device:</strong> {navegador}</li><li><strong>Estimated Location:</strong> {ubicacion}</li></ul><p>If this was you, you can safely ignore this email. If you do not recognize this activity, we suggest signing in immediately and changing your access password.</p>',
                'plc_boton_texto' => 'Review My Account Security',
                'plc_boton_url' => '{enlace_accion}',
                'plc_footer_texto' => 'Security and Audit Center of {empresa_nombre}.',
                'plc_variables_disponibles' => '{"{usuario_nombre}":"User name","{usuario_usuario}":"Username","{ip_direccion}":"Connection IP","{navegador}":"Browser and OS","{ubicacion}":"City and country","{enlace_accion}":"Profile/security link","{empresa_nombre}":"Company name","{fecha_actual}":"Event date and time","{ano_actual}":"Current year"}',
            ]
        ];

        foreach ($defaults as $row) {
            $exists = $this->db->where('plc_codigo', $row['plc_codigo'])->count_all_results($this->table) > 0;
            if (!$exists) {
                $this->db->insert($this->table, $row);
            }
        }
    }

    public function get_por_id($id)
    {
        return $this->db
            ->where($this->primary_key, (int)$id)
            ->where('plc_eliminado', 0)
            ->get($this->table)
            ->row_array();
    }

    public function get_por_codigo($codigo)
    {
        return $this->db
            ->where('plc_codigo', trim((string)$codigo))
            ->where('plc_status', 1)
            ->where('plc_eliminado', 0)
            ->get($this->table)
            ->row_array();
    }

    public function existe_codigo($codigo, $id_excluir = 0)
    {
        $this->db
            ->where('plc_codigo', trim((string)$codigo))
            ->where('plc_eliminado', 0);

        if ($id_excluir > 0) {
            $this->db->where($this->primary_key . ' !=', (int)$id_excluir);
        }

        return $this->db->count_all_results($this->table) > 0;
    }

    public function count_todos()
    {
        return $this->db
            ->where('plc_eliminado', 0)
            ->count_all_results($this->table);
    }

    public function count_filtrados($search = '')
    {
        $this->_datatable_query($search);
        return $this->db->count_all_results($this->table);
    }

    public function get_datatable($start, $length, $search = '', $order_col = 0, $order_dir = 'asc')
    {
        $this->_datatable_query($search);

        $columna = $this->columnas_ordenables[$order_col] ?? $this->primary_key;
        $direccion = strtolower((string)$order_dir) === 'desc' ? 'DESC' : 'ASC';
        $this->db->order_by($columna, $direccion);

        if ((int)$length > 0) {
            $this->db->limit((int)$length, (int)$start);
        }

        return $this->db->get($this->table)->result_array();
    }

    private function _datatable_query($search = '')
    {
        $this->db->where('plc_eliminado', 0);

        $search = trim((string)$search);
        if ($search !== '') {
            $this->db->group_start();
            $this->db->like('plc_codigo', $search);
            $this->db->or_like('plc_nombre', $search);
            $this->db->or_like('plc_asunto', $search);
            $this->db->or_like('plc_descripcion', $search);
            $this->db->group_end();
        }
    }

    public function insertar(array $data)
    {
        $data['plc_created_at'] = date('Y-m-d H:i:s');
        $data['plc_updated_at'] = date('Y-m-d H:i:s');
        if ($this->db->insert($this->table, $data)) {
            return (int)$this->db->insert_id();
        }
        return 0;
    }

    public function actualizar($id, array $data)
    {
        $data['plc_updated_at'] = date('Y-m-d H:i:s');
        return $this->db
            ->where($this->primary_key, (int)$id)
            ->update($this->table, $data);
    }

    public function eliminar($id)
    {
        return $this->db
            ->where($this->primary_key, (int)$id)
            ->update($this->table, [
                'plc_eliminado'  => 1,
                'plc_updated_at' => date('Y-m-d H:i:s')
            ]);
    }

    public function cambiar_status($id, $status)
    {
        return $this->db
            ->where($this->primary_key, (int)$id)
            ->update($this->table, [
                'plc_status'     => (int)$status === 1 ? 1 : 0,
                'plc_updated_at' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * Renderiza una plantilla transaccional sustituyendo las variables dinámicas
     * y envolviéndola en el layout HTML responsivo de Metronic.
     *
     * @param string $codigo Código único de la plantilla (ej: 'RECUPERAR_CLAVE')
     * @param array $datos Variables dinámicas a sustituir
     * @return array ['ok' => bool, 'asunto' => string, 'cuerpo_html' => string, 'plantilla' => array]
     */
    public function render_plantilla($codigo, array $datos = [])
    {
        $plantilla = $this->get_por_codigo($codigo);
        if (!$plantilla) {
            return [
                'ok' => FALSE,
                'msg' => "The template with code '{$codigo}' does not exist or is inactive.",
            ];
        }

        return [
            'ok' => TRUE,
            'asunto' => $this->procesar_texto($plantilla['plc_asunto'], $datos),
            'cuerpo_html' => $this->generar_html_completo($plantilla, $datos),
            'plantilla' => $plantilla,
        ];
    }

    /**
     * Reemplaza variables tipo {variable} en una cadena de texto.
     */
    public function procesar_texto($texto, array $datos = [])
    {
        $texto = (string)$texto;
        $variables = $this->obtener_variables_globales($datos);

        return strtr($texto, $variables);
    }

    /**
     * Retorna las variables por defecto del sistema combinadas con las pasadas por parámetro.
     */
    public function obtener_variables_globales(array $extra = [])
    {
        $this->load->model('Configuracion_general_model');
        $this->load->model('Configuracion_tema_model');

        $gen_cfg = $this->Configuracion_general_model->get_settings();
        $theme_cfg = $this->Configuracion_tema_model->get_settings();

        $empresa_nombre = !empty($gen_cfg['empresa_nombre']) ? $gen_cfg['empresa_nombre'] : ($theme_cfg['project_name'] ?? 'My System');

        $base = [
            '{empresa_nombre}'    => $empresa_nombre,
            '{empresa_correo}'    => (string)($gen_cfg['empresa_correo'] ?? ''),
            '{empresa_telefono}'  => (string)($gen_cfg['empresa_telefono'] ?? ''),
            '{empresa_direccion}' => (string)($gen_cfg['empresa_direccion'] ?? ''),
            '{fecha_actual}'      => date('d/m/Y'),
            '{ano_actual}'        => date('Y'),
            '{enlace_login}'      => base_url('login'),
        ];

        return array_merge($base, $extra);
    }

    /**
     * Ensambla el correo completo dentro del layout responsivo canónico de Metronic.
     */
    public function generar_html_completo(array $plantilla, array $datos = [])
    {
        $asunto      = $this->procesar_texto($plantilla['plc_asunto'] ?? '', $datos);
        $preheader   = $this->procesar_texto($plantilla['plc_preheader'] ?? '', $datos);
        $titulo      = $this->procesar_texto($plantilla['plc_titulo'] ?? '', $datos);
        $subtitulo   = $this->procesar_texto($plantilla['plc_subtitulo'] ?? '', $datos);
        $cuerpo      = $this->procesar_texto($plantilla['plc_cuerpo'] ?? '', $datos);
        $boton_texto = trim((string)$this->procesar_texto($plantilla['plc_boton_texto'] ?? '', $datos));
        $boton_url   = trim((string)$this->procesar_texto($plantilla['plc_boton_url'] ?? '', $datos));
        $footer      = $this->procesar_texto($plantilla['plc_footer_texto'] ?? '', $datos);

        $this->load->model('Configuracion_tema_model');
        $this->load->model('Configuracion_general_model');
        $theme_cfg = $this->Configuracion_tema_model->get_settings();
        $gen_cfg   = $this->Configuracion_general_model->get_settings();

        $primary_color = !empty($theme_cfg['primary_color']) ? $theme_cfg['primary_color'] : '#1B84FF';
        $logo_url      = !empty($theme_cfg['login_logo_url']) ? $theme_cfg['login_logo_url'] : base_url('assets/media/logos/default-dark.svg');
        $project_name  = !empty($gen_cfg['empresa_nombre']) ? $gen_cfg['empresa_nombre'] : ($theme_cfg['project_name'] ?? 'My System');

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo htmlspecialchars($asunto); ?></title>
    <?php if ($preheader !== ''): ?>
    <style>
        .preheader { display:none !important; visibility:hidden; opacity:0; color:transparent; height:0; width:0; font-size:1px; line-height:1px; max-height:0; max-width:0; }
    </style>
    <?php endif; ?>
</head>
<body style="margin: 0; padding: 0; background-color: #F9F9FB; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <?php if ($preheader !== ''): ?>
    <span class="preheader"><?php echo htmlspecialchars($preheader); ?></span>
    <?php endif; ?>

    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #F9F9FB; padding: 40px 15px;">
        <tr>
            <td align="center">
                <!-- Contenedor Principal (Max 600px) -->
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #FFFFFF; border-radius: 12px; border: 1px solid #E4E6EF; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
                    
                    <!-- Cabecera / Logo -->
                    <tr>
                        <td align="center" style="padding: 35px 30px 20px 30px; border-bottom: 1px solid #F1F1F4;">
                            <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($project_name); ?>" style="max-height: 40px; width: auto; display: block; border: 0;" />
                        </td>
                    </tr>

                    <!-- Contenido Central -->
                    <tr>
                        <td style="padding: 40px 35px 30px 35px; color: #3F4254; font-size: 15px; line-height: 1.6;">
                            
                            <?php if ($titulo !== ''): ?>
                            <h1 style="margin: 0 0 10px 0; font-size: 22px; font-weight: 700; color: #181C32; text-align: left;">
                                <?php echo htmlspecialchars($titulo); ?>
                            </h1>
                            <?php endif; ?>

                            <?php if ($subtitulo !== ''): ?>
                            <p style="margin: 0 0 25px 0; font-size: 15px; color: #7E8299; text-align: left;">
                                <?php echo htmlspecialchars($subtitulo); ?>
                            </p>
                            <?php endif; ?>

                            <div style="color: #3F4254; font-size: 15px; line-height: 1.65;">
                                <?php echo $cuerpo; ?>
                            </div>

                            <?php if ($boton_texto !== '' && $boton_url !== ''): ?>
                            <div style="margin: 35px 0 25px 0; text-align: center;">
                                <a href="<?php echo htmlspecialchars($boton_url); ?>" target="_blank" style="display: inline-block; background-color: <?php echo htmlspecialchars($primary_color); ?>; color: #FFFFFF; font-size: 15px; font-weight: 600; text-decoration: none; padding: 13px 28px; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.12);">
                                    <?php echo htmlspecialchars($boton_texto); ?>
                                </a>
                            </div>
                            <?php endif; ?>

                        </td>
                    </tr>

                    <!-- Pie de Página / Footer -->
                    <tr>
                        <td style="padding: 25px 35px; background-color: #F8F9FA; border-top: 1px solid #F1F1F4; text-align: center; color: #A1A5B7; font-size: 12px; line-height: 1.5;">
                            <?php if ($footer !== ''): ?>
                            <p style="margin: 0 0 8px 0;"><?php echo htmlspecialchars($footer); ?></p>
                            <?php endif; ?>
                            <p style="margin: 0; color: #B5B5C3;">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($project_name); ?>. All rights reserved.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}

