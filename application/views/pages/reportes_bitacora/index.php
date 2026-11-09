<div class="card card-flush shadow-sm">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="card-label fw-bold text-dark">
                Reporte General de Bitácora
                <span class="text-muted fw-semibold fs-7 d-block mt-1">
                    Consulta todas las acciones registradas en el sistema y filtra por usuario específico
                </span>
            </h3>
        </div>
        <div class="card-toolbar gap-3 flex-wrap">
            <div class="position-relative">
                <i class="ki-duotone ki-magnifier fs-3 text-muted position-absolute top-50 translate-middle-y ms-4">
                    <span class="path1"></span><span class="path2"></span>
                </i>
                <input type="text" id="filtro_reportes_bitacora" class="form-control form-control-solid w-250px ps-12" placeholder="Buscar por acción, detalle o IP" />
            </div>
            <select id="filtro_usuario_bitacora" class="form-select form-select-solid w-225px">
                <option value="">Todos los usuarios</option>
                <?php foreach ($usuarios_filtro as $usuario): ?>
                <option value="<?php echo htmlspecialchars((string)$usuario['bit_usuario']); ?>">@<?php echo htmlspecialchars((string)$usuario['bit_usuario']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-sm btn-light-primary fw-semibold px-4 py-2" id="btn_limpiar_bitacora">Limpiar</button>
        </div>
    </div>

    <div class="card-body pt-0">
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4" id="tabla_reportes_bitacora">
                <thead>
                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0 border-bottom border-gray-200">
                        <th class="min-w-250px">Usuario</th>
                        <th class="min-w-180px text-center">Acción</th>
                        <th class="min-w-320px">Detalle</th>
                        <th class="min-w-240px">Navegador</th>
                        <th class="min-w-150px">Dirección IP</th>
                        <th class="min-w-160px text-end pe-2">Fecha</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
const BASE_URL = "<?php echo base_url($url_base . '/'); ?>";
const filtroBusqueda = document.getElementById('filtro_reportes_bitacora');
const filtroUsuario = document.getElementById('filtro_usuario_bitacora');
const btnLimpiar = document.getElementById('btn_limpiar_bitacora');
let tabla = null;
let filtroTimer = null;

tabla = $('#tabla_reportes_bitacora').DataTable({
    processing: true,
    serverSide: true,
    searching: true,
    responsive: true,
    pageLength: 10,
    lengthMenu: [10, 25, 50, 100],
    order: [[5, 'desc']],
    dom: "rt<'row align-items-center mt-5'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 d-flex justify-content-md-end'p>>",
    ajax: {
        url: BASE_URL + 'tabla',
        type: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        data: function(d) {
            d.usuario = filtroUsuario.value || '';
        }
    },
    columns: [
        { data: 'usuario', orderable: true, searchable: false },
        { data: 'accion', orderable: true, searchable: false, className: 'text-center' },
        { data: 'detalle', orderable: true },
        { data: 'navegador', orderable: true },
        { data: 'ip', orderable: true },
        { data: 'fecha', orderable: true, searchable: false }
    ],
    language: {
        processing: 'Cargando bitácora...',
        search: 'Buscar:',
        lengthMenu: 'Mostrar _MENU_',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
        infoEmpty: 'No hay eventos para mostrar',
        infoFiltered: '(filtrados de _MAX_ registros)',
        zeroRecords: 'No se encontraron registros',
        emptyTable: 'No hay eventos registrados',
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

filtroUsuario.addEventListener('change', function() {
    tabla.ajax.reload();
});

btnLimpiar.addEventListener('click', function() {
    filtroBusqueda.value = '';
    filtroUsuario.value = '';
    tabla.search('').ajax.reload();
});
});
</script>