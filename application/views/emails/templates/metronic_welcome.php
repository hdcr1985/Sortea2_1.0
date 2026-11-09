<?php
$email_title = isset($email_title) ? (string)$email_title : 'Bienvenido';
$email_preheader = isset($email_preheader) ? (string)$email_preheader : '';
$email_logo_url = isset($email_logo_url) ? (string)$email_logo_url : base_url('assets/media/logos/default-dark.svg');
$recipient_name = isset($recipient_name) ? (string)$recipient_name : 'Usuario';
$intro_lines = isset($intro_lines) && is_array($intro_lines) ? $intro_lines : [];
$steps = isset($steps) && is_array($steps) ? $steps : [];
$action_label = isset($action_label) ? (string)$action_label : 'Continuar';
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
                        <div style="text-align:center; margin:0 15px 34px 15px;">
                            <div style="margin-bottom:10px;">
                                <img alt="Logo" src="<?php echo htmlspecialchars($email_logo_url, ENT_QUOTES, 'UTF-8'); ?>" style="max-height:35px; max-width:180px;" />
                            </div>
                            <div style="margin-bottom:15px; font-size:54px; line-height:1;">👍</div>
                            <div style="font-size:14px; font-weight:500; margin-bottom:27px;">
                                <p style="margin-bottom:9px; color:#181c32; font-size:22px; font-weight:700;">Hola <?php echo htmlspecialchars($recipient_name, ENT_QUOTES, 'UTF-8'); ?>, bienvenido.</p>
                                <?php foreach ($intro_lines as $line): ?>
                                <p style="margin-bottom:4px; color:#7e8299;"><?php echo htmlspecialchars((string)$line, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endforeach; ?>
                            </div>
                            <a href="<?php echo htmlspecialchars($action_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" style="background-color:#50cd89; border-radius:6px; display:inline-block; padding:11px 19px; color:#ffffff; font-size:14px; font-weight:500; text-decoration:none;"><?php echo htmlspecialchars($action_label, ENT_QUOTES, 'UTF-8'); ?></a>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 60px 35px 60px;">
                        <p style="color:#181c32; font-size:18px; font-weight:600; margin-bottom:13px;">Qué sigue ahora</p>
                        <div style="background:#f9f9f9; border-radius:12px; padding:35px 30px;">
                            <?php foreach ($steps as $index => $step): ?>
                            <div style="display:flex; margin-bottom:<?php echo $index === array_key_last($steps) ? '0' : '18px'; ?>;">
                                <div style="display:flex; justify-content:center; align-items:center; min-width:40px; width:40px; height:40px; margin-right:13px; border-radius:50%; background:#e8fff3; color:#50cd89; font-size:16px; font-weight:700;">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div style="flex:1;">
                                    <div style="color:#181c32; font-size:14px; font-weight:600;"><?php echo htmlspecialchars((string)($step['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                    <p style="color:#5e6278; font-size:13px; font-weight:500; padding-top:3px; margin:0;"><?php echo htmlspecialchars((string)($step['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="font-size:13px; text-align:center; padding:0 10px 10px 10px; font-weight:500; color:#a1a5b7;">
                        <p style="color:#181c32; font-size:16px; font-weight:600; margin-bottom:9px;">Estamos para ayudarte</p>
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