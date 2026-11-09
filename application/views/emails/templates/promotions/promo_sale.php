<?php
$email_subject = isset($email_subject) ? (string)$email_subject : 'Oferta especial';
$email_title = isset($email_title) ? (string)$email_title : $email_subject;
$email_preheader = isset($email_preheader) ? (string)$email_preheader : $email_subject;
$email_logo_url = isset($email_logo_url) ? (string)$email_logo_url : base_url('assets/media/logos/default-dark.svg');
$email_body_html = isset($email_body_html) ? (string)$email_body_html : '';
$email_footer = isset($email_footer) ? (string)$email_footer : '';
$recipient_name = isset($recipient_name) ? (string)$recipient_name : 'Usuario';
$app_name = isset($app_name) ? (string)$app_name : 'Mi Sistema';
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><title><?php echo htmlspecialchars($email_title, ENT_QUOTES, 'UTF-8'); ?></title></head>
<body style="margin:0;padding:24px;background:#fff6ef;font-family:Arial,Helvetica,sans-serif;color:#303044;"><table role="presentation" width="100%" style="max-width:600px;margin:auto;background:#fff;">
<tr><td align="center" style="padding:32px;background:#d95d39;color:#fff;"><img src="<?php echo htmlspecialchars($email_logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($app_name, ENT_QUOTES, 'UTF-8'); ?>" style="max-width:170px;max-height:48px;"><h1><?php echo htmlspecialchars($email_subject, ENT_QUOTES, 'UTF-8'); ?></h1><p>Hola <?php echo htmlspecialchars($recipient_name, ENT_QUOTES, 'UTF-8'); ?>, esta oferta es para ti.</p></td></tr>
<tr><td style="padding:30px;line-height:1.7;"><?php echo $email_body_html; ?></td></tr><tr><td align="center" style="padding:20px;background:#fff6ef;"><?php echo htmlspecialchars($email_footer, ENT_QUOTES, 'UTF-8'); ?></td></tr></table></body></html>
