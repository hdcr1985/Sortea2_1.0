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
    <div style="background-color:#ffffff; padding:0 0 34px 0; border-radius:24px; margin:40px auto; max-width:600px; overflow:hidden;">
        <div style="background:linear-gradient(135deg, #009ef7 0%, #7239ea 100%); padding:36px 40px; text-align:center; color:#ffffff;">
            <div style="margin-bottom:12px;"><img alt="Logo" src="<?php echo htmlspecialchars($email_logo_url, ENT_QUOTES, 'UTF-8'); ?>" style="max-height:35px; max-width:180px; filter:brightness(0) invert(1);" /></div>
            <div style="font-size:13px; letter-spacing:0.12em; text-transform:uppercase; opacity:0.85; margin-bottom:10px;"><?php echo htmlspecialchars($app_name, ENT_QUOTES, 'UTF-8'); ?></div>
            <div style="font-size:28px; line-height:1.2; font-weight:700; margin-bottom:8px;"><?php echo htmlspecialchars($email_subject, ENT_QUOTES, 'UTF-8'); ?></div>
            <div style="font-size:14px; opacity:0.9;">Hola <?php echo htmlspecialchars($recipient_name, ENT_QUOTES, 'UTF-8'); ?>, revisa esta comunicación especial.</div>
        </div>
        <div style="padding:36px 40px 20px 40px; color:#3f4254; font-size:15px; line-height:1.7;">
            <?php echo $email_body_html !== '' ? $email_body_html : '<p style="margin:0;">Sin contenido.</p>'; ?>
        </div>
        <div style="padding:0 40px 0 40px; font-size:13px; line-height:1.6; color:#7e8299; text-align:center;">
            <?php echo htmlspecialchars($email_footer, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    </div>
</body>
</html>