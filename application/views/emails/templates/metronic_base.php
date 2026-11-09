<?php
$email_title = isset($email_title) ? (string)$email_title : 'Notificación del sistema';
$email_preheader = isset($email_preheader) ? (string)$email_preheader : '';
$email_heading = isset($email_heading) ? (string)$email_heading : $email_title;
$email_body_html = isset($email_body_html) ? (string)$email_body_html : '';
$email_footer = isset($email_footer) ? (string)$email_footer : 'Este mensaje fue generado automáticamente por el sistema.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($email_title, ENT_QUOTES, 'UTF-8'); ?></title>
</head>
<body style="margin:0; padding:0; background-color:#f5f8fa; font-family:Arial, Helvetica, sans-serif; color:#181c32;">
    <?php if ($email_preheader !== ''): ?>
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">
        <?php echo htmlspecialchars($email_preheader, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php endif; ?>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f5f8fa;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:640px; background-color:#ffffff; border-radius:16px; overflow:hidden;">
                    <tr>
                        <td style="background:linear-gradient(135deg, #009ef7 0%, #7239ea 100%); padding:32px 40px; color:#ffffff;">
                            <div style="font-size:13px; letter-spacing:0.12em; text-transform:uppercase; opacity:0.85; margin-bottom:10px;">Correo del sistema</div>
                            <div style="font-size:28px; line-height:1.2; font-weight:700;">
                                <?php echo htmlspecialchars($email_heading, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:40px; font-size:15px; line-height:1.7; color:#3f4254;">
                            <?php echo $email_body_html !== '' ? $email_body_html : '<p style="margin:0;">Contenido del correo no disponible.</p>'; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 40px 32px 40px; font-size:13px; line-height:1.6; color:#7e8299;">
                            <?php echo nl2br(htmlspecialchars($email_footer, ENT_QUOTES, 'UTF-8')); ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>