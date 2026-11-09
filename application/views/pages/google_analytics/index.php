<?php $configuracion = $configuracion ?: []; ?>
<div class="card card-flush shadow-sm mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title d-flex align-items-center">
            <div class="symbol symbol-40px symbol-circle bg-light-primary me-3"><i class="ki-duotone ki-chart-line-up fs-2 text-primary"><span class="path1"></span><span class="path2"></span></i></div>
            <div><h3 class="fw-bold text-gray-900 mb-1">Seguimiento Google Analytics 4</h3><span class="text-muted fs-7">Configura la medición del panel autenticado.</span></div>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (!$configuracion): ?>
            <div class="alert alert-warning">La configuración no está disponible. Ejecuta <code>database/2026-10-27_create_google_analytics.sql</code>.</div>
        <?php else: ?>
        <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-5 mb-7">
            <i class="ki-duotone ki-information-5 fs-2 text-primary me-3"><span class="path1"></span><span class="path2"></span></i>
            <div class="text-gray-700">Los eventos se envían a la propiedad GA4 indicada. Las opciones de medición mejorada de Google se administran también en Google Analytics.</div>
        </div>
        <form id="ga_config_form" action="<?php echo base_url($url_base . '/guardar'); ?>" method="post">
            <input type="hidden" name="csrf_ga" value="<?php echo htmlspecialchars($csrf_ga, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="mb-7">
                <label for="measurement_id" class="form-label fw-semibold">ID de medición GA4</label>
                <input id="measurement_id" name="measurement_id" type="text" class="form-control form-control-solid" maxlength="32" placeholder="G-ABC1234567" value="<?php echo htmlspecialchars($configuracion['ga_measurement_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo !$puede_editar ? 'disabled' : ''; ?>>
                <div class="invalid-feedback" id="ga_id_error"></div>
                <div class="form-text">Disponible en Administrar → Flujos de datos de Google Analytics.</div>
            </div>
            <?php foreach ([
                'activo' => ['ga_activo', 'Activar seguimiento', 'Carga la etiqueta GA4 en el panel autenticado.'],
                'page_view' => ['ga_page_view', 'Vistas de página', 'Envía un evento page_view al cargar cada página.'],
                'outbound_click' => ['ga_outbound_click', 'Clics en enlaces externos', 'Envía un evento outbound_click al seleccionar un enlace hacia otro dominio.'],
            ] as $name => $item): ?>
            <div class="d-flex flex-stack border-bottom border-gray-200 py-5">
                <div class="pe-5"><label for="ga_<?php echo $name; ?>" class="fw-bold text-gray-900"><?php echo $item[1]; ?></label><div class="text-muted fs-7"><?php echo $item[2]; ?></div></div>
                <div class="form-check form-switch form-check-custom form-check-solid"><input class="form-check-input" id="ga_<?php echo $name; ?>" type="checkbox" name="<?php echo $name; ?>" value="1" <?php echo !empty($configuracion[$item[0]]) ? 'checked' : ''; ?> <?php echo !$puede_editar ? 'disabled' : ''; ?>></div>
            </div>
            <?php endforeach; ?>
            <div class="separator separator-dashed my-7"></div>
            <h4 class="text-gray-900">Reporte de métricas</h4>
            <?php if (!$reporte_disponible): ?>
            <div class="alert alert-warning">Para habilitar el reporte ejecuta <code>database/2026-10-27_add_ga4_reporting.sql</code>.</div>
            <?php else: ?>
            <div class="d-flex flex-stack border-bottom border-gray-200 py-5">
                <div class="pe-5"><label for="ga_reporte_activo" class="fw-bold text-gray-900">Activar reporte</label><div class="text-muted fs-7">Permite consultar métricas de la propiedad mediante la Data API.</div></div>
                <div class="form-check form-switch form-check-custom form-check-solid"><input class="form-check-input" id="ga_reporte_activo" type="checkbox" name="reporte_activo" value="1" <?php echo !empty($configuracion['ga_reporte_activo']) ? 'checked' : ''; ?> <?php echo !$puede_editar ? 'disabled' : ''; ?>></div>
            </div>
            <div class="mt-5">
                <label for="property_id" class="form-label fw-semibold">ID numérico de propiedad GA4</label>
                <input id="property_id" name="property_id" type="text" inputmode="numeric" maxlength="20" class="form-control form-control-solid" value="<?php echo htmlspecialchars($configuracion['ga_property_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo !$puede_editar ? 'disabled' : ''; ?>>
                <div class="invalid-feedback" id="ga_property_error"></div>
                <div class="form-text">Es el ID de propiedad, no el ID de medición G-…. Habilita Google Analytics Data API y concede acceso de lectura a la cuenta de servicio en GA4.</div>
                <div class="form-text">Credenciales: <?php echo $credenciales_disponibles ? 'archivo configurado y legible' : 'pendientes de configurar en el servidor'; ?>. Define <code>GOOGLE_APPLICATION_CREDENTIALS</code> en el entorno de Apache con la ruta al JSON de cuenta de servicio, fuera del directorio web. Reinicia Apache tras cambiarla.</div>
            </div>
            <?php endif; ?>
            <div id="ga_message" role="status" aria-live="polite" class="mt-5"></div>
            <?php if ($puede_editar): ?>
            <div class="d-flex justify-content-end mt-7"><button type="submit" id="ga_submit" class="btn btn-sm btn-primary"><i class="ki-duotone ki-check fs-4"><span class="path1"></span><span class="path2"></span></i> Guardar configuración</button></div>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('ga_config_form');
    if (!form) return;
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var button = document.getElementById('ga_submit'), message = document.getElementById('ga_message');
        if (!button) return;
        var id = document.getElementById('measurement_id'), error = document.getElementById('ga_id_error');
        var property = document.getElementById('property_id'), propertyError = document.getElementById('ga_property_error');
        id.classList.remove('is-invalid'); error.textContent = ''; message.textContent = '';
        if (property) { property.classList.remove('is-invalid'); propertyError.textContent = ''; }
        button.disabled = true;
        fetch(form.action, {method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (response) { return response.json().then(function (data) { return {status: response.status, data: data}; }); })
            .then(function (result) {
                var data = result.data;
                if (data.errores && data.errores.measurement_id) { id.classList.add('is-invalid'); error.textContent = data.errores.measurement_id; }
                if (property && data.errores && data.errores.property_id) { property.classList.add('is-invalid'); propertyError.textContent = data.errores.property_id; }
                message.className = 'mt-5 ' + (data.ok ? 'text-success' : 'text-danger');
                message.textContent = data.msg || (data.ok ? 'Guardado.' : 'Revisa los campos indicados.');
            })
            .catch(function () { message.className = 'mt-5 text-danger'; message.textContent = 'No se pudo completar la solicitud. Comprueba tu sesión e inténtalo nuevamente.'; })
            .finally(function () { button.disabled = false; });
    });
});
</script>
