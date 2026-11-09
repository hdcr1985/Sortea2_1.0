<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$csrf_name = $this->security->get_csrf_token_name();
$csrf_hash = $this->security->get_csrf_hash();
?>
<meta name="csrf-name" content="<?php echo htmlspecialchars($csrf_name, ENT_QUOTES, 'UTF-8'); ?>" />
<meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_hash, ENT_QUOTES, 'UTF-8'); ?>" />
<script>
(function () {
    'use strict';

    var nameMeta = document.querySelector('meta[name="csrf-name"]');
    var tokenMeta = document.querySelector('meta[name="csrf-token"]');
    if (!nameMeta || !tokenMeta) {
        return;
    }

    var csrfName = nameMeta.getAttribute('content') || '';
    var csrfToken = tokenMeta.getAttribute('content') || '';
    if (!csrfName || !csrfToken) {
        return;
    }

    function sameOrigin(url) {
        try {
            return new URL(url || window.location.href, window.location.href).origin === window.location.origin;
        } catch (e) {
            return true;
        }
    }

    function appendToken(body) {
        if (body instanceof FormData) {
            if (!body.has(csrfName)) {
                body.append(csrfName, csrfToken);
            }
            return body;
        }

        if (body instanceof URLSearchParams) {
            if (!body.has(csrfName)) {
                body.append(csrfName, csrfToken);
            }
            return body;
        }

        if (typeof body === 'string' && body !== '') {
            var trimmed = body.trim();
            if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
                /* cuerpo JSON; se deja intacto para no corromper la estructura */
                return body;
            }
            try {
                var params = new URLSearchParams(body);
                if (!params.has(csrfName)) {
                    params.append(csrfName, csrfToken);
                    return params.toString();
                }
            } catch (e) {
                /* cuerpo no convertible; se deja intacto */
            }
        }

        return body;
    }

    if (window.fetch) {
        var originalFetch = window.fetch;
        window.fetch = function (input, init) {
            init = init || {};
            var method = String(init.method || (input && input.method) || 'GET').toUpperCase();
            var url = typeof input === 'string' ? input : ((input && input.url) || '');

            if (method === 'POST' && sameOrigin(url)) {
                if (init.body === undefined || init.body === null) {
                    var fd = new FormData();
                    fd.append(csrfName, csrfToken);
                    init.body = fd;
                } else {
                    init.body = appendToken(init.body);
                }
            }

            return originalFetch.call(this, input, init);
        };
    }

    var originalOpen = XMLHttpRequest.prototype.open;
    var originalSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (method, url) {
        this.__csrfMethod = String(method || 'GET').toUpperCase();
        this.__csrfUrl = url;
        return originalOpen.apply(this, arguments);
    };

    XMLHttpRequest.prototype.send = function (body) {
        if (this.__csrfMethod === 'POST' && sameOrigin(this.__csrfUrl)) {
            if (body === undefined || body === null) {
                var params = new URLSearchParams();
                params.append(csrfName, csrfToken);
                body = params;
            } else {
                body = appendToken(body);
            }
        }
        return originalSend.call(this, body);
    };

    function ensureFormToken(form) {
        if (!form || String(form.method || '').toLowerCase() !== 'post') {
            return;
        }
        var input = form.querySelector('input[name="' + csrfName + '"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = csrfName;
            form.appendChild(input);
        }
        input.value = csrfToken;
    }

    document.addEventListener('submit', function (event) {
        ensureFormToken(event.target);
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('form').forEach(ensureFormToken);
        });
    } else {
        document.querySelectorAll('form').forEach(ensureFormToken);
    }
})();
</script>
