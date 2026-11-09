<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Geoip_service
{
    private $city_db;
    private $asn_db;
    private $autoload_file;
    private $reader_ready = FALSE;

    public function __construct()
    {
        $basePath = rtrim(FCPATH, '\\/');
        $this->autoload_file = $basePath . DIRECTORY_SEPARATOR . 'third_party' . DIRECTORY_SEPARATOR . 'maxmind-db-reader' . DIRECTORY_SEPARATOR . 'autoload.php';
        $this->city_db = $basePath . DIRECTORY_SEPARATOR . 'third_party' . DIRECTORY_SEPARATOR . 'geoip' . DIRECTORY_SEPARATOR . 'dbip-city-lite-2026-05.mmdb';
        $this->asn_db = $basePath . DIRECTORY_SEPARATOR . 'third_party' . DIRECTORY_SEPARATOR . 'geoip' . DIRECTORY_SEPARATOR . 'GeoLite2-ASN.mmdb';
    }

    public function enrich_ip($ip)
    {
        $ip = trim((string)$ip);
        if ($ip === '' || !$this->_reader_available()) {
            return [];
        }

        try {
            $cityReader = new MaxMind\Db\Reader($this->city_db);
            $asnReader = new MaxMind\Db\Reader($this->asn_db);

            $cityRecord = $cityReader->get($ip);
            $asnRecord = $asnReader->get($ip);

            $cityReader->close();
            $asnReader->close();

            return [
                'bit_asn' => isset($asnRecord['autonomous_system_number']) ? (string)$asnRecord['autonomous_system_number'] : NULL,
                'bit_isp' => $this->_value($asnRecord, ['autonomous_system_organization']),
                'bit_country_code' => $this->_value($cityRecord, ['country', 'iso_code']),
                'bit_region' => $this->_value($cityRecord, ['subdivisions', 0, 'names', 'en']),
                'bit_city' => $this->_value($cityRecord, ['city', 'names', 'en']),
                'bit_organization' => $this->_value($asnRecord, ['autonomous_system_organization']),
                'bit_latitude' => $this->_stringify($this->_value($cityRecord, ['location', 'latitude'])),
                'bit_longitude' => $this->_stringify($this->_value($cityRecord, ['location', 'longitude'])),
            ];
        } catch (Throwable $e) {
            log_message('error', 'GeoIP lookup failed: ' . $e->getMessage());
            return [];
        }
    }

    private function _reader_available()
    {
        if ($this->reader_ready) {
            return TRUE;
        }

        if (!is_file($this->autoload_file) || !is_file($this->city_db) || !is_file($this->asn_db)) {
            return FALSE;
        }

        require_once $this->autoload_file;
        $this->reader_ready = class_exists('MaxMind\\Db\\Reader');

        return $this->reader_ready;
    }

    private function _value($source, array $path)
    {
        $current = $source;
        foreach ($path as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
                continue;
            }

            return NULL;
        }

        return $current;
    }

    private function _stringify($value)
    {
        if ($value === NULL || $value === '') {
            return NULL;
        }

        return (string)$value;
    }
}