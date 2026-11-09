<div class="card card-flush shadow-sm mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title"><div><h3 class="fw-bold text-gray-900 mb-1">Métricas de Google Analytics</h3><span class="text-muted fs-7">Datos de la propiedad GA4 configurada.</span></div></div>
        <div class="card-toolbar gap-3">
            <label for="ga_periodo" class="text-gray-700">Período</label>
            <select id="ga_periodo" class="form-select form-select-solid w-auto"><option value="7">Últimos 7 días</option><option value="30" selected>Últimos 30 días</option><option value="90">Últimos 90 días</option></select>
            <button type="button" class="btn btn-sm btn-light-primary" id="ga_consultar"><i class="ki-duotone ki-arrows-circle fs-4"><span class="path1"></span><span class="path2"></span></i> Actualizar</button>
        </div>
    </div>
    <div class="card-body pt-0">
        <div id="ga_estado" role="status" aria-live="polite" class="text-muted mb-5">Cargando métricas…</div>
        <div class="row g-5 mb-7" id="ga_metricas" hidden>
            <?php foreach (['Usuarios activos', 'Sesiones', 'Vistas de página', 'Eventos'] as $index => $label): ?>
            <div class="col-6 col-xl-3"><div class="border border-gray-200 rounded p-5 h-100"><div class="text-muted fs-7"><?php echo $label; ?></div><div class="fw-bold text-gray-900 fs-2" id="ga_metric_<?php echo $index; ?>">—</div></div></div>
            <?php endforeach; ?>
        </div>
        <div class="row g-5" id="ga_detalles" hidden>
            <div class="col-lg-6"><h4 class="text-gray-900">Usuarios activos por día</h4><div class="table-responsive"><table class="table table-row-dashed table-row-gray-200 align-middle gy-4"><thead><tr><th>Fecha</th><th class="text-end">Usuarios</th></tr></thead><tbody id="ga_dias"></tbody></table></div></div>
            <div class="col-lg-6"><h4 class="text-gray-900">Páginas más vistas</h4><div class="table-responsive"><table class="table table-row-dashed table-row-gray-200 align-middle gy-4"><thead><tr><th>Página</th><th class="text-end">Vistas</th></tr></thead><tbody id="ga_paginas"></tbody></table></div></div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var endpoint = <?php echo json_encode(base_url($url_base . '/datos'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var button = document.getElementById('ga_consultar'), status = document.getElementById('ga_estado');
    function renderRows(id, rows, empty) {
        var body = document.getElementById(id); body.textContent = '';
        if (!rows.length) { var tr = body.insertRow(), cell = tr.insertCell(); cell.colSpan = 2; cell.textContent = empty; return; }
        rows.forEach(function (row) { var tr = body.insertRow(), label = tr.insertCell(), value = tr.insertCell(); label.textContent = (row.dimensionValues || [])[0]?.value || '—'; value.textContent = (row.metricValues || [])[0]?.value || '0'; value.className = 'text-end'; });
    }
    function load() {
        button.disabled = true; status.className = 'text-muted mb-5'; status.textContent = 'Cargando métricas…';
        document.getElementById('ga_metricas').hidden = true; document.getElementById('ga_detalles').hidden = true;
        fetch(endpoint + '?dias=' + encodeURIComponent(document.getElementById('ga_periodo').value), {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (response) { return response.json(); })
            .then(function (result) {
                if (!result.ok) throw new Error(result.msg || 'No se pudo consultar el reporte.');
                var reports = result.data.reports || [], metrics = (((reports[0] || {}).rows || [])[0] || {}).metricValues || [];
                for (var i = 0; i < 4; i++) document.getElementById('ga_metric_' + i).textContent = (metrics[i] || {}).value || '0';
                renderRows('ga_dias', (reports[1] || {}).rows || [], 'Sin datos para el período.');
                renderRows('ga_paginas', (reports[2] || {}).rows || [], 'Sin páginas para el período.');
                document.getElementById('ga_metricas').hidden = false; document.getElementById('ga_detalles').hidden = false;
                status.textContent = result.cache ? 'Resultados almacenados temporalmente.' : 'Reporte actualizado.';
            })
            .catch(function (error) { status.className = 'text-danger mb-5'; status.textContent = error.message || 'No se pudo consultar el reporte.'; })
            .finally(function () { button.disabled = false; });
    }
    button.addEventListener('click', load); load();
});
</script>
