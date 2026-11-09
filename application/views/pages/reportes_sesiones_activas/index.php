<div class="card card-flush shadow-sm">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="card-label fw-bold text-gray-900">
                Usuarios con Sesiones Activas
                <span class="text-muted fw-semibold fs-7 d-block mt-1">
                    Monitorea usuarios conectados, revisa IP/ISP/ubicación de acceso y banea para cerrar su sesión al instante
                </span>
            </h3>
        </div>
        <div class="card-toolbar gap-3 flex-wrap">
            <span class="badge badge-light-success fw-semibold px-3 py-2 d-inline-flex align-items-center">
                <span class="bullet bullet-dot bg-success me-2 animation-blink"></span> En vivo
            </span>
            <div class="position-relative">
                <i class="ki-duotone ki-magnifier fs-3 text-muted position-absolute top-50 translate-middle-y ms-4">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                <input type="text" id="filtro_sesiones_activas" class="form-control form-control-solid w-275px ps-12" placeholder="Buscar por usuario, IP, ISP o ubicación" />
            </div>
            <button type="button" class="btn btn-sm btn-light-primary fw-semibold px-4 py-2" id="btn_recargar_sesiones">Recargar</button>
        </div>
    </div>

    <div class="card-body pt-0">
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4" id="tabla_sesiones_activas">
                <thead>
                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                        <th class="min-w-250px">Usuario</th>
                        <th class="min-w-180px">Rol / Perfil</th>
                        <th class="min-w-220px">Sesión</th>
                        <th class="min-w-220px">Acceso</th>
                        <th class="min-w-120px text-center">Estado</th>
                        <th class="min-w-120px text-end pe-2">Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_detalle_sesion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0 px-10 pt-9">
                <div>
                    <div class="badge badge-light-primary fw-semibold mb-3">Detalle de conexión</div>
                    <h2 class="fw-bold mb-1">Sesión activa</h2>
                    <div class="text-muted fs-7">Información de acceso, red y riesgo asociada a la conexión actual</div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                </button>
            </div>
            <div class="modal-body pt-8 pb-9 px-10">
                <div class="row g-6">
                    <div class="col-md-6">
                        <div class="rounded border border-gray-200 p-6 h-100 bg-light">
                            <div class="d-flex align-items-center justify-content-between mb-5">
                                <div class="text-uppercase text-muted fs-8 fw-bold">Usuario</div>
                                <span class="badge badge-light-dark">Identidad</span>
                            </div>
                            <div class="fs-5 fw-bold text-gray-900" id="detalle_usuario">—</div>
                            <div class="text-muted fs-7 mt-1" id="detalle_username">—</div>
                            <div class="text-muted fs-7" id="detalle_correo">—</div>
                            <div class="separator separator-dashed my-4"></div>
                            <div class="d-flex justify-content-between align-items-start py-2"><span class="text-muted">Rol</span><span class="fw-semibold text-gray-900 text-end ms-4" id="detalle_rol">—</span></div>
                            <div class="d-flex justify-content-between align-items-start py-2"><span class="text-muted">Perfil</span><span class="fw-semibold text-gray-900 text-end ms-4" id="detalle_perfil">—</span></div>
                            <div class="d-flex justify-content-between align-items-start py-2"><span class="text-muted">Inicio</span><span class="fw-semibold text-gray-900 text-end ms-4" id="detalle_inicio">—</span></div>
                            <div class="d-flex justify-content-between align-items-start py-2"><span class="text-muted">Última actividad</span><span class="fw-semibold text-gray-900 text-end ms-4" id="detalle_actividad">—</span></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="rounded border border-gray-200 p-6 h-100 bg-light">
                            <div class="d-flex align-items-center justify-content-between mb-5">
                                <div class="text-uppercase text-muted fs-8 fw-bold">Red y dispositivo</div>
                                <span class="badge badge-light-success">Conexión</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-start py-2"><span class="text-muted">IP</span><span class="fw-semibold text-gray-900 text-end ms-4" id="detalle_ip">—</span></div>
                            <div class="d-flex justify-content-between align-items-start py-2"><span class="text-muted">Host</span><span class="fw-semibold text-gray-900 text-end ms-4" id="detalle_host">—</span></div>
                            <div class="d-flex justify-content-between align-items-start py-2"><span class="text-muted">Ubicación</span><span class="fw-semibold text-gray-900 text-end ms-4" id="detalle_ubicacion">—</span></div>
                            <div class="d-flex justify-content-between align-items-start py-2"><span class="text-muted">ISP</span><span class="fw-semibold text-gray-900 text-end ms-4" id="detalle_isp">—</span></div>
                            <div class="d-flex justify-content-between align-items-start py-2"><span class="text-muted">Organización</span><span class="fw-semibold text-gray-900 text-end ms-4" id="detalle_organizacion">—</span></div>
                            <div class="d-flex justify-content-between align-items-start py-2"><span class="text-muted">ASN</span><span class="fw-semibold text-gray-900 text-end ms-4" id="detalle_asn">—</span></div>
                            <div class="d-flex justify-content-between align-items-start py-2"><span class="text-muted">Navegador</span><span class="fw-semibold text-gray-900 text-end text-break ms-4" id="detalle_navegador">—</span></div>
                            <div class="d-flex justify-content-between align-items-start py-2"><span class="text-muted">Coordenadas</span><span class="fw-semibold text-gray-900 text-end ms-4" id="detalle_coordenadas">—</span></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="rounded border border-gray-200 p-6 bg-light">
                            <div class="d-flex align-items-center justify-content-between mb-5">
                                <div class="text-uppercase text-muted fs-8 fw-bold">Indicadores de riesgo</div>
                                <span class="badge badge-light-warning">Seguridad</span>
                            </div>
                            <div class="row g-5">
                                <div class="col-md-2 col-6"><span class="text-muted d-block fs-8 mb-1">Proxy</span><span class="fw-bold fs-6 text-gray-900" id="detalle_proxy">—</span></div>
                                <div class="col-md-2 col-6"><span class="text-muted d-block fs-8 mb-1">VPN</span><span class="fw-bold fs-6 text-gray-900" id="detalle_vpn">—</span></div>
                                <div class="col-md-2 col-6"><span class="text-muted d-block fs-8 mb-1">Tor</span><span class="fw-bold fs-6 text-gray-900" id="detalle_tor">—</span></div>
                                <div class="col-md-2 col-6"><span class="text-muted d-block fs-8 mb-1">Mobile</span><span class="fw-bold fs-6 text-gray-900" id="detalle_mobile">—</span></div>
                                <div class="col-md-2 col-6"><span class="text-muted d-block fs-8 mb-1">Crawler</span><span class="fw-bold fs-6 text-gray-900" id="detalle_crawler">—</span></div>
                                <div class="col-md-2 col-6"><span class="text-muted d-block fs-8 mb-1">Request ID</span><span class="fw-bold fs-6 text-gray-900 text-break" id="detalle_request_id">—</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
const BASE_URL = "<?php echo base_url($url_base . '/'); ?>";
const filtroBusqueda = document.getElementById('filtro_sesiones_activas');
const btnRecargar = document.getElementById('btn_recargar_sesiones');
const modalDetalleEl = document.getElementById('modal_detalle_sesion');
const modalDetalle = new bootstrap.Modal(modalDetalleEl);
let tabla = null;
let filtroTimer = null;

function mostrar_alerta(tipo, msg) {
    const titulos = {
        success: '¡Listo!',
        warning: 'Atención',
        info: 'Información',
        error: 'Error'
    };

    Swal.fire({
        icon: tipo,
        title: titulos[tipo] || 'Mensaje',
        text: msg,
        confirmButtonText: 'Aceptar',
        customClass: { confirmButton: 'btn btn-primary' },
        buttonsStyling: false,
    });
}

function setDetalle(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = value && String(value).trim() !== '' ? value : '—';
    }
}

function cargar_detalle_sesion(sessionToken) {
    const fd = new FormData();
    fd.append('session_token', sessionToken);

    fetch(BASE_URL + 'detalle', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
    })
    .then(r => r.json())
    .then(res => {
        if (!res.ok) {
            mostrar_alerta('error', res.msg || 'No se pudo cargar el detalle de la sesión.');
            return;
        }

        const d = res.data || {};
        setDetalle('detalle_usuario', d.usuario);
        setDetalle('detalle_username', d.username ? '@' + d.username : '');
        setDetalle('detalle_correo', d.correo);
        setDetalle('detalle_rol', d.rol);
        setDetalle('detalle_perfil', d.perfil);
        setDetalle('detalle_inicio', d.sesion_desde);
        setDetalle('detalle_actividad', d.ultima_actividad);
        setDetalle('detalle_ip', d.ip);
        setDetalle('detalle_host', d.host);
        setDetalle('detalle_ubicacion', d.ubicacion);
        setDetalle('detalle_isp', d.isp);
        setDetalle('detalle_organizacion', d.organizacion);
        setDetalle('detalle_asn', d.asn);
        setDetalle('detalle_navegador', d.navegador);
        setDetalle('detalle_coordenadas', d.coordenadas);
        setDetalle('detalle_proxy', d.proxy);
        setDetalle('detalle_vpn', d.vpn);
        setDetalle('detalle_tor', d.tor);
        setDetalle('detalle_mobile', d.mobile);
        setDetalle('detalle_crawler', d.crawler);
        setDetalle('detalle_request_id', d.request_id);
        modalDetalle.show();
    })
    .catch(() => mostrar_alerta('error', 'Error de conexión al consultar la sesión.'));
}

function banear_usuario(id, nombre) {
    Swal.fire({
        icon: 'warning',
        title: '¿Banear usuario?',
        text: `Se desactivará al usuario ${nombre} y se cerrarán sus sesiones activas.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, banear',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-light ms-2',
        },
        buttonsStyling: false,
    }).then(result => {
        if (!result.isConfirmed) return;

        fetch(BASE_URL + 'banear/' + id, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                mostrar_alerta('success', res.msg || 'Usuario baneado correctamente.');
                tabla.ajax.reload(null, false);
                return;
            }

            mostrar_alerta('error', res.msg || 'No se pudo banear al usuario.');
        })
        .catch(() => mostrar_alerta('error', 'Error de conexión.'));
    });
}

function cerrar_sesion_usuario(id, nombre) {
    Swal.fire({
        icon: 'warning',
        title: '¿Cerrar sesión?',
        text: `Se cerrarán las sesiones activas de ${nombre} sin desactivar su usuario.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, cerrar sesión',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-warning',
            cancelButton: 'btn btn-light ms-2',
        },
        buttonsStyling: false,
    }).then(result => {
        if (!result.isConfirmed) return;

        fetch(BASE_URL + 'cerrar_sesion/' + id, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                mostrar_alerta('success', res.msg || 'Sesión cerrada correctamente.');
                tabla.ajax.reload(null, false);
                return;
            }

            mostrar_alerta('error', res.msg || 'No se pudo cerrar la sesión del usuario.');
        })
        .catch(() => mostrar_alerta('error', 'Error de conexión.'));
    });
}

tabla = $('#tabla_sesiones_activas').DataTable({
    processing: true,
    serverSide: true,
    searching: true,
    responsive: true,
    pageLength: 10,
    lengthMenu: [10, 25, 50, 100],
    order: [[2, 'desc']],
    dom: "rt<'row align-items-center mt-5'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 d-flex justify-content-md-end'p>>",
    ajax: {
        url: BASE_URL + 'tabla',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    },
    columns: [
        { data: 'usuario', orderable: true, searchable: false },
        { data: 'rol', orderable: true, searchable: false },
        { data: 'sesion', orderable: true, searchable: false },
        { data: 'acceso', orderable: true, searchable: false },
        { data: 'estado', orderable: false, searchable: false, className: 'text-center' },
        { data: 'acciones', orderable: false, searchable: false, className: 'text-end pe-2' }
    ],
    language: {
        processing: 'Cargando sesiones activas...',
        search: 'Buscar:',
        lengthMenu: 'Mostrar _MENU_',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ sesiones activas',
        infoEmpty: 'No hay sesiones activas para mostrar',
        infoFiltered: '(filtradas de _MAX_ sesiones)',
        zeroRecords: 'No se encontraron sesiones activas',
        emptyTable: 'No hay usuarios conectados en este momento',
        paginate: {
            first: 'Primero',
            last: 'Último',
            next: 'Siguiente',
            previous: 'Anterior'
        }
    }
});

filtroBusqueda.addEventListener('input', function() {
    const valor = this.value;
    clearTimeout(filtroTimer);
    filtroTimer = setTimeout(function() {
        tabla.search(valor).draw();
    }, 250);
});

btnRecargar.addEventListener('click', function() {
    tabla.ajax.reload(null, false);
});

// Refresco automático del monitor: las altas/bajas de sesión se reflejan sin
// recargar la página, pausando mientras la pestaña está oculta o hay un modal.
const REFRESCO_MS = 10000;
let refrescoTimer = null;

function refrescar_si_visible() {
    if (document.visibilityState !== 'visible') return;
    if (document.querySelector('.modal.show') || document.querySelector('.swal2-container')) return;
    tabla.ajax.reload(null, false);
}

refrescoTimer = window.setInterval(refrescar_si_visible, REFRESCO_MS);

document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        refrescar_si_visible();
    }
});

document.getElementById('tabla_sesiones_activas').addEventListener('click', function(event) {
    const btnDetalle = event.target.closest('.btn-detalle-sesion');
    if (btnDetalle) {
        cargar_detalle_sesion(btnDetalle.dataset.session || '');
        return;
    }

    const btnCerrar = event.target.closest('.btn-cerrar-sesion');
    if (btnCerrar && !btnCerrar.disabled) {
        cerrar_sesion_usuario(btnCerrar.dataset.id, btnCerrar.dataset.nombre || 'este usuario');
        return;
    }

    const btnBanear = event.target.closest('.btn-banear-sesion');
    if (btnBanear && !btnBanear.disabled) {
        banear_usuario(btnBanear.dataset.id, btnBanear.dataset.nombre || 'este usuario');
    }
});
});
</script>