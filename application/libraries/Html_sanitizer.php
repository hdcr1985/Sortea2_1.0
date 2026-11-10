<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Html_sanitizer
 * ─────────────────────────────────────────────────────────────
 * Sanitiza HTML enriquecido para campos de usuario (descripciones).
 *
 * Estrategia:
 * - Parseo con DOMDocument en UTF-8.
 * - Lista blanca de etiquetas y atributos.
 * - Etiquetas peligrosas se eliminan con todo su contenido.
 * - Etiquetas desconocidas se desenvuelven conservando sus hijos.
 * - URLs limitadas a http, https, mailto (enlaces) y http/https (imágenes).
 * - Enlaces target="_blank" reciben rel="noopener noreferrer".
 */
class Html_sanitizer
{
    /**
     * Etiquetas permitidas en el HTML final.
     *
     * @var array
     */
    protected $permitidas = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
        'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'blockquote', 'a', 'img',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'hr', 'pre', 'code', 'figure', 'figcaption',
    ];

    /**
     * Etiquetas eliminadas por completo junto con su contenido.
     *
     * @var array
     */
    protected $eliminar_completo = [
        'script', 'style', 'iframe', 'object', 'embed',
        'form', 'input', 'button', 'select', 'textarea',
        'svg', 'math', 'link', 'meta',
        'base', 'noscript', 'template', 'applet',
        'frame', 'frameset', 'canvas', 'audio', 'video',
        'source', 'track', 'param',
    ];

    /**
     * Atributos permitidos por etiqueta.
     *
     * @var array
     */
    protected $atributos = [
        'a'     => ['href', 'title', 'target'],
        'img'   => ['src', 'alt', 'width', 'height'],
        'table' => ['colspan', 'rowspan'],
        'th'    => ['colspan', 'rowspan'],
        'td'    => ['colspan', 'rowspan'],
    ];

    /**
     * Sanitiza una cadena HTML.
     *
     * @param  string $html
     * @return string
     */
    public function sanitize($html)
    {
        if (!is_string($html)) {
            return '';
        }

        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $previo = libxml_use_internal_errors(TRUE);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $root_id = '__html_sanitizer_root__';
        $documento = '<div id="' . $root_id . '">' . $html . '</div>';

        // El preámbulo XML fuerza a DOMDocument a interpretar UTF-8 antes de
        // retirarlo del árbol. Se conservan las banderas exigidas por el
        // contrato para no introducir html/body implícitos.
        $cargado = $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $documento,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );

        if (!$cargado) {
            libxml_clear_errors();
            libxml_use_internal_errors($previo);
            return '';
        }

        // Retirar la instrucción de procesamiento usada para UTF-8.
        foreach (iterator_to_array($dom->childNodes) as $nodo) {
            if ($nodo->nodeType === XML_PI_NODE) {
                $dom->removeChild($nodo);
            }
        }

        $raiz = $dom->getElementById($root_id);
        if (!$raiz) {
            $divs = $dom->getElementsByTagName('div');
            $raiz = $divs->length > 0 ? $divs->item(0) : NULL;
        }

        if (!$raiz) {
            libxml_clear_errors();
            libxml_use_internal_errors($previo);
            return '';
        }

        $this->_procesar_nodo($raiz);

        $salida = '';
        foreach (iterator_to_array($raiz->childNodes) as $hijo) {
            $html_hijo = $dom->saveHTML($hijo);
            if (is_string($html_hijo)) {
                $salida .= $html_hijo;
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        return trim($salida);
    }

    /**
     * Procesa recursivamente los hijos de un nodo.
     *
     * @param  DOMNode $nodo
     * @return void
     */
    private function _procesar_nodo(DOMNode $nodo)
    {
        $hijos = iterator_to_array($nodo->childNodes);

        foreach ($hijos as $hijo) {
            if ($hijo->nodeType === XML_COMMENT_NODE) {
                $nodo->removeChild($hijo);
                continue;
            }

            if ($hijo->nodeType === XML_PI_NODE) {
                $nodo->removeChild($hijo);
                continue;
            }

            if ($hijo->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $etiqueta = strtolower((string)$hijo->nodeName);

            if (in_array($etiqueta, $this->eliminar_completo, TRUE)) {
                $nodo->removeChild($hijo);
                continue;
            }

            if (!in_array($etiqueta, $this->permitidas, TRUE)) {
                // Sanitizar primero el interior y luego desenvolver.
                $this->_procesar_nodo($hijo);

                while ($hijo->firstChild) {
                    $nodo->insertBefore($hijo->firstChild, $hijo);
                }

                $nodo->removeChild($hijo);
                continue;
            }

            if ($etiqueta === 'img' && !$this->_sanitizar_atributos($hijo, $etiqueta)) {
                $nodo->removeChild($hijo);
                continue;
            }

            $this->_sanitizar_atributos($hijo, $etiqueta);
            $this->_procesar_nodo($hijo);
        }
    }

    /**
     * Limpia los atributos del elemento según la lista blanca.
     *
     * @param  DOMElement $elemento
     * @param  string     $etiqueta
     * @return bool FALSE si el elemento debe eliminarse (img sin src válido).
     */
    private function _sanitizar_atributos(DOMElement $elemento, $etiqueta)
    {
        $permitidos = $this->atributos[$etiqueta] ?? [];
        $atributos = iterator_to_array($elemento->attributes);

        foreach ($atributos as $atributo) {
            $nombre = strtolower((string)$atributo->nodeName);
            $valor  = (string)$atributo->nodeValue;

            if (!in_array($nombre, $permitidos, TRUE)) {
                $elemento->removeAttributeNode($atributo);
                continue;
            }

            switch ($nombre) {
                case 'href':
                    if (!$this->_url_valida($valor, ['http', 'https', 'mailto'])) {
                        $elemento->removeAttribute('href');
                    } else {
                        $elemento->setAttribute('href', trim($valor));
                    }
                    break;

                case 'src':
                    if (!$this->_url_valida($valor, ['http', 'https'])) {
                        return FALSE;
                    }
                    $elemento->setAttribute('src', trim($valor));
                    break;

                case 'target':
                    $valor = strtolower(trim($valor));
                    if (!in_array($valor, ['_blank', '_self', '_parent', '_top'], TRUE)) {
                        $elemento->removeAttribute('target');
                    } else {
                        $elemento->setAttribute('target', $valor);
                    }
                    break;

                case 'width':
                case 'height':
                case 'colspan':
                case 'rowspan':
                    $valor = trim($valor);
                    if (!preg_match('/^\d{1,5}$/', $valor) || (int)$valor < 1) {
                        $elemento->removeAttribute($nombre);
                    } else {
                        $elemento->setAttribute($nombre, (string)(int)$valor);
                    }
                    break;

                case 'title':
                case 'alt':
                    $elemento->setAttribute($nombre, $valor);
                    break;

                default:
                    $elemento->removeAttribute($nombre);
                    break;
            }
        }

        if ($etiqueta === 'img' && !$elemento->hasAttribute('src')) {
            return FALSE;
        }

        if ($etiqueta === 'a' && strtolower($elemento->getAttribute('target')) === '_blank') {
            $elemento->setAttribute('rel', 'noopener noreferrer');
        } else {
            $elemento->removeAttribute('rel');
        }

        return TRUE;
    }

    /**
     * Valida una URL contra los esquemas permitidos.
     *
     * @param  string $url
     * @param  array  $esquemas
     * @return bool
     */
    private function _url_valida($url, array $esquemas)
    {
        $url = trim((string)$url);
        if ($url === '') {
            return FALSE;
        }

        // Rechazar URLs protocol-relative y caracteres de control.
        if (strpos($url, '//') === 0 || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return FALSE;
        }

        // Detectar esquemas peligrosos incluso con espacios/controles embebidos.
        $compacta = preg_replace('/[\x00-\x20\x7F]+/', '', $url);
        if (preg_match('/^(javascript|data|vbscript|file|blob|about|jar|chrome|resource):/i', $compacta)) {
            return FALSE;
        }

        $partes = @parse_url($url);
        if (!is_array($partes) || empty($partes['scheme'])) {
            return FALSE;
        }

        $esquema = strtolower((string)$partes['scheme']);
        if (!in_array($esquema, $esquemas, TRUE)) {
            return FALSE;
        }

        if (in_array($esquema, ['http', 'https'], TRUE)) {
            $host = (string)($partes['host'] ?? '');
            if ($host === '' || preg_match('/[\s<>"\']/', $host)) {
                return FALSE;
            }
        }

        if ($esquema === 'mailto') {
            $destino = (string)($partes['path'] ?? '');
            if ($destino === '' || preg_match('/[\s<>"\']/', $destino)) {
                return FALSE;
            }
        }

        return TRUE;
    }
}
