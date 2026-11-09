<?php
$CI =& get_instance();
$CI->load->model('Google_analytics_model');
$ga = $CI->Google_analytics_model->configuracion();
$ga_id = (string)($ga['ga_measurement_id'] ?? '');
if (empty($ga['ga_activo']) || !preg_match('/\AG-[A-Z0-9]{4,29}\z/', $ga_id)) return;
?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo rawurlencode($ga_id); ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', <?php echo json_encode($ga_id, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, {
    send_page_view: <?php echo !empty($ga['ga_page_view']) ? 'true' : 'false'; ?>,
    page_location: window.location.origin + window.location.pathname
});
<?php if (!empty($ga['ga_outbound_click'])): ?>
document.addEventListener('click', function (event) {
    var link = event.target.closest('a[href]');
    if (!link) return;
    try {
        var target = new URL(link.href, window.location.href);
        if ((target.protocol === 'http:' || target.protocol === 'https:') && target.hostname !== window.location.hostname) {
            gtag('event', 'outbound_click');
        }
    } catch (error) { /* Ignorar enlaces inválidos. */ }
});
<?php endif; ?>
</script>
