<?php
$email_title = isset($email_title) ? (string)$email_title : 'Confirmación';
$email_preheader = isset($email_preheader) ? (string)$email_preheader : '';
$email_logo_url = isset($email_logo_url) ? (string)$email_logo_url : base_url('assets/media/logos/default-dark.svg');
$recipient_name = isset($recipient_name) ? (string)$recipient_name : 'Usuario';
$intro_lines = isset($intro_lines) && is_array($intro_lines) ? $intro_lines : [];
$summary_title = isset($summary_title) ? (string)$summary_title : 'Resumen';
$summary_items = isset($summary_items) && is_array($summary_items) ? $summary_items : [];
$action_label = isset($action_label) ? (string)$action_label : 'Ver detalle';
$action_url = isset($action_url) ? (string)$action_url : base_url('dashboard');
$email_help_text = isset($email_help_text) ? (string)$email_help_text : '';
$email_footer = isset($email_footer) ? (string)$email_footer : 'Este mensaje fue generado automáticamente por el sistema.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($email_title, ENT_QUOTES, 'UTF-8'); ?></title>
</head>
<body style="margin:0; padding:0; background-color:#d5d9e2; font-family:Arial,Helvetica,sans-serif; color:#2f3044;">
    <?php if ($email_preheader !== ''): ?>
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">
        <?php echo htmlspecialchars($email_preheader, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php endif; ?>
    <div style="background-color:#ffffff; padding:45px 0 34px 0; border-radius:24px; margin:40px auto; max-width:600px;">
        <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
            <tbody>
                <tr>
                    <td align="center" style="text-align:center; padding-bottom:10px;">
                        <div style="text-align:center; margin:0 60px 34px 60px;">
                            <div style="margin-bottom:10px;">
                                <img alt="Logo" src="<?php echo htmlspecialchars($email_logo_url, ENT_QUOTES, 'UTF-8'); ?>" style="max-height:35px; max-width:180px;" />
                            </div>
                            <div style="margin-bottom:15px; font-size:54px; line-height:1;">✅</div>
                            <div style="font-size:14px; font-weight:500; margin-bottom:28px;">
                                <p style="margin-bottom:9px; color:#181c32; font-size:22px; font-weight:700;">Hola <?php echo htmlspecialchars($recipient_name, ENT_QUOTES, 'UTF-8'); ?>.</p>
                                <?php foreach ($intro_lines as $line): ?>
                                <p style="margin-bottom:4px; color:#7e8299;"><?php echo htmlspecialchars((string)$line, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endforeach; ?>
                            </div>
                            <div style="margin-bottom:15px; text-align:left;">
                                <h3 style="color:#181c32; font-size:18px; font-weight:600; margin-bottom:22px;"><?php echo htmlspecialchars($summary_title, ENT_QUOTES, 'UTF-8'); ?></h3>
                                <div style="padding-bottom:9px;">
                                    <?php foreach ($summary_items as $index => $item): ?>
                                    <div style="display:flex; justify-content:space-between; color:#7e8299; font-size:14px; font-weight:500; margin-bottom:8px; gap:12px;">
                                        <div><?php echo htmlspecialchars((string)($item['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div style="color:<?php echo $index === array_key_last($summary_items) ? '#50cd89' : '#7e8299'; ?>; font-weight:<?php echo $index === array_key_last($summary_items) ? '700' : '500'; ?>;"><?php echo htmlspecialchars((string)($item['value'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <a href="<?php echo htmlspecialchars($action_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" style="background-color:#50cd89; border-radius:6px; display:inline-block; padding:11px 19px; color:#ffffff; font-size:14px; font-weight:500; text-decoration:none;"><?php echo htmlspecialchars($action_label, ENT_QUOTES, 'UTF-8'); ?></a>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="font-size:13px; text-align:center; padding:0 10px 10px 10px; font-weight:500; color:#a1a5b7;">
                        <p style="color:#181c32; font-size:16px; font-weight:600; margin-bottom:9px;">Confirmación registrada</p>
                        <?php if ($email_help_text !== ''): ?>
                        <p style="margin-bottom:4px;"><?php echo htmlspecialchars($email_help_text, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        <p style="margin-bottom:0;"><?php echo htmlspecialchars($email_footer, ENT_QUOTES, 'UTF-8'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>