<?php
$activo              = (bool)($activo ?? FALSE);
$disponible          = (bool)($disponible ?? FALSE);
$migracion_pendiente = (bool)($migracion_pendiente ?? FALSE);
$dias                = max(1, (int)($dias ?? 7));
$max_entradas        = max(50, (int)($max_entradas ?? 500));
$desde               = (string)($desde ?? date('Y-m-d', strtotime('-6 days')));
$hasta               = (string)($hasta ?? date('Y-m-d'));
$total_aprox         = max(0, (int)($total_aprox ?? 0));
$puede_consultar     = !$migracion_pendiente && $disponible && $activo;
$url_base            = (string)($url_base ?? 'reporte-errores');
?>
<style>
    .reporte-error-mensaje {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        max-width: 560px;
        word-break: break-word;
        white-space: pre-wrap;
    }
    .reporte-error-archivo {
        display: inline-block;
        max-width: 260px;
        word-break: break-word;
    }
    @media (max-width: 991.98px) {
        .reporte-error-mensaje { max-width: 320px; }
        .reporte-error-archivo { max-width: 200px; }
    }
</style>

<div class="card card-flush shadow-sm">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="card-label fw-bold text-gray-900">
                Reporte de Errores
                <span class="text-muted fw-semibold fs-7 d-block mt-1">
                    Consulta los eventos registrados en application/logs sin salir del sistema
                </span>
            </h3>
        </div>
        <div class="card-toolbar gap-3 flex-wrap">
            <div class="position-relative">
                <i class="ki-duotone ki-magnifier fs-3 text-muted position-absolute top-50 translate-middle-y ms-4">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                <input type="text" id="filtro_reporte_errores" class="form-control form-control-solid w-250px ps-12" maxlength="200" placeholder="Buscar en los logs" <?php echo $puede_consultar ? '' : 'disabled'; ?> />
            </div>
            <select id="filtro_nivel_reporte_errores" class="form-select form-select-solid w-200px" <?php echo $puede_consultar ? '' : 'disabled'; ?>>
                <option value="">Todos los niveles</option>
                <option value="ERROR">ERROR</option>
                <option value="DEBUG">DEBUG</option>
                <option value="INFO">INFO</option>
                <option value="WARNING">WARNING</option>
                <option value="NOTICE">NOTICE</option>
                <option value="CRITICAL">CRITICAL</option>
                <option value="ALERT">ALERT</option>
                <option value="EMERGENCY">EMERGENCY</option>
            </select>
            <input type="date" id="filtro_desde_reporte_errores" class="form-control form-control-solid w-150px" value="<?php echo htmlspecialchars($desde, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Fecha desde" <?php echo $puede_consultar ? '' : 'disabled'; ?> />
            <input type="date" id="filtro_hasta_reporte_errores" class="form-control form-control-solid w-150px" value="<?php echo htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Fecha hasta" <?php echo $puede_consultar ? '' : 'disabled'; ?> />
            <button type="button" class="btn btn-sm btn-light-primary fw-semibold px-4" id="btn_actualizar_reporte_errores" <?php echo $puede_consultar ? '' : 'disabled'; ?>>
                <i class="ki-duotone ki-arrows-circle fs-4 me-1"><span class="path1"></span><span class="path2"></span></i> Actualizar
            </button>
            <button type="button" class="btn btn-sm btn-light fw-semibold px-4" id="btn_limpiar_reporte_errores" <?php echo $puede_consultar ? '' : 'disabled'; ?>>
                <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i> Limpiar
            </button>
        </div>
    </div>

    <div class="card-body pt-0">
        <?php if ($migracion_pendiente): ?>
        <div class="alert bg-light-warning d-flex flex-column flex-sm-row p-5 mb-5 border border-warning border-dashed">
            <i class="ki-duotone ki-information-4 fs-2hx text-warning me-4 mb-5 mb-sm-0"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
            <div class="d-flex flex-column pe-0 pe-sm-10 justify-content-center">
                <h5 class="mb-1 text-warning fw-bold">Módulo aún no registrado</h5>
                <span class="fs-7 text-gray-700">El módulo aún no está registrado en el menú. Ejecuta la migración <code>database/2026-10-28_create_reporte_errores.sql</code> y recarga la página.</span>
            </div>
        </div>
        <?php elseif (!$disponible): ?>
        <div class="alert bg-light-warning d-flex flex-column flex-sm-row p-5 mb-5 border border-warning border-dashed">
            <i class="ki-duotone ki-information-4 fs-2hx text-warning me-4 mb-5 mb-sm-0"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
            <div class="d-flex flex-column pe-0 pe-sm-10 justify-content-center">
                <h5 class="mb-1 text-warning fw-bold">Logs no disponibles</h5>
                <span class="fs-7 text-gray-700">No se pudo leer <code>application/logs</code> o <code>log_date_format</code> no es compatible.</span>
            </div>
        </div>
        <?php elseif (!$activo): ?>
        <div class="alert bg-light-warning d-flex flex-column flex-sm-row p-5 mb-5 border border-warning border-dashed">
            <i class="ki-duotone ki-information-4 fs-2hx text-warning me-4 mb-5 mb-sm-0"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
            <div class="d-flex flex-column pe-0 pe-sm-10 justify-content-center">
                <h5 class="mb-1 text-warning fw-bold">Visor desactivado</h5>
                <span class="fs-7 text-gray-700">El visor está desactivado en Configuración General. <a href="<?php echo base_url('configuracion-general'); ?>" class="text-primary fw-semibold">Habilitarlo ahora</a>.</span>
            </div>
        </div>
        <?php endif; ?>

        <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
            <span class="badge badge-light-primary fw-semibold px-3 py-2">
                <i class="ki-duotone ki-information-4 fs-6 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                Últimos <?php echo (int)$dias; ?> días · máx. <?php echo number_format($max_entradas, 0, ',', '.'); ?> entradas
            </span>
            <?php if ($total_aprox > 0): ?>
            <span class="badge badge-light fw-semibold px-3 py-2">≈ <?php echo number_format($total_aprox, 0, ',', '.'); ?> registros</span>
            <?php endif; ?>
        </div>

        <div class="alert bg-light-warning border border-warning border-dashed d-flex align-items-center p-4 mb-5" role="note">
            <i class="ki-duotone ki-shield-cross fs-2 text-warning me-3"><span class="path1"></span><span class="path2"></span></i>
            <span class="fs-7 text-gray-700">Los logs pueden contener datos sensibles (rutas, consultas o credenciales). Manipula esta información con cuidado.</span>
        </div>

        <div id="aviso_reporte_errores" class="d-none"></div>

        <?php if ($puede_consultar): ?>
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4" id="tabla_reportes_errores">
                <thead>
                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                        <th class="min-w-150px">Fecha</th>
                        <th class="min-w-100px text-center">Nivel</th>
                        <th class="min-w-300px">Mensaje</th>
                        <th class="min-w-200px">Archivo</th>
                        <th class="min-w-120px text-end pe-2">Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="modal_detalle_error" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0 px-10 pt-9">
                <div class="d-flex flex-column pe-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge badge-light-secondary fw-semibold" id="detalle_error_nivel">—</span>
                        <span class="text-muted fs-7" id="detalle_error_fecha">—</span>
                    </div>
                    <h2 class="fw-bold mb-1">Detalle del error</h2>
                    <div class="text-muted fs-7 text-break" id="detalle_error_archivo">—</div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>
            <div class="modal-body pt-8 pb-9 px-10">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="badge badge-light" id="detalle_error_lineas">—</span>
                    <span class="badge badge-light-warning d-none" id="detalle_error_truncado">Fragmento truncado</span>
                </div>
                <div class="rounded border border-gray-200 bg-light p-4">
                    <pre id="detalle_error_mensaje" class="mb-0 text-gray-800 fs-7 font-monospace text-break" style="white-space: pre-wrap;"></pre>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-10 pb-9">
                <button type="button" class="btn btn-sm btn-light fw-semibold px-4" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-4 me-1"><span class="path1"></span><span class="path2"></span></i> Cerrar
                </button>
                <button type="button" class="btn btn-sm btn-primary fw-semibold px-4" id="btn_copiar_error">
                    <i class="ki-duotone ki-copy fs-4 me-1"></i> Copiar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const BASE_URL = "<?php echo base_url($url_base . '/'); ?>";
    const PUEDE_CONSULTAR = <?php echo $puede_consultar ? 'true' : 'false'; ?>;
    const DESDE_DEFECTO = "<?php echo htmlspecialchars($desde, ENT_QUOTES, 'UTF-8'); ?>";
    const HASTA_DEFECTO = "<?php echo htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8'); ?>";

    const filtroBusqueda = document.getElementById('filtro_reporte_errores');
    const filtroNivel = document.getElementById('filtro_nivel_reporte_errores');
    const filtroDesde = document.getElementById('filtro_desde_reporte_errores');
    const filtroHasta = document.getElementById('filtro_hasta_reporte_errores');
    const btnActualizar = document.getElementById('btn_actualizar_reporte_errores');
    const btnLimpiar = document.getElementById('btn_limpiar_reporte_errores');
    const contenedorAviso = document.getElementById('aviso_reporte_errores');
    const modalEl = document.getElementById('modal_detalle_error');
    const btnCopiar = document.getElementById('btn_copiar_error');

    let tabla = null;
    let filtroTimer = null;
    let modalDetalle = null;

    function crearIcono(clase, tamano) {
        const icono = document.createElement('i');
        icono.className = 'ki-duotone ' + clase + ' ' + (tamano || 'fs-2hx');
        ['path1', 'path2', 'path3'].forEach(function(path) {
            const span = document.createElement('span');
            span.className = path;
            icono.appendChild(span);
        });
        return icono;
    }

    function mostrarAviso(mensaje, tono) {
        if (!contenedorAviso || !mensaje) {
            return;
        }
        tono = ['info', 'warning', 'danger'].indexOf(tono) !== -1 ? tono : 'info';
        contenedorAviso.className = 'alert alert-dismissible bg-light-' + tono + ' d-flex align-items-center p-4 mb-5 border border-' + tono + ' border-dashed';
        contenedorAviso.textContent = '';
        contenedorAviso.appendChild(crearIcono('ki-information-4 text-' + tono, 'fs-2hx me-3'));

        const texto = document.createElement('span');
        texto.className = 'fs-7 text-gray-700';
        texto.textContent = String(mensaje);
        contenedorAviso.appendChild(texto);

        const cerrar = document.createElement('button');
        cerrar.type = 'button';
        cerrar.className = 'btn btn-icon ms-auto';
        cerrar.setAttribute('data-bs-dismiss', 'alert');
        cerrar.setAttribute('aria-label', 'Cerrar aviso');
        cerrar.appendChild(crearIcono('ki-cross text-' + tono, 'fs-1'));
        contenedorAviso.appendChild(cerrar);

        contenedorAviso.classList.remove('d-none');
    }

    function ocultarAviso() {
        if (contenedorAviso) {
            contenedorAviso.className = 'd-none';
            contenedorAviso.textContent = '';
        }
    }

    function mensajeDeError(xhr) {
        const respuesta = xhr && xhr.responseJSON ? xhr.responseJSON : null;
        if (respuesta) {
            if (typeof respuesta.msg === 'string' && respuesta.msg.trim() !== '') {
                return respuesta.msg;
            }
            if (respuesta.errores && typeof respuesta.errores === 'object') {
                const claves = Object.keys(respuesta.errores);
                if (claves.length > 0 && typeof respuesta.errores[claves[0]] === 'string') {
                    return respuesta.errores[claves[0]];
                }
            }
        }

        const estado = xhr ? xhr.status : 0;
        if (estado === 403) return 'No tienes permiso para consultar el reporte de errores.';
        if (estado === 404) return 'La entrada ya no está disponible o está fuera de la ventana de consulta.';
        if (estado === 422) return 'Los filtros enviados no son válidos. Revisa el rango de fechas y el nivel seleccionado.';
        if (estado === 503) return 'El directorio de logs no está disponible en este momento.';
        if (estado === 500) return 'Ocurrió un error interno al consultar el reporte.';
        return 'No se pudo consultar el reporte de errores.';
    }

    function alertaError(mensaje) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
                icon: 'error',
                title: 'No se pudo cargar el detalle',
                text: String(mensaje),
                confirmButtonText: 'Aceptar',
                customClass: { confirmButton: 'btn btn-primary' },
                buttonsStyling: false
            });
            return;
        }
        mostrarAviso(mensaje, 'danger');
    }

    function claseNivel(nivel) {
        switch (String(nivel || '').toUpperCase()) {
            case 'ERROR':
            case 'CRITICAL':
            case 'ALERT':
            case 'EMERGENCY':
                return 'danger';
            case 'WARNING':
            case 'NOTICE':
                return 'warning';
            case 'INFO':
                return 'info';
            case 'DEBUG':
                return 'secondary';
            default:
                return 'primary';
        }
    }

    function setTexto(id, valor) {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = valor === undefined || valor === null ? '' : String(valor);
        }
    }

    function pintarDetalle(data) {
        const nivel = String(data.nivel || 'ERROR').toUpperCase();
        const badgeNivel = document.getElementById('detalle_error_nivel');
        if (badgeNivel) {
            badgeNivel.className = 'badge badge-light-' + claseNivel(nivel) + ' fw-semibold';
            badgeNivel.textContent = nivel;
        }

        setTexto('detalle_error_fecha', data.fecha || '—');
        setTexto('detalle_error_archivo', data.archivo || '—');

        const lineas = Number(data.lineas || 0);
        setTexto('detalle_error_lineas', lineas > 0 ? lineas + (lineas === 1 ? ' línea' : ' líneas') : '—');

        const truncado = document.getElementById('detalle_error_truncado');
        if (truncado) {
            truncado.classList.toggle('d-none', !data.truncado);
        }

        setTexto('detalle_error_mensaje', data.mensaje || '');
    }

    function cargarDetalle(id) {
        if (!id) {
            return;
        }

        $.ajax({
            url: BASE_URL + 'detalle',
            type: 'POST',
            dataType: 'json',
            cache: false,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: { id: id }
        }).done(function(res) {
            if (!res || res.ok !== true || !res.data) {
                alertaError(res && res.msg ? res.msg : 'La entrada ya no está disponible.');
                return;
            }

            pintarDetalle(res.data);
            if (modalDetalle) {
                modalDetalle.show();
            }
        }).fail(function(xhr) {
            alertaError(mensajeDeError(xhr));
        });
    }

    function copiarTexto(texto) {
        if (!texto) {
            return;
        }

        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(texto).then(function() {
                mostrarResultadoCopia(true);
            }).catch(function() {
                copiaAlternativa(texto);
            });
            return;
        }

        copiaAlternativa(texto);
    }

    function copiaAlternativa(texto) {
        let copiado = false;
        try {
            const area = document.createElement('textarea');
            area.value = texto;
            area.setAttribute('readonly', 'readonly');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            copiado = document.execCommand('copy');
            document.body.removeChild(area);
        } catch (e) {
            copiado = false;
        }
        mostrarResultadoCopia(copiado);
    }

    function mostrarResultadoCopia(ok) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
                icon: ok ? 'success' : 'error',
                title: ok ? 'Mensaje copiado' : 'No se pudo copiar',
                text: ok ? 'El detalle está en el portapapeles.' : 'Selecciona el texto manualmente para copiarlo.',
                confirmButtonText: 'Aceptar',
                customClass: { confirmButton: 'btn btn-primary' },
                buttonsStyling: false
            });
            return;
        }
        mostrarAviso(ok ? 'Mensaje copiado al portapapeles.' : 'No se pudo copiar el mensaje.', ok ? 'info' : 'danger');
    }

    if (!PUEDE_CONSULTAR) {
        return;
    }

    if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.DataTable) {
        mostrarAviso('No se pudo cargar el componente de tabla. Recarga la página.', 'danger');
        return;
    }

    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        modalDetalle = bootstrap.Modal.getOrCreateInstance
            ? bootstrap.Modal.getOrCreateInstance(modalEl)
            : new bootstrap.Modal(modalEl);
    }

    tabla = $('#tabla_reportes_errores').DataTable({
        processing: true,
        serverSide: true,
        searching: true,
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        dom: "rt<'row align-items-center mt-5'<'col-sm-12 col-md-4'l><'col-sm-12 col-md-4'i><'col-sm-12 col-md-4 d-flex justify-content-md-end'p>>",
        ajax: {
            url: BASE_URL + 'tabla',
            type: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            cache: false,
            data: function(d) {
                d.desde = (filtroDesde && filtroDesde.value) ? filtroDesde.value : DESDE_DEFECTO;
                d.hasta = (filtroHasta && filtroHasta.value) ? filtroHasta.value : HASTA_DEFECTO;
                d.nivel = filtroNivel ? filtroNivel.value : '';
            },
            dataSrc: function(json) {
                if (json && json.ok === false) {
                    mostrarAviso(mensajeDeError({ responseJSON: json }), 'danger');
                    return [];
                }
                if (json && json.aviso) {
                    mostrarAviso(String(json.aviso), 'warning');
                } else {
                    ocultarAviso();
                }
                return json && Array.isArray(json.data) ? json.data : [];
            },
            error: function(xhr) {
                mostrarAviso(mensajeDeError(xhr), 'danger');
            }
        },
        columns: [
            { data: 'fecha', orderable: true, searchable: false },
            { data: 'nivel', orderable: true, searchable: false, className: 'text-center' },
            {
                data: 'mensaje',
                orderable: true,
                searchable: true,
                render: function(data, type) {
                    if (type !== 'display') {
                        return data;
                    }
                    return '<div class="reporte-error-mensaje text-gray-800 fs-7 text-break">' + String(data === null || data === undefined ? '' : data) + '</div>';
                }
            },
            {
                data: 'archivo',
                orderable: true,
                searchable: true,
                render: function(data, type) {
                    if (type !== 'display') {
                        return data;
                    }
                    return '<span class="reporte-error-archivo text-gray-700 fs-7 text-break">' + String(data === null || data === undefined ? '' : data) + '</span>';
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-end pe-2',
                render: function(data, type, row) {
                    if (type !== 'display') {
                        return '';
                    }
                    const id = row && row.id ? String(row.id) : '';
                    return '<button type="button" class="btn btn-sm btn-icon btn-light-primary shadow-xs btn-ver-detalle-error" data-bs-toggle="tooltip" data-bs-placement="top" title="Ver detalle" aria-label="Ver detalle" data-id="' + id + '">'
                        + '<i class="ki-duotone ki-eye fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>'
                        + '</button>';
                }
            }
        ],
        language: {
            processing: 'Cargando errores...',
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'No hay errores para mostrar',
            infoFiltered: '(filtrados de _MAX_ registros)',
            zeroRecords: 'No se encontraron registros',
            emptyTable: 'No hay errores registrados en la ventana consultada',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            }
        }
    });

    if (filtroBusqueda) {
        filtroBusqueda.addEventListener('input', function() {
            const valor = this.value;
            clearTimeout(filtroTimer);
            filtroTimer = setTimeout(function() {
                tabla.search(valor).draw();
            }, 250);
        });
    }

    if (filtroNivel) {
        filtroNivel.addEventListener('change', function() {
            tabla.ajax.reload();
        });
    }

    [filtroDesde, filtroHasta].forEach(function(input) {
        if (input) {
            input.addEventListener('change', function() {
                tabla.ajax.reload();
            });
        }
    });

    if (btnActualizar) {
        btnActualizar.addEventListener('click', function() {
            tabla.ajax.reload();
        });
    }

    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', function() {
            if (filtroBusqueda) filtroBusqueda.value = '';
            if (filtroNivel) filtroNivel.value = '';
            if (filtroDesde) filtroDesde.value = DESDE_DEFECTO;
            if (filtroHasta) filtroHasta.value = HASTA_DEFECTO;
            ocultarAviso();
            tabla.search('').ajax.reload();
        });
    }

    $('#tabla_reportes_errores').on('click', '.btn-ver-detalle-error', function() {
        cargarDetalle($(this).attr('data-id') || '');
    });

    if (btnCopiar) {
        btnCopiar.addEventListener('click', function() {
            const mensaje = document.getElementById('detalle_error_mensaje');
            copiarTexto(mensaje ? mensaje.textContent : '');
        });
    }
});
</script>
