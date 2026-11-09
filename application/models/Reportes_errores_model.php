<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Reportes_errores_model
 * ─────────────────────────────────────────────────────────────
 * Lector de solo lectura de los logs nativos de CodeIgniter 3.
 *
 * Decisiones de límites (documentadas):
 *   · TAIL_BYTES (2 MB por archivo): evita leer logs gigantes completos.
 *     Solo se inspecciona la cola del archivo, que es donde están los
 *     errores más recientes. Si la cola inicia a mitad de línea se
 *     descarta esa primera línea incompleta.
 *   · PRESUPUESTO_BYTES (8 MB de mensajes por request): acota la memoria
 *     del proceso. Al procesar archivos de más reciente a más antiguo y
 *     anteponer las entradas invertidas, el recorte conserva lo más nuevo.
 *   · MENSAJE_MAX_BYTES (8 KB por entrada): evita que un stack trace
 *     enorme sature la respuesta DataTables.
 *   · DETALLE_MAX_BYTES (64 KB por detalle): el detalle lee únicamente
 *     desde el offset solicitado, nunca el archivo completo.
 *
 * Seguridad:
 *   · No se hace include/require del archivo .php de log.
 *   · No se llama log_message() dentro del parser (evita recursión).
 *   · Los mensajes se sanean a UTF-8 válido, se normalizan las rutas
 *     físicas conocidas y se enmascaran credenciales.
 */
class Reportes_errores_model extends CI_Model
{
    const MEN_URL         = 'ReportesErrores/index';
    const MEN_URLAMIGABLE = 'reporte-errores';

    // Límites de lectura / memoria.
    const TAIL_BYTES        = 2097152; // 2 MB por archivo
    const PRESUPUESTO_BYTES = 8388608; // 8 MB de mensajes por request
    const MENSAJE_MAX_BYTES = 8192;    // 8 KB por entrada
    const DETALLE_MAX_BYTES = 65536;   // 64 KB por detalle

    // Cabecera nativa de CI3: NIVEL - Y-m-d H:i:s --> mensaje
    const CABECERA_RE = '/^([A-Z][A-Z0-9_]+)\s+-\s+(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\s+-->\s?(.*)$/';
    const ID_RE       = '/^(\d{4}-\d{2}-\d{2})\|(\d+)$/';

    // Enmascara password=..., "password":"...", Authorization: Bearer ...
    const SECRETO_RE = '/((?:password|passwd|pwd|secret|token|api_key|authorization|mail_password|db_password)["\']?\s*(?::|=>|=)\s*["\']?(?:(?:Bearer|Basic|Token)\s+)?)([^"\'\s,;&}\]]+)/i';

    /** @var array<string,array> Caché por request: "desde|hasta|max" => filas */
    private $cache = [];

    /** @var mixed FALSE = sin resolver, NULL = no disponible, string = ruta */
    private $dir_cache = FALSE;

    /** @var string|null Extensión normalizada */
    private $ext_cache = NULL;

    /** @var bool|null Disponibilidad del visor */
    private $disponible = NULL;

    // ─────────────────────────────────────────────────────────
    // API pública
    // ─────────────────────────────────────────────────────────

    /**
     * Resuelve el men_id dinámicamente (nunca hardcodeado).
     */
    public function menu_id(): int
    {
        if (!$this->db->table_exists('cab_menu')) {
            return 0;
        }

        $row = $this->db
            ->select('men_id')
            ->where(
                "(TRIM(BOTH '/' FROM men_url) = '" . self::MEN_URL . "'
                  OR TRIM(BOTH '/' FROM men_urlamigable) = '" . self::MEN_URLAMIGABLE . "')",
                NULL,
                FALSE
            )
            ->limit(1)
            ->get('cab_menu')
            ->row_array();

        return $row ? (int)$row['men_id'] : 0;
    }

    /**
     * Indica si el directorio de logs es usable y el formato de fecha es el
     * esperado por el parser. Se cachea durante el request.
     */
    public function logs_disponibles(): bool
    {
        if ($this->disponible !== NULL) {
            return $this->disponible;
        }

        // El parser solo entiende la cabecera con este formato exacto.
        if (config_item('log_date_format') !== 'Y-m-d H:i:s') {
            return $this->disponible = FALSE;
        }

        return $this->disponible = ($this->_directorio() !== NULL);
    }

    /**
     * Total de la ventana efectiva (rango + tope), sin búsqueda ni nivel.
     */
    public function count_todos(string $desde, string $hasta, int $max_entradas): int
    {
        return count($this->_cargar($desde, $hasta, $max_entradas));
    }

    /**
     * Total tras aplicar búsqueda y nivel.
     */
    public function count_filtrados(string $desde, string $hasta, int $max_entradas, string $search = '', string $nivel = ''): int
    {
        return count($this->_filtrar($this->_cargar($desde, $hasta, $max_entradas), $search, $nivel));
    }

    /**
     * Filas paginadas y ordenadas para DataTables.
     *
     * order_col: 0=fecha, 1=nivel, 2=mensaje, 3=archivo, 4=lineas.
     */
    public function get_datatable(int $start, int $length, string $desde, string $hasta, int $max_entradas, string $search = '', string $nivel = '', int $order_col = 0, string $order_dir = 'desc'): array
    {
        $items = $this->_filtrar($this->_cargar($desde, $hasta, $max_entradas), $search, $nivel);

        $campos = [
            0 => 'fecha',
            1 => 'nivel',
            2 => 'mensaje',
            3 => 'archivo',
            4 => 'lineas',
        ];
        $campo = $campos[$order_col] ?? 'fecha';
        $asc   = strtolower($order_dir) === 'asc';

        // Índice de estabilidad para desempates (la carga ya viene de más
        // reciente a más antiguo, por lo que el desempate conserva ese orden).
        $indice = 0;
        foreach ($items as &$item) {
            $item['_orden'] = $indice++;
        }
        unset($item);

        usort($items, function ($a, $b) use ($campo, $asc) {
            if ($campo === 'lineas') {
                $cmp = ((int)$a['lineas'] <=> (int)$b['lineas']);
            } else {
                $cmp = strcmp((string)$a[$campo], (string)$b[$campo]);
            }

            if ($cmp === 0) {
                return ((int)$a['_orden'] <=> (int)$b['_orden']);
            }

            return $asc ? $cmp : -$cmp;
        });

        $start  = max(0, $start);
        $length = (int)$length;
        $slice  = ($length > 0)
            ? array_slice($items, $start, $length)
            : array_slice($items, $start);

        $salida = [];
        foreach ($slice as $item) {
            unset($item['_orden']);
            $salida[] = $item;
        }

        return $salida;
    }

    /**
     * Detalle de una entrada concreta. No carga el log completo.
     * Devuelve NULL si el id no es válido, la fecha está fuera de ventana,
     * el offset no es el inicio de una cabecera válida o el archivo no existe.
     */
    public function get_detalle(string $id, string $desde, string $hasta): ?array
    {
        if (!preg_match(self::ID_RE, $id, $m)) {
            return NULL;
        }

        $fecha  = $m[1];
        $offset = (int)$m[2];

        if (!$this->_fecha_valida($fecha) || $fecha < $desde || $fecha > $hasta) {
            return NULL;
        }

        // El offset 0 es válido: es la primera entrada de un archivo que cabe
        // completo dentro del tail permitido.
        if ($offset < 0 || !$this->logs_disponibles()) {
            return NULL;
        }

        $dir = $this->_directorio();
        if ($dir === NULL) {
            return NULL;
        }

        $nombre = 'log-' . $fecha . $this->_extension();
        $ruta   = $dir . '/' . $nombre;

        if (!is_file($ruta) || !is_readable($ruta)) {
            return NULL;
        }

        $real = @realpath($ruta);
        if ($real === FALSE) {
            return NULL;
        }
        $real = str_replace('\\', '/', $real);

        // Contención: el archivo real debe vivir dentro del directorio de logs.
        if (strpos(strtolower($real), strtolower($dir) . '/') !== 0) {
            return NULL;
        }

        $size = @filesize($real);
        if ($size === FALSE || $size <= 0) {
            return NULL;
        }

        $inicio_tail = max(0, $size - self::TAIL_BYTES);

        // El offset debe estar dentro del archivo y dentro del tail permitido.
        if ($offset < $inicio_tail || $offset >= $size) {
            return NULL;
        }

        $fh = @fopen($real, 'rb');
        if (!$fh) {
            return NULL;
        }

        // El offset debe ser inicio de línea: el byte anterior debe ser "\n".
        if ($offset > 0) {
            if (@fseek($fh, $offset - 1) !== 0) {
                @fclose($fh);
                return NULL;
            }
            $previo = @fread($fh, 1);
            if ($previo !== "\n") {
                @fclose($fh);
                return NULL;
            }
        }

        if (@fseek($fh, $offset) !== 0) {
            @fclose($fh);
            return NULL;
        }

        $bloque = @fread($fh, self::DETALLE_MAX_BYTES);
        $completo = feof($fh);
        @fclose($fh);

        if ($bloque === FALSE || $bloque === '') {
            return NULL;
        }

        $partes = explode("\n", $bloque);

        // El bloque se cortó a mitad de línea si no llegamos al EOF y el
        // último byte no es un salto de línea.
        $sobra = (!$completo && substr($bloque, -1) !== "\n");

        // Elimina el residuo vacío que deja un salto de línea final.
        if (!$sobra && !empty($partes) && end($partes) === '') {
            array_pop($partes);
        }

        // La primera línea contiene la cabecera. Si el mensaje es tan largo
        // que no cabe en 64 KB, igualmente se conserva la cabecera y el
        // fragmento disponible (la entrada se marca como truncada).
        $primera = array_shift($partes);
        if ($primera === NULL) {
            return NULL;
        }

        // Si hay líneas de continuación y la última quedó partida, se descarta
        // para no devolver una línea incompleta.
        if ($sobra && !empty($partes)) {
            array_pop($partes);
        }

        $primera = rtrim($primera, "\r");
        if (!preg_match(self::CABECERA_RE, $primera, $h)) {
            return NULL;
        }

        $fecha_entrada = $h[2];
        if (substr($fecha_entrada, 0, 10) < $desde || substr($fecha_entrada, 0, 10) > $hasta) {
            return NULL;
        }

        $mensaje  = $h[3];
        $lineas   = 1;
        $encontro = FALSE;

        foreach ($partes as $raw) {
            $linea = rtrim($raw, "\r");

            if (preg_match(self::CABECERA_RE, $linea)) {
                $encontro = TRUE;
                break;
            }

            $mensaje .= "\n" . $linea;
            $lineas++;
        }

        return [
            'id'       => $fecha . '|' . $offset,
            'fecha'    => $fecha_entrada,
            'nivel'    => strtoupper((string)$h[1]),
            'mensaje'  => $this->_sanitizar($mensaje, 0),
            'archivo'  => $nombre,
            'lineas'   => $lineas,
            'truncado' => (!$completo && !$encontro),
        ];
    }

    // ─────────────────────────────────────────────────────────
    // Resolución de configuración
    // ─────────────────────────────────────────────────────────

    private function _directorio()
    {
        if ($this->dir_cache !== FALSE) {
            return $this->dir_cache;
        }

        $ruta = config_item('log_path');
        if (!is_string($ruta) || trim($ruta) === '') {
            $ruta = APPPATH . 'logs/';
        }

        $real = @realpath($ruta);
        if ($real === FALSE || !is_dir($real) || !is_readable($real)) {
            return $this->dir_cache = NULL;
        }

        return $this->dir_cache = rtrim(str_replace('\\', '/', $real), '/');
    }

    private function _extension()
    {
        if ($this->ext_cache !== NULL) {
            return $this->ext_cache;
        }

        $ext = config_item('log_file_extension');
        $ext = is_string($ext) ? trim($ext) : '';
        if ($ext === '') {
            $ext = '.php';
        }
        if (substr($ext, 0, 1) !== '.') {
            $ext = '.' . $ext;
        }

        return $this->ext_cache = $ext;
    }

    // ─────────────────────────────────────────────────────────
    // Carga y caché por request
    // ─────────────────────────────────────────────────────────

    private function _cargar(string $desde, string $hasta, int $max_entradas): array
    {
        $max   = max(1, $max_entradas);
        $clave = $desde . '|' . $hasta . '|' . $max;

        if (array_key_exists($clave, $this->cache)) {
            return $this->cache[$clave];
        }

        $this->cache[$clave] = [];

        if (!$this->logs_disponibles()) {
            return $this->cache[$clave];
        }

        $dir = $this->_directorio();
        if ($dir === NULL) {
            return $this->cache[$clave];
        }

        $archivos  = $this->_archivos($dir, $this->_extension(), $desde, $hasta);
        $acumulado = [];
        $bytes     = 0;
        $total     = 0;

        // Archivos de más reciente a más antiguo. Cada archivo devuelve sus
        // entradas de más reciente a más antigua; se concatenan al final del
        // acumulado para preservar el orden global descendente.
        foreach ($archivos as $archivo) {
            if ($total >= $max || $bytes >= self::PRESUPUESTO_BYTES) {
                break;
            }

            $entradas = $this->_leer_archivo($archivo, $desde, $hasta);
            $nuevas   = [];

            foreach ($entradas as $entrada) {
                if ($total + count($nuevas) >= $max) {
                    break;
                }

                $largo = strlen($entrada['mensaje']);
                if ($bytes + $largo > self::PRESUPUESTO_BYTES && !empty($nuevas)) {
                    break;
                }

                $nuevas[] = $entrada;
                $bytes += $largo;
            }

            if (empty($nuevas)) {
                continue;
            }

            $acumulado = array_merge($acumulado, $nuevas);
            $total     = count($acumulado);
        }

        return $this->cache[$clave] = $acumulado;
    }

    /**
     * Lista los archivos log-YYYY-MM-DD<ext> del rango, con validación
     * estricta de fecha y contención dentro del directorio.
     */
    private function _archivos(string $dir, string $ext, string $desde, string $hasta): array
    {
        $items = @scandir($dir);
        if (!is_array($items)) {
            return [];
        }

        $patron   = '/^log-(\d{4})-(\d{2})-(\d{2})' . preg_quote($ext, '/') . '$/';
        $dir_norm = strtolower($dir) . '/';
        $salida   = [];

        foreach ($items as $nombre) {
            if (!preg_match($patron, $nombre, $m)) {
                continue;
            }

            $anio = (int)$m[1];
            $mes  = (int)$m[2];
            $dia  = (int)$m[3];

            if (!checkdate($mes, $dia, $anio)) {
                continue;
            }

            $fecha = $m[1] . '-' . $m[2] . '-' . $m[3];
            if ($fecha < $desde || $fecha > $hasta) {
                continue;
            }

            $ruta = $dir . '/' . $nombre;
            $real = @realpath($ruta);
            if ($real === FALSE) {
                continue;
            }

            $real = str_replace('\\', '/', $real);

            // Contención case-insensitive (Windows) y separadores normalizados.
            if (strpos(strtolower($real), $dir_norm) !== 0) {
                continue;
            }

            if (!is_file($real) || !is_readable($real)) {
                continue;
            }

            $salida[] = [
                'fecha'  => $fecha,
                'nombre' => $nombre,
                'ruta'   => $real,
            ];
        }

        usort($salida, function ($a, $b) {
            return strcmp($b['fecha'], $a['fecha']);
        });

        return $salida;
    }

    /**
     * Lee la cola (máx 2 MB) de un archivo y devuelve sus entradas del rango,
     * ordenadas de más reciente a más antigua.
     */
    private function _leer_archivo(array $archivo, string $desde, string $hasta): array
    {
        $ruta          = $archivo['ruta'];
        $nombre        = $archivo['nombre'];
        $fecha_archivo = $archivo['fecha'];

        $size = @filesize($ruta);
        if ($size === FALSE || $size <= 0) {
            return [];
        }

        $inicio_tail = max(0, $size - self::TAIL_BYTES);

        $fh = @fopen($ruta, 'rb');
        if (!$fh) {
            return [];
        }

        $inicio_en_linea = TRUE;
        if ($inicio_tail > 0) {
            if (@fseek($fh, $inicio_tail - 1) === 0) {
                $previo = @fread($fh, 1);
                $inicio_en_linea = ($previo === "\n");
            } else {
                $inicio_en_linea = FALSE;
            }
        }

        if (@fseek($fh, $inicio_tail) !== 0) {
            @fclose($fh);
            return [];
        }

        $bloque = @stream_get_contents($fh, self::TAIL_BYTES);
        @fclose($fh);

        if ($bloque === FALSE || $bloque === '') {
            return [];
        }

        $partes   = explode("\n", $bloque);

        // El salto de línea final del archivo no es una línea de continuación.
        if (!empty($partes) && end($partes) === '') {
            array_pop($partes);
        }

        $cursor   = $inicio_tail;
        $entradas = [];
        $actual   = NULL;

        foreach ($partes as $i => $raw) {
            $offset = $cursor;
            $cursor += strlen($raw) + 1;

            // Si la cola arrancó a mitad de línea, descarta esa línea parcial.
            if ($i === 0 && !$inicio_en_linea) {
                continue;
            }

            $linea = rtrim($raw, "\r");

            if (preg_match(self::CABECERA_RE, $linea, $m)) {
                if ($actual !== NULL) {
                    $entradas[] = $actual;
                }

                $actual = [
                    'fecha'   => $m[2],
                    'nivel'   => strtoupper((string)$m[1]),
                    'mensaje' => $m[3],
                    'offset'  => $offset,
                ];
            } elseif ($actual !== NULL) {
                // Línea de continuación (stack trace, SQL, etc.).
                $actual['mensaje'] .= "\n" . $linea;
            }
        }

        if ($actual !== NULL) {
            $entradas[] = $actual;
        }

        $filtradas = [];

        foreach ($entradas as $entrada) {
            $fecha_entrada = substr($entrada['fecha'], 0, 10);
            if ($fecha_entrada < $desde || $fecha_entrada > $hasta) {
                continue;
            }

            $mensaje  = $this->_sanitizar($entrada['mensaje'], 0);
            $truncado = strlen($mensaje) > self::MENSAJE_MAX_BYTES;
            if ($truncado) {
                $mensaje = $this->_truncar($mensaje, self::MENSAJE_MAX_BYTES);
            }

            $filtradas[] = [
                // El id usa la fecha del archivo para poder localizarlo luego.
                'id'       => $fecha_archivo . '|' . (int)$entrada['offset'],
                'fecha'    => $entrada['fecha'],
                'nivel'    => $entrada['nivel'],
                'mensaje'  => $mensaje,
                'archivo'  => $nombre,
                'offset'   => (int)$entrada['offset'],
                'lineas'   => substr_count($mensaje, "\n") + 1,
                'truncado' => $truncado,
            ];
        }

        return array_reverse($filtradas);
    }

    // ─────────────────────────────────────────────────────────
    // Filtros
    // ─────────────────────────────────────────────────────────

    private function _filtrar(array $items, string $search, string $nivel): array
    {
        // El término puede llegar con bytes inválidos: se normaliza a UTF-8
        // antes de usarlo con mb_strtolower/mb_strpos.
        $search = $this->_utf8(trim($search));
        $nivel  = strtoupper(trim($nivel));
        $filtrar_nivel = ($nivel !== '' && $nivel !== 'ALL');

        if ($search === '' && !$filtrar_nivel) {
            return $items;
        }

        $aguja = $this->_minusculas($search);
        $salida = [];

        foreach ($items as $item) {
            if ($filtrar_nivel && $item['nivel'] !== $nivel) {
                continue;
            }

            if ($search !== '' && !$this->_contiene($item, $aguja)) {
                continue;
            }

            $salida[] = $item;
        }

        return $salida;
    }

    private function _contiene(array $item, string $aguja): bool
    {
        $aguja = $this->_utf8($aguja);

        foreach (['mensaje', 'nivel', 'fecha', 'archivo'] as $campo) {
            $pajar = $this->_minusculas((string)$item[$campo]);
            $encontrado = FALSE;

            if (function_exists('mb_strpos')) {
                try {
                    $encontrado = mb_strpos($pajar, $aguja, 0, 'UTF-8') !== FALSE;
                } catch (Throwable $e) {
                    // Fallback byte a byte si mbstring rechaza la entrada.
                    $encontrado = FALSE;
                }
            }

            if (!$encontrado && strpos($pajar, $aguja) !== FALSE) {
                $encontrado = TRUE;
            }

            if ($encontrado) {
                return TRUE;
            }
        }

        return FALSE;
    }

    private function _minusculas(string $texto): string
    {
        $texto = $this->_utf8($texto);

        if (function_exists('mb_strtolower')) {
            try {
                return mb_strtolower($texto, 'UTF-8');
            } catch (Throwable $e) {
                // Fallback seguro para entradas no UTF-8.
            }
        }

        return strtolower($texto);
    }

    private function _fecha_valida(string $fecha): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return FALSE;
        }

        $dt = DateTime::createFromFormat('!Y-m-d', $fecha);
        if ($dt === FALSE) {
            return FALSE;
        }

        $errores = DateTime::getLastErrors();
        if (is_array($errores) && (($errores['warning_count'] ?? 0) > 0 || ($errores['error_count'] ?? 0) > 0)) {
            return FALSE;
        }

        return $dt->format('Y-m-d') === $fecha;
    }

    // ─────────────────────────────────────────────────────────
    // Sanitización de mensajes
    // ─────────────────────────────────────────────────────────

    /**
     * @param string $texto   Mensaje crudo
     * @param int    $max     Tope en bytes; 0 = sin tope (detalle)
     */
    private function _sanitizar(string $texto, int $max = self::MENSAJE_MAX_BYTES): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);

        // Se normalizan separadores antes de sustituir rutas conocidas.
        $texto = str_replace('\\', '/', $texto);

        $texto = $this->_utf8($texto);
        $texto = $this->_normalizar_rutas($texto);

        $reemplazo = preg_replace(self::SECRETO_RE, '$1***', $texto);
        if (is_string($reemplazo)) {
            $texto = $reemplazo;
        }

        $texto = $this->_truncar($texto, $max);

        return $this->_utf8($texto);
    }

    /**
     * Reemplaza las bases físicas conocidas por tokens y enmascara cualquier
     * ruta absoluta de Windows restante.
     */
    private function _normalizar_rutas(string $texto): string
    {
        $bases = [
            [APPPATH,  '[APP]'],
            [BASEPATH, '[SYSTEM]'],
            [FCPATH,   '[ROOT]'],
        ];

        foreach ($bases as $par) {
            $ruta = str_replace('\\', '/', rtrim((string)$par[0], '\\/'));

            // Evita reemplazar prefijos de directorios hermanos
            // (ej: proyecto_base vs proyecto_base_metronic).
            if ($ruta === '' || $ruta === '/') {
                continue;
            }

            $patron = '#' . preg_quote($ruta, '#') . '(?![A-Za-z0-9_.-])#i';
            $nuevo  = preg_replace($patron, $par[1], $texto);
            if (is_string($nuevo)) {
                $texto = $nuevo;
            }
        }

        $generico = preg_replace('/[A-Za-z]:\/[^\s"\'<>|:]*/', '[RUTA]', $texto);

        return is_string($generico) ? $generico : $texto;
    }

    /**
     * Garantiza UTF-8 válido. Nunca devuelve secuencias inválidas.
     */
    private function _utf8(string $texto): string
    {
        if ($texto === '') {
            return '';
        }

        if (function_exists('mb_check_encoding')) {
            if (mb_check_encoding($texto, 'UTF-8')) {
                return $texto;
            }

            if (function_exists('mb_convert_encoding')) {
                $convertido = @mb_convert_encoding($texto, 'UTF-8', 'UTF-8');
                if (is_string($convertido)) {
                    return $convertido;
                }
            }
        } elseif (preg_match('//u', $texto)) {
            return $texto;
        }

        if (function_exists('iconv')) {
            $convertido = @iconv('UTF-8', 'UTF-8//IGNORE', $texto);
            if ($convertido !== FALSE) {
                return $convertido;
            }
        }

        return preg_replace('/[^\x09\x0A\x20-\x7E]/', '?', $texto);
    }

    /**
     * Recorta en bytes respetando límites de caracteres UTF-8.
     */
    private function _truncar(string $texto, int $max): string
    {
        if ($max <= 0 || strlen($texto) <= $max) {
            return $texto;
        }

        if (function_exists('mb_strcut')) {
            $corte = mb_strcut($texto, 0, $max, 'UTF-8');
            if (is_string($corte)) {
                return $corte;
            }
        }

        $corte = substr($texto, 0, $max);
        while ($corte !== '' && !preg_match('//u', $corte)) {
            $corte = substr($corte, 0, -1);
        }

        return $corte;
    }
}
