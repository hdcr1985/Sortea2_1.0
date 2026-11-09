-- ========================================================================
-- Migración: Crear tabla cab_plantillas_correo y registrar en cab_menu
-- Fecha: 2026-10-25
-- Descripción: Módulo para gestionar plantillas de correo transaccionales
-- ========================================================================

CREATE TABLE IF NOT EXISTS `cab_plantillas_correo` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plantilla 1: Bienvenida de Usuario
INSERT INTO `cab_plantillas_correo` (
    `plc_codigo`, `plc_nombre`, `plc_descripcion`, `plc_asunto`, `plc_preheader`, `plc_titulo`, `plc_subtitulo`,
    `plc_cuerpo`, `plc_boton_texto`, `plc_boton_url`, `plc_footer_texto`, `plc_variables_disponibles`
)
SELECT
    'BIENVENIDA',
    'Bienvenida de Nuevo Usuario',
    'Enviado automáticamente cuando un nuevo usuario es registrado o se da de alta en el sistema.',
    '¡Bienvenido a {empresa_nombre}! Tu cuenta está lista',
    'Te damos la bienvenida a nuestra plataforma.',
    '¡Bienvenido a {empresa_nombre}!',
    'Nos alegra darte la bienvenida a nuestra comunidad.',
    '<p>Hola <strong>{usuario_nombre}</strong>,</p><p>Tu cuenta ha sido creada exitosamente con el usuario <strong>{usuario_usuario}</strong>. Ya puedes acceder a la plataforma y disfrutar de todas las herramientas y funciones disponibles para optimizar tus operaciones.</p><p>Si tienes alguna pregunta o requieres asistencia técnica, nuestro equipo de soporte está siempre listo para ayudarte.</p>',
    'Ir al Panel Principal',
    '{enlace_accion}',
    'Este correo es generado automáticamente por {empresa_nombre}. Por favor no respondas directamente a este mensaje.',
    '{"{usuario_nombre}":"Nombre completo del usuario","{usuario_usuario}":"Nombre de usuario/login","{usuario_correo}":"Correo electrónico","{enlace_accion}":"Enlace al dashboard o inicio","{empresa_nombre}":"Nombre de la empresa","{empresa_correo}":"Correo de soporte","{empresa_telefono}":"Teléfono de contacto","{fecha_actual}":"Fecha actual (d/m/Y)","{ano_actual}":"Año actual (Y)"}'
WHERE NOT EXISTS (SELECT 1 FROM `cab_plantillas_correo` WHERE `plc_codigo` = 'BIENVENIDA');

-- Plantilla 2: Recuperación de Contraseña
INSERT INTO `cab_plantillas_correo` (
    `plc_codigo`, `plc_nombre`, `plc_descripcion`, `plc_asunto`, `plc_preheader`, `plc_titulo`, `plc_subtitulo`,
    `plc_cuerpo`, `plc_boton_texto`, `plc_boton_url`, `plc_footer_texto`, `plc_variables_disponibles`
)
SELECT
    'RECUPERAR_CLAVE',
    'Recuperación de Contraseña',
    'Enviado cuando el usuario solicita restablecer su contraseña olvidada.',
    'Restablece tu contraseña - {empresa_nombre}',
    'Instrucciones para recuperar tu acceso.',
    'Restablecimiento de Contraseña',
    'Recibimos una solicitud para cambiar tu contraseña.',
    '<p>Hola <strong>{usuario_nombre}</strong>,</p><p>Hemos recibido una solicitud para restablecer la contraseña de acceso asociada a tu cuenta (<strong>{usuario_usuario}</strong>).</p><p>Para definir una nueva contraseña segura, haz clic en el botón inferior. Este enlace tiene una validez temporal y expirará automáticamente por seguridad.</p><p><em>Si no solicitaste este cambio, puedes ignorar este mensaje; tu contraseña actual permanecerá segura y sin cambios.</em></p>',
    'Restablecer Contraseña',
    '{enlace_accion}',
    'Por tu seguridad, nunca compartas tus credenciales de acceso con terceros.',
    '{"{usuario_nombre}":"Nombre del usuario","{usuario_usuario}":"Nombre de usuario","{usuario_correo}":"Correo electrónico","{enlace_accion}":"Enlace temporal para cambiar clave","{empresa_nombre}":"Nombre de la empresa","{fecha_actual}":"Fecha actual","{ano_actual}":"Año actual"}'
WHERE NOT EXISTS (SELECT 1 FROM `cab_plantillas_correo` WHERE `plc_codigo` = 'RECUPERAR_CLAVE');

-- Plantilla 3: Verificación de Correo (Registro Público)
INSERT INTO `cab_plantillas_correo` (
    `plc_codigo`, `plc_nombre`, `plc_descripcion`, `plc_asunto`, `plc_preheader`, `plc_titulo`, `plc_subtitulo`,
    `plc_cuerpo`, `plc_boton_texto`, `plc_boton_url`, `plc_footer_texto`, `plc_variables_disponibles`
)
SELECT
    'VERIFICAR_CORREO',
    'Verificación de Correo Electrónico',
    'Enviado tras el registro público para validar la dirección de correo y activar la cuenta.',
    'Confirma tu correo para activar tu cuenta - {empresa_nombre}',
    'Confirma tu dirección de correo electrónico.',
    'Confirma tu Cuenta',
    'Solo un paso más para completar tu registro.',
    '<p>Hola <strong>{usuario_nombre}</strong>,</p><p>Gracias por registrarte en <strong>{empresa_nombre}</strong>. Para garantizar la seguridad de tu cuenta y activar tu acceso al sistema, por favor confirma tu dirección de correo haciendo clic en el siguiente botón:</p>',
    'Confirmar y Activar Cuenta',
    '{enlace_accion}',
    'Si no realizaste este registro, por favor desestima este correo electrónico.',
    '{"{usuario_nombre}":"Nombre del usuario","{usuario_usuario}":"Usuario creado","{usuario_correo}":"Correo a verificar","{enlace_accion}":"Enlace único de activación","{empresa_nombre}":"Nombre de la empresa","{fecha_actual}":"Fecha actual","{ano_actual}":"Año actual"}'
WHERE NOT EXISTS (SELECT 1 FROM `cab_plantillas_correo` WHERE `plc_codigo` = 'VERIFICAR_CORREO');

-- Plantilla 4: Código de Autenticación en Dos Factores (2FA)
INSERT INTO `cab_plantillas_correo` (
    `plc_codigo`, `plc_nombre`, `plc_descripcion`, `plc_asunto`, `plc_preheader`, `plc_titulo`, `plc_subtitulo`,
    `plc_cuerpo`, `plc_boton_texto`, `plc_boton_url`, `plc_footer_texto`, `plc_variables_disponibles`
)
SELECT
    'LOGIN_2FA',
    'Código de Seguridad 2FA',
    'Enviado durante el inicio de sesión cuando la verificación en dos pasos está requerida.',
    'Tu código de verificación de inicio de sesión: {codigo_verificacion}',
    'Código temporal de seguridad para acceder a tu cuenta.',
    'Código de Verificación',
    'Usa este código para completar tu inicio de sesión.',
    '<p>Hola <strong>{usuario_nombre}</strong>,</p><p>Se ha detectado un intento de inicio de sesión en tu cuenta. Para verificar tu identidad, introduce el siguiente código de seguridad en la pantalla de acceso:</p><div style="text-align: center; margin: 25px 0;"><span style="display: inline-block; font-size: 32px; font-weight: bold; letter-spacing: 6px; padding: 12px 24px; background: #F1F1F4; border-radius: 8px; color: #181C32;">{codigo_verificacion}</span></div><p>Este código es de un solo uso y vencerá en pocos minutos. No compartas este código con ninguna persona.</p>',
    NULL,
    NULL,
    'Si tú no intentaste iniciar sesión, te recomendamos cambiar tu contraseña inmediatamente.',
    '{"{usuario_nombre}":"Nombre del usuario","{codigo_verificacion}":"Código numérico de 6 dígitos","{usuario_usuario}":"Usuario","{empresa_nombre}":"Nombre de la empresa","{fecha_actual}":"Fecha actual","{ano_actual}":"Año actual"}'
WHERE NOT EXISTS (SELECT 1 FROM `cab_plantillas_correo` WHERE `plc_codigo` = 'LOGIN_2FA');

-- Plantilla 5: Alerta de Seguridad por Nuevo Inicio de Sesión
INSERT INTO `cab_plantillas_correo` (
    `plc_codigo`, `plc_nombre`, `plc_descripcion`, `plc_asunto`, `plc_preheader`, `plc_titulo`, `plc_subtitulo`,
    `plc_cuerpo`, `plc_boton_texto`, `plc_boton_url`, `plc_footer_texto`, `plc_variables_disponibles`
)
SELECT
    'AVISO_SEGURIDAD_IP',
    'Alerta de Inicio de Sesión',
    'Notificación de seguridad enviada al detectar un inicio de sesión desde un nuevo dispositivo o IP.',
    'Alerta de Seguridad: Nuevo inicio de sesión en tu cuenta - {empresa_nombre}',
    'Aviso de actividad reciente en tu cuenta.',
    'Nuevo Inicio de Sesión Detectado',
    'Verificamos una conexión reciente en tu cuenta.',
    '<p>Hola <strong>{usuario_nombre}</strong>,</p><p>Te informamos que se ha registrado un nuevo inicio de sesión en tu cuenta de <strong>{empresa_nombre}</strong> con los siguientes detalles:</p><ul style="line-height: 1.8; color: #3F4254;"><li><strong>Fecha y Hora:</strong> {fecha_actual}</li><li><strong>Dirección IP:</strong> {ip_direccion}</li><li><strong>Navegador / Dispositivo:</strong> {navegador}</li><li><strong>Ubicación estimada:</strong> {ubicacion}</li></ul><p>Si fuiste tú, puedes ignorar este correo con tranquilidad. Si no reconoces esta actividad, te sugerimos ingresar de inmediato y cambiar tu clave de acceso.</p>',
    'Revisar Seguridad de mi Cuenta',
    '{enlace_accion}',
    'Centro de Seguridad y Auditoría de {empresa_nombre}.',
    '{"{usuario_nombre}":"Nombre del usuario","{usuario_usuario}":"Nombre de usuario","{ip_direccion}":"IP de conexión","{navegador}":"Navegador y SO","{ubicacion}":"Ciudad y País","{enlace_accion}":"Enlace a perfil/seguridad","{empresa_nombre}":"Nombre de la empresa","{fecha_actual}":"Fecha y hora del evento","{ano_actual}":"Año actual"}'
WHERE NOT EXISTS (SELECT 1 FROM `cab_plantillas_correo` WHERE `plc_codigo` = 'AVISO_SEGURIDAD_IP');

-- Registrar módulo en cab_menu
INSERT INTO `cab_menu` (
    `men_nombre`,
    `men_descripcion`,
    `men_url`,
    `men_urlamigable`,
    `men_imgmenu`,
    `men_tipo`,
    `men_orden`,
    `sub_men_id`,
    `men_externo`,
    `men_fecha`,
    `men_hora`
)
SELECT
    'Plantillas de Correo',
    'Personalización de correos transaccionales y notificaciones del sistema',
    'PlantillasCorreo/index',
    'plantillas-correo',
    'ki-duotone ki-sms',
    0,
    995,
    0,
    '0',
    CURDATE(),
    CURTIME()
WHERE NOT EXISTS (
    SELECT 1
    FROM `cab_menu`
    WHERE TRIM(BOTH '/' FROM men_url) = 'PlantillasCorreo/index'
       OR TRIM(BOTH '/' FROM men_urlamigable) = 'plantillas-correo'
);

-- Asignar permisos automáticos al rol 1000 (Admin Master)
INSERT INTO `cab_permisologia` (`mis_id`, `mis_permiso`, `mis_menu`, `mis_tipomenu`, `mis_tipoper`, `mis_activo`)
SELECT 
    CONCAT('1000_', m.men_id),
    1000,
    m.men_id,
    m.men_tipo,
    COALESCE((SELECT per_tipo FROM cab_grupopermi WHERE per_id = 1000 LIMIT 1), 1),
    '1'
FROM `cab_menu` m
WHERE (TRIM(BOTH '/' FROM m.men_url) = 'PlantillasCorreo/index' OR TRIM(BOTH '/' FROM m.men_urlamigable) = 'plantillas-correo')
  AND NOT EXISTS (
      SELECT 1 FROM `cab_permisologia` p 
      WHERE p.mis_permiso = 1000 AND p.mis_menu = m.men_id
  );

INSERT INTO `cab_permiaccion` (`acc_id`, `acc_permiso`, `acc_consultar`, `acc_incluir`, `acc_modificar`, `acc_eliminar`, `acc_imprimir`, `acc_extra1`, `acc_extra2`)
SELECT 
    p.mis_id,
    1000,
    1, 1, 1, 1, 1, 1, 1
FROM `cab_permisologia` p
JOIN `cab_menu` m ON m.men_id = p.mis_menu
WHERE p.mis_permiso = 1000
  AND (TRIM(BOTH '/' FROM m.men_url) = 'PlantillasCorreo/index' OR TRIM(BOTH '/' FROM m.men_urlamigable) = 'plantillas-correo')
  AND NOT EXISTS (
      SELECT 1 FROM `cab_permiaccion` a WHERE a.acc_id = p.mis_id
  );

