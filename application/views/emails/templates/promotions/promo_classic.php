<?php
$email_subject = isset($email_subject) ? (string)$email_subject : 'Promoción';
$email_title = isset($email_title) ? (string)$email_title : $email_subject;
$email_preheader = isset($email_preheader) ? (string)$email_preheader : $email_subject;
$email_logo_url = isset($email_logo_url) ? (string)$email_logo_url : base_url('assets/media/logos/default-dark.svg');
$email_body_html = isset($email_body_html) ? (string)$email_body_html : '';
$email_footer = isset($email_footer) ? (string)$email_footer : 'Todos los derechos reservados.';
$recipient_name = isset($recipient_name) ? (string)$recipient_name : 'Usuario';
$app_name = isset($app_name) ? (string)$app_name : 'Mi Sistema';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($email_title, ENT_QUOTES, 'UTF-8'); ?></title>
</head>
<body style="margin:0; padding:0; background-color:#d5d9e2; font-family:Arial,Helvetica,sans-serif; color:#2f3044;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;"><?php echo htmlspecialchars($email_preheader, ENT_QUOTES, 'UTF-8'); ?></div>
    <div style="background-color:#ffffff; padding:45px 0 34px 0; border-radius:24px; margin:40px auto; max-width:600px;">
        <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
            <tbody>
                <tr>
                    <td align="center" style="padding-bottom:10px;">
                        <div style="text-align:center; margin:0 40px 34px 40px;">
                            <div style="margin-bottom:10px;"><img alt="Logo" src="<?php echo htmlspecialchars($email_logo_url, ENT_QUOTES, 'UTF-8'); ?>" style="max-height:35px; max-width:180px;" /></div>
                            <div style="margin-bottom:15px; font-size:52px; line-height:1;">📣</div>
                            <p style="margin-bottom:9px; color:#181c32; font-size:22px; font-weight:700;">Hola <?php echo htmlspecialchars($recipient_name, ENT_QUOTES, 'UTF-8'); ?>, tenemos novedades para ti.</p>
                            <p style="margin-bottom:0; color:#7e8299; font-size:14px;">Campaña promocional enviada desde <?php echo htmlspecialchars($app_name, ENT_QUOTES, 'UTF-8'); ?>.</p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 40px 35px 40px;">
                        <div style="background:#f9f9f9; border-radius:12px; padding:32px 28px; color:#3f4254; font-size:14px; line-height:1.7;">
                            <?php echo $email_body_html !== '' ? $email_body_html : '<p style="margin:0;">Sin contenido.</p>'; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="font-size:13px; text-align:center; padding:0 16px 10px 16px; font-weight:500; color:#a1a5b7;">
                        <p style="color:#181c32; font-size:16px; font-weight:600; margin-bottom:9px;">Gracias por seguir con nosotros</p>
                        <p style="margin-bottom:0;"><?php echo htmlspecialchars($email_footer, ENT_QUOTES, 'UTF-8'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>