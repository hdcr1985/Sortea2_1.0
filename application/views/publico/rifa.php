<?php
$rifa_id       = (int)$rifa['rifa_id'];
$titulo        = (string)$rifa['rifa_titulo'];
$imagen_url    = !empty($rifa['rifa_imagen']) ? base_url('media/rifa/' . $rifa_id) : '';
$precio        = (float)($rifa['rifa_precio'] ?? 0);
$moneda        = (string)($rifa['rifa_moneda'] ?? 'USD');
$total         = (int)($rifa['rifa_total_numeros'] ?? 0);
$disponibles   = (int)($resumen['disponibles'] ?? 0);
$modo          = (string)($rifa['rifa_modo_asignacion'] ?? 'manual');
$max_compra    = max(1, (int)($rifa['rifa_max_por_compra'] ?? 1));
$mostrar_res   = (int)($rifa['rifa_mostrar_reservados'] ?? 1);
$fecha_sorteo  = !empty($rifa['rifa_fecha_sorteo']) ? date('d/m/Y H:i', strtotime($rifa['rifa_fecha_sorteo'])) : 'Por definir';
$metodos       = isset($metodos) && is_array($metodos) ? $metodos : [];
$imagenes      = isset($imagenes) && is_array($imagenes) ? $imagenes : [];
?>
<style>
    .rifa-descripcion img { max-width: 100%; height: auto; border-radius: .75rem; }
    .rifa-descripcion table { width: 100%; border-collapse: collapse; }
    .rifa-descripcion th, .rifa-descripcion td { border: 1px solid var(--bs-border-color); padding: .5rem; }
    .rifa-descripcion p:last-child { margin-bottom: 0; }
    .numero-chip { min-width: 58px; }
    .numero-chip:disabled { opacity: .6; }
</style>

<section class="py-8 py-lg-12">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6">
            <a href="<?php echo htmlspecialchars(base_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-light fw-semibold">
                <i class="ki-duotone ki-arrow-left fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                Volver a las rifas
            </a>
            <span class="badge <?php echo $disponibles > 0 ? 'badge-light-success' : 'badge-light-danger'; ?> fw-bold px-4 py-2">
                <?php echo $disponibles > 0 ? 'Rifa activa' : 'Agotada'; ?>
            </span>
        </div>

        <div class="row g-6">
            <div class="col-lg-7">
                <div class="card card-flush border border-gray-200 rounded-4 overflow-hidden mb-6">
                    <div class="ratio ratio-16x9 bg-light">
                        <?php if ($imagen_url !== ''): ?>
                            <img src="<?php echo htmlspecialchars($imagen_url, ENT_QUOTES, 'UTF-8'); ?>"
                                alt="<?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?>"
                                class="w-100 h-100 object-fit-cover" />
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center bg-light-primary">
                                <i class="ki-duotone ki-gift fs-5x text-primary"><span class="path1"></span><span class="path2"></span></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-6 p-lg-8">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <span class="badge badge-light-primary fw-bold px-3 py-2">Sorteo: <?php echo htmlspecialchars($fecha_sorteo, ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="badge badge-light-info fw-bold px-3 py-2">
                                <?php echo $modo === 'automatica' ? 'Asignación aleatoria' : 'Elige tus números'; ?>
                            </span>
                        </div>
                        <h1 class="fw-bold text-gray-900 fs-1 mb-4"><?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?></h1>
                        <div class="rifa-descripcion text-gray-700 fs-6">
                            <?php echo (string)($rifa['rifa_descripcion'] ?? ''); ?>
                        </div>
                        <?php if (!empty($rifa['rifa_condiciones'])): ?>
                            <div class="separator separator-dashed my-6"></div>
                            <h3 class="fw-bold text-gray-900 fs-5 mb-2">Condiciones</h3>
                            <div class="text-muted fs-7" style="white-space:pre-line;"><?php echo htmlspecialchars((string)$rifa['rifa_condiciones'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($imagenes)): ?>
                    <div class="card card-flush border border-gray-200 rounded-4">
                        <div class="card-body p-6">
                            <h3 class="fw-bold text-gray-900 fs-5 mb-4">Galería</h3>
                            <div class="row g-3">
                                <?php foreach ($imagenes as $img): ?>
                                    <div class="col-4">
                                        <a href="<?php echo htmlspecialchars((string)$img['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                            target="_blank" rel="noopener" class="d-block ratio ratio-1x1 rounded-3 overflow-hidden bg-light">
                                            <img src="<?php echo htmlspecialchars((string)$img['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                                alt="Imagen de <?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?>"
                                                class="w-100 h-100 object-fit-cover" loading="lazy" />
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-5">
                <div class="card card-flush border border-gray-200 rounded-4 position-sticky" style="top:1rem;">
                    <div class="card-body p-6 p-lg-8">
                        <div class="d-flex align-items-center justify-content-between gap-3 mb-5">
                            <div class="d-flex flex-column">
                                <span class="text-muted fs-8">Precio por número</span>
                                <span class="fw-bold text-gray-900 fs-1">
                                    <?php echo number_format($precio, 2, '.', ','); ?>
                                    <span class="fs-5 text-primary"><?php echo htmlspecialchars($moneda, ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>
                            </div>
                            <div class="d-flex flex-column text-end">
                                <span class="text-muted fs-8">Disponibles</span>
                                <span class="fw-bold text-gray-900 fs-4"><?php echo number_format($disponibles, 0, '.', ','); ?> / <?php echo number_format($total, 0, '.', ','); ?></span>
                            </div>
                        </div>

                        <?php if ($precio <= 0): ?>
                            <div class="alert bg-light-info border border-info border-dashed mb-0">
                                <div class="text-gray-700 fs-7">Esta rifa no tiene un precio de ticket disponible.</div>
                            </div>
                        <?php elseif (empty($metodos)): ?>
                            <div class="alert bg-light-warning border border-warning border-dashed mb-0">
                                <div class="text-gray-700 fs-7">El organizador aún no tiene métodos de pago disponibles. Intenta más tarde.</div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger d-none mb-5 rounded-3" id="checkout_error"></div>

                            <form id="checkout_form" novalidate="novalidate">
                                <input type="hidden" name="<?php echo htmlspecialchars($csrf_name, ENT_QUOTES, 'UTF-8'); ?>"
                                    value="<?php echo htmlspecialchars($csrf_hash, ENT_QUOTES, 'UTF-8'); ?>" />
                                <input type="hidden" name="checkout_token"
                                    value="<?php echo htmlspecialchars($checkout_token ?? '', ENT_QUOTES, 'UTF-8'); ?>" />

                                <div class="mb-5">
                                    <label class="form-label required fw-semibold fs-7 text-gray-800" for="checkout_metodo">Método de pago</label>
                                    <select class="form-select form-select-solid" name="orgmet_id" id="checkout_metodo" required>
                                        <option value="">Selecciona un método</option>
                                        <?php foreach ($metodos as $metodo): ?>
                                            <option value="<?php echo (int)$metodo['orgmet_id']; ?>"
                                                data-icono="<?php echo htmlspecialchars((string)($metodo['icono'] ?? 'ki-bill'), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-imagen="<?php echo htmlspecialchars((string)($metodo['imagen'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars((string)$metodo['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="d-flex align-items-center gap-3 mt-3 d-none" id="checkout_metodo_preview">
                                        <div class="symbol symbol-45px symbol-circle bg-light-primary flex-shrink-0">
                                            <span class="symbol-label" id="checkout_metodo_preview_icon"></span>
                                        </div>
                                        <span class="text-muted fs-8" id="checkout_metodo_preview_text"></span>
                                    </div>
                                    <div class="text-muted fs-8 mt-1">Las instrucciones y datos se muestran al confirmar la reserva.</div>
                                </div>

                                <?php if ($modo === 'automatica'): ?>
                                    <div class="mb-5">
                                        <label class="form-label required fw-semibold fs-7 text-gray-800" for="checkout_cantidad">Cantidad de números</label>
                                        <input type="number" class="form-control form-control-solid" name="cantidad" id="checkout_cantidad"
                                            min="1" max="<?php echo $max_compra; ?>" value="1" required />
                                        <div class="text-muted fs-8 mt-1">El sistema asignará números aleatorios disponibles. Máximo <?php echo $max_compra; ?> por orden.</div>
                                    </div>
                                <?php else: ?>
                                    <div class="mb-5">
                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                            <span class="fw-semibold text-gray-800 fs-7">Selecciona tus números</span>
                                            <span class="badge badge-light-primary fw-bold" id="checkout_seleccionados">0 seleccionados</span>
                                        </div>
                                        <div class="border border-gray-200 rounded-3 p-3 bg-light" style="max-height:280px;overflow:auto;">
                                            <div class="d-flex flex-wrap gap-2" id="checkout_numeros">
                                                <span class="text-muted fs-8">Cargando números…</span>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-between gap-2 mt-2">
                                            <button type="button" class="btn btn-sm btn-light" id="checkout_prev">Anterior</button>
                                            <span class="text-muted fs-8" id="checkout_pagina">—</span>
                                            <button type="button" class="btn btn-sm btn-light" id="checkout_next">Siguiente</button>
                                        </div>
                                        <div class="text-muted fs-8 mt-1">Máximo <?php echo $max_compra; ?> números por orden.</div>
                                    </div>
                                <?php endif; ?>

                                <div class="separator separator-dashed my-5"></div>

                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label required fw-semibold fs-7 text-gray-800" for="checkout_nombre">Nombre completo</label>
                                        <input type="text" class="form-control form-control-solid" name="nombre" id="checkout_nombre"
                                            maxlength="150" autocomplete="name" required />
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label required fw-semibold fs-7 text-gray-800" for="checkout_email">Correo electrónico</label>
                                        <input type="email" class="form-control form-control-solid" name="email" id="checkout_email"
                                            maxlength="150" autocomplete="email" required />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold fs-7 text-gray-800" for="checkout_telefono">Teléfono</label>
                                        <input type="tel" class="form-control form-control-solid" name="telefono" id="checkout_telefono"
                                            maxlength="50" autocomplete="tel" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold fs-7 text-gray-800" for="checkout_documento">Documento</label>
                                        <input type="text" class="form-control form-control-solid" name="documento" id="checkout_documento"
                                            maxlength="50" />
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold fs-7 text-gray-800" for="checkout_notas">Notas (opcional)</label>
                                        <textarea class="form-control form-control-solid" name="notas" id="checkout_notas"
                                            rows="2" maxlength="500"></textarea>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary fw-bold w-100 mt-6" id="checkout_submit">
                                    <span class="indicator-label">
                                        <i class="ki-duotone ki-lock-2 fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                        Reservar números
                                    </span>
                                    <span class="indicator-progress">
                                        Procesando...
                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                    </span>
                                </button>
                                <div class="text-muted fs-8 text-center mt-3">
                                    La reserva queda pendiente hasta que el organizador confirme el pago.
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const DISPONIBILIDAD_URL = <?php echo json_encode($disponibilidad_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const COMPRAR_URL = <?php echo json_encode($comprar_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const CSRF_NAME = <?php echo json_encode($csrf_name, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const CSRF_HASH = <?php echo json_encode($csrf_hash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const MODO = <?php echo json_encode($modo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const MAX_COMPRA = <?php echo (int)$max_compra; ?>;

    const form = document.getElementById('checkout_form');
    if (!form) {
        return;
    }

    const metodoSelect = document.getElementById('checkout_metodo');
    const metodoPreview = document.getElementById('checkout_metodo_preview');
    const metodoPreviewIcon = document.getElementById('checkout_metodo_preview_icon');
    const metodoPreviewText = document.getElementById('checkout_metodo_preview_text');

    function actualizar_metodo_preview() {
        if (!metodoSelect || !metodoPreview) return;

        const option = metodoSelect.options[metodoSelect.selectedIndex];
        if (!option || !option.value) {
            metodoPreview.classList.add('d-none');
            return;
        }

        const icono = option.getAttribute('data-icono') || 'ki-bill';
        const imagen = option.getAttribute('data-imagen') || '';

        if (/^https?:\/\//i.test(imagen)) {
            metodoPreviewIcon.innerHTML = '<img src="' + escapeHtml(imagen) + '" alt="" loading="lazy" referrerpolicy="no-referrer" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" />';
            metodoPreviewText.textContent = option.textContent.trim();
        } else {
            metodoPreviewIcon.innerHTML = '<i class="ki-duotone ' + escapeHtml(icono) + ' fs-2 text-primary"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>';
            metodoPreviewText.textContent = option.textContent.trim();
        }

        metodoPreview.classList.remove('d-none');
    }

    if (metodoSelect) {
        metodoSelect.addEventListener('change', actualizar_metodo_preview);
        actualizar_metodo_preview();
    }

    const errorBox = document.getElementById('checkout_error');
    const submitBtn = document.getElementById('checkout_submit');
    const numerosWrap = document.getElementById('checkout_numeros');
    const seleccionadosBadge = document.getElementById('checkout_seleccionados');
    const paginaLabel = document.getElementById('checkout_pagina');
    const btnPrev = document.getElementById('checkout_prev');
    const btnNext = document.getElementById('checkout_next');

    let offset = 0;
    const limit = 200;
    let total = 0;
    let seleccionados = new Set();
    let cargando = false;

    function escapeHtml(valor) {
        return String(valor === null || valor === undefined ? '' : valor)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function mostrar_error(msg) {
        if (!errorBox) return;
        errorBox.textContent = msg;
        errorBox.classList.remove('d-none');
    }

    function limpiar_error() {
        if (!errorBox) return;
        errorBox.textContent = '';
        errorBox.classList.add('d-none');
    }

    function set_loading(activo) {
        if (!submitBtn) return;
        if (activo) {
            submitBtn.setAttribute('data-kt-indicator', 'on');
            submitBtn.disabled = true;
        } else {
            submitBtn.removeAttribute('data-kt-indicator');
            submitBtn.disabled = false;
        }
    }

    function actualizar_seleccionados() {
        if (seleccionadosBadge) {
            seleccionadosBadge.textContent = seleccionados.size + ' seleccionados';
        }
    }

    function estado_clase(estado) {
        if (estado === 'pagado') return 'badge-light-primary';
        if (estado === 'reservado') return 'badge-light-warning';
        if (estado === 'bloqueado') return 'badge-light-danger';
        return 'badge-light-success';
    }

    function render_numeros(data) {
        if (!numerosWrap) return;
        numerosWrap.innerHTML = '';
        total = parseInt(data.total, 10) || 0;

        if (!Array.isArray(data.items) || data.items.length === 0) {
            numerosWrap.innerHTML = '<span class="text-muted fs-8">No hay números disponibles.</span>';
        } else {
            data.items.forEach(function (item) {
                const numero = String(item.numero);
                const estado = String(item.estado || 'disponible');
                const disponible = estado === 'disponible';
                const checked = seleccionados.has(numero);

                const btn = document.createElement('label');
                btn.className = 'badge ' + estado_clase(estado) + ' fw-bold numero-chip border border-gray-200 py-2 px-3';
                btn.innerHTML =
                    '<input type="checkbox" class="form-check-input me-1" value="' + escapeHtml(numero) + '"' +
                        (disponible ? '' : ' disabled') + (checked ? ' checked' : '') + ' />' +
                    escapeHtml(numero);

                const input = btn.querySelector('input');
                if (input && disponible) {
                    input.addEventListener('change', function () {
                        if (input.checked) {
                            if (seleccionados.size >= MAX_COMPRA) {
                                input.checked = false;
                                mostrar_error('Puedes seleccionar hasta ' + MAX_COMPRA + ' números por orden.');
                                return;
                            }
                            seleccionados.add(numero);
                        } else {
                            seleccionados.delete(numero);
                        }
                        actualizar_seleccionados();
                    });
                }

                numerosWrap.appendChild(btn);
            });
        }

        if (paginaLabel) {
            const totalPaginas = total > 0 ? Math.ceil(total / limit) : 0;
            const pagina = Math.floor(offset / limit) + 1;
            paginaLabel.textContent = totalPaginas > 0 ? 'Página ' + pagina + ' de ' + totalPaginas : 'Sin números';
        }

        if (btnPrev) btnPrev.disabled = offset <= 0;
        if (btnNext) btnNext.disabled = total <= 0 || (offset + limit) >= total;
        actualizar_seleccionados();
    }

    function cargar_numeros() {
        if (cargando || !numerosWrap) return;
        cargando = true;
        numerosWrap.innerHTML = '<span class="text-muted fs-8">Cargando números…</span>';

        fetch(DISPONIBILIDAD_URL + '?offset=' + offset + '&limit=' + limit, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            cargando = false;
            if (!payload || payload.ok !== true || !payload.data) {
                numerosWrap.innerHTML = '<span class="text-danger fs-8">No se pudieron cargar los números.</span>';
                return;
            }
            render_numeros(payload.data);
        }).catch(function () {
            cargando = false;
            numerosWrap.innerHTML = '<span class="text-danger fs-8">Error de conexión.</span>';
        });
    }

    if (btnPrev) {
        btnPrev.addEventListener('click', function () {
            if (offset > 0) {
                offset = Math.max(0, offset - limit);
                cargar_numeros();
            }
        });
    }

    if (btnNext) {
        btnNext.addEventListener('click', function () {
            if ((offset + limit) < total) {
                offset += limit;
                cargar_numeros();
            }
        });
    }

    if (MODO === 'manual') {
        cargar_numeros();
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        limpiar_error();

        const metodo = document.getElementById('checkout_metodo');
        if (!metodo || !metodo.value) {
            mostrar_error('Selecciona un método de pago.');
            return;
        }

        const fd = new FormData(form);
        if (!fd.has(CSRF_NAME)) {
            fd.append(CSRF_NAME, CSRF_HASH);
        }

        if (MODO === 'manual') {
            if (seleccionados.size === 0) {
                mostrar_error('Selecciona al menos un número.');
                return;
            }
            fd.set('numeros', Array.from(seleccionados).join(','));
            fd.delete('cantidad');
        } else {
            const cantidad = parseInt(document.getElementById('checkout_cantidad').value, 10) || 0;
            if (cantidad < 1 || cantidad > MAX_COMPRA) {
                mostrar_error('Indica una cantidad entre 1 y ' + MAX_COMPRA + '.');
                return;
            }
            fd.set('cantidad', String(cantidad));
            fd.delete('numeros');
        }

        set_loading(true);

        fetch(COMPRAR_URL, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (response) {
            return response.json().then(function (payload) {
                return { status: response.status, data: payload || {} };
            });
        }).then(function (res) {
            set_loading(false);

            if (res.data && res.data.ok && res.data.orden && res.data.orden.url) {
                window.location.href = res.data.orden.url;
                return;
            }

            mostrar_error((res.data && res.data.msg) || 'No se pudo crear la reserva.');

            if (res.status === 409 && MODO === 'manual') {
                seleccionados = new Set();
                cargar_numeros();
            }
        }).catch(function () {
            set_loading(false);
            mostrar_error('Error de conexión con el servidor.');
        });
    });
});
</script>
