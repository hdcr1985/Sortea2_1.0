<!--begin::Javascript-->
<script>var hostUrl = "<?php echo base_url('assets/'); ?>";</script>

<!--begin::Global Javascript Bundle (obligatorio en todas las páginas)-->
<script src="<?php echo base_url('assets/plugins/global/plugins.bundle.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/scripts.bundle.js'); ?>"></script>
<!--end::Global Javascript Bundle-->

<script>
(function () {
	if (!window.bootstrap || !window.bootstrap.Tooltip) {
		return;
	}

	document.addEventListener('mouseover', function (event) {
		var trigger = event.target && event.target.closest ? event.target.closest('[data-bs-toggle="tooltip"]') : null;

		if (!trigger) {
			return;
		}

		var tooltip = window.bootstrap.Tooltip.getInstance(trigger);

		if (!tooltip) {
			tooltip = new window.bootstrap.Tooltip(trigger);
		}

		tooltip.show();
	}, true);
})();
</script>

<script>
(function() {
	function resolveElement(target) {
		if (!target) {
			return null;
		}

		if (typeof target === 'string') {
			return document.querySelector(target);
		}

		return target;
	}

	function init(root) {
		if (typeof flatpickr !== 'function') {
			return;
		}

		var scope = root || document;
		var elements = scope.querySelectorAll ? scope.querySelectorAll('input[data-app-date-picker="true"]') : [];

		elements.forEach(function(input) {
			if (input._flatpickr) {
				return;
			}

			var maxDate = input.getAttribute('data-max-date') || null;
			flatpickr(input, {
				dateFormat: 'Y-m-d',
				altInput: true,
				altFormat: 'd-m-Y',
				allowInput: false,
				disableMobile: true,
				maxDate: maxDate || undefined,
				defaultDate: input.value || null
			});
		});
	}

	function setValue(target, value) {
		var input = resolveElement(target);
		if (!input) {
			return;
		}

		if (input._flatpickr) {
			if (value) {
				input._flatpickr.setDate(value, true, 'Y-m-d');
			} else {
				input._flatpickr.clear();
			}
			return;
		}

		input.value = value || '';
	}

	window.AppDatepicker = {
		init: init,
		setValue: setValue
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			init(document);
		});
	} else {
		init(document);
	}
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const badge = document.getElementById('header_notifications_badge');
	const count = document.getElementById('header_notifications_count');
	const list = document.getElementById('header_notifications_items');
	const empty = document.getElementById('header_notifications_empty');
	const markAllButton = document.getElementById('header_notifications_mark_all');

	if (!badge || !count || !list || !empty || !markAllButton) {
		return;
	}

	const appBaseUrl = <?php echo json_encode(rtrim(base_url(), '/')); ?>;
	const feedUrl = <?php echo json_encode(base_url('notificaciones/feed')); ?>;
	const markAllUrl = <?php echo json_encode(base_url('notificaciones/marcar-todas')); ?>;
	const markReadBaseUrl = <?php echo json_encode(base_url('notificaciones/marcar-leida/')); ?>;

	function escapeHtml(value) {
		return String(value || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function notificationTypeClass(type) {
		const classes = {
			success: 'success',
			warning: 'warning',
			danger: 'danger',
			info: 'primary'
		};

		return classes[type] || 'primary';
	}

	function notificationTypeIcon(type) {
		const icons = {
			success: 'fas fa-circle-check',
			warning: 'fas fa-triangle-exclamation',
			danger: 'fas fa-circle-exclamation',
			info: 'fas fa-circle-info'
		};

		return icons[type] || 'fas fa-bell';
	}

	function resolveLink(link) {
		if (!link) {
			return '';
		}

		if (/^https?:\/\//i.test(link)) {
			return link;
		}

		if (link.charAt(0) === '/') {
			return appBaseUrl + link;
		}

		return link;
	}

	function formatDate(value) {
		if (!value) {
			return '';
		}

		const normalized = value.replace(' ', 'T');
		const parsed = new Date(normalized);
		if (Number.isNaN(parsed.getTime())) {
			return value;
		}

		return parsed.toLocaleString('es-CO', {
			year: 'numeric',
			month: '2-digit',
			day: '2-digit',
			hour: '2-digit',
			minute: '2-digit'
		});
	}

	function formatRelativeDate(value) {
		if (!value) {
			return '';
		}

		const normalized = value.replace(' ', 'T');
		const parsed = new Date(normalized);
		if (Number.isNaN(parsed.getTime())) {
			return value;
		}

		const diffMs = Date.now() - parsed.getTime();
		const diffMinutes = Math.floor(diffMs / 60000);

		if (diffMinutes < 1) {
			return 'Ahora';
		}

		if (diffMinutes < 60) {
			return diffMinutes + ' min';
		}

		const diffHours = Math.floor(diffMinutes / 60);
		if (diffHours < 24) {
			return diffHours + ' h';
		}

		const diffDays = Math.floor(diffHours / 24);
		if (diffDays < 7) {
			return diffDays + ' d';
		}

		return parsed.toLocaleDateString('es-CO', {
			day: '2-digit',
			month: 'short'
		});
	}

	function updateCounters(unread) {
		const hasUnread = Number(unread) > 0;
		badge.classList.toggle('d-none', !hasUnread);
		count.classList.toggle('d-none', !hasUnread);
		markAllButton.classList.toggle('d-none', !hasUnread);
		count.textContent = String(unread || 0) + ' nuevas';
	}

	function renderItems(items) {
		list.innerHTML = '';
		empty.classList.toggle('d-none', items.length > 0);

		items.forEach(function(item) {
			const typeClass = notificationTypeClass(item.noti_tipo);
			const typeIcon = notificationTypeIcon(item.noti_tipo);
			const titleHtml = item.noti_enlace
				? '<a href="' + escapeHtml(resolveLink(item.noti_enlace)) + '" class="fs-6 text-gray-800 text-hover-primary fw-bold js-open-notification" data-id="' + escapeHtml(item.nfu_id) + '">' + escapeHtml(item.noti_titulo) + '</a>'
				: (item.nfu_leida === '0'
					? '<a href="#" class="fs-6 text-gray-800 text-hover-primary fw-bold js-mark-read" data-id="' + escapeHtml(item.nfu_id) + '">' + escapeHtml(item.noti_titulo) + '</a>'
					: '<span class="fs-6 text-gray-800 fw-bold">' + escapeHtml(item.noti_titulo) + '</span>');
			const wrapper = document.createElement('div');
			wrapper.className = 'd-flex flex-stack py-4';
			wrapper.innerHTML = '' +
				'<div class="d-flex align-items-center me-2">' +
					'<div class="symbol symbol-35px me-4">' +
						'<span class="symbol-label bg-light-' + escapeHtml(typeClass) + '">' +
							'<i class="' + escapeHtml(typeIcon) + ' fa-lg text-' + escapeHtml(typeClass) + '"></i>' +
						'</span>' +
					'</div>' +
					'<div class="mb-0 me-2">' +
						titleHtml +
						'<div class="text-gray-500 fs-7" title="' + escapeHtml(formatDate(item.noti_created_at)) + '">' + escapeHtml(item.noti_mensaje) + '</div>' +
					'</div>' +
				'</div>' +
				'<span class="badge badge-light fs-8">' + escapeHtml(formatRelativeDate(item.noti_created_at)) + '</span>' +
				'';
			list.appendChild(wrapper);
		});
	}

	function requestJson(url, options) {
		const config = Object.assign({
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		}, options || {});

		return fetch(url, config).then(function(response) {
			if (response.status === 401) {
				window.location.href = '<?php echo base_url("login?expired=1"); ?>';
				return Promise.reject(new Error('Sesión expirada'));
			}
			return response.json();
		});
	}

	function loadNotifications() {
		requestJson(feedUrl, {
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'X-Background-Poll': '1'
			}
		})
		.then(function(payload) {
			if (!payload || payload.ok !== true) {
				return;
			}

			updateCounters(payload.unread || 0);
			renderItems(Array.isArray(payload.items) ? payload.items : []);
		})
		.catch(function() {});
	}

	list.addEventListener('click', function(event) {
		const markButton = event.target.closest('.js-mark-read');
		const openButton = event.target.closest('.js-open-notification');

		if (markButton) {
			event.preventDefault();
			requestJson(markReadBaseUrl + markButton.dataset.id, { method: 'POST' })
				.then(function() { loadNotifications(); })
				.catch(function() {});
			return;
		}

		if (openButton) {
			event.preventDefault();
			const targetUrl = openButton.getAttribute('href');
			requestJson(markReadBaseUrl + openButton.dataset.id, { method: 'POST' })
				.catch(function() {})
				.finally(function() {
					window.location.href = targetUrl;
				});
		}
	});

	markAllButton.addEventListener('click', function() {
		requestJson(markAllUrl, { method: 'POST' })
			.then(function() { loadNotifications(); })
			.catch(function() {});
	});

	loadNotifications();
	window.setInterval(loadNotifications, 60000);

	// ========================================================
	// DETECCIÓN DE ACTIVIDAD DEL USUARIO Y HEARTBEAT DE SESIÓN
	// ========================================================
	(function() {
		var lastPing = Date.now();
		var pingUrl = '<?php echo base_url("ping-sesion"); ?>';
		var PING_INTERVAL = 120000; // Enviar heartbeat como máximo cada 2 minutos mientras el usuario interactúa

		function registrarActividad() {
			var now = Date.now();
			if (now - lastPing > PING_INTERVAL) {
				lastPing = now;
				fetch(pingUrl, {
					method: 'GET',
					headers: {
						'X-Requested-With': 'XMLHttpRequest',
						'X-User-Active': '1'
					}
				}).then(function(res) {
					if (res.status === 401) {
						window.location.href = '<?php echo base_url("login?expired=1"); ?>';
					}
				}).catch(function() {});
			}
		}

		// Escuchar interacción humana real en la ventana
		['mousemove', 'keydown', 'mousedown', 'scroll', 'touchstart'].forEach(function(evt) {
			window.addEventListener(evt, registrarActividad, { passive: true });
		});
	})();
});
</script>

<?php if (!empty($page_scripts)): ?>
<!--begin::Scripts específicos de esta página-->
<?php foreach ((array)$page_scripts as $script): ?>
<script src="<?php echo base_url('assets/js/' . htmlspecialchars($script)); ?>"></script>
<?php endforeach; ?>
<!--end::Scripts específicos de esta página-->
<?php endif; ?>

<?php if (!empty($page_vendor_scripts)): ?>
<!--begin::Vendor scripts específicos de esta página-->
<?php foreach ((array)$page_vendor_scripts as $script): ?>
<script src="<?php echo base_url(htmlspecialchars($script)); ?>"></script>
<?php endforeach; ?>
<!--end::Vendor scripts específicos de esta página-->
<?php endif; ?>

<?php if (!empty($inline_js)): ?>
<script><?php echo $inline_js; ?></script>
<?php endif; ?>
<!--end::Javascript-->
