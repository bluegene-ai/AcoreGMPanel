<?php
/**
 * IP geolocation settings.
 *
 * This panel uses a local MaxMind .mmdb database (e.g., GeoLite2-City.mmdb).
 * Download the database separately and place it under storage/ip_geo/.
 */

return [
    // Driver: currently only 'mmdb' is supported.
    'driver' => 'mmdb',

    // Absolute path to the .mmdb file.
    // You can override this in config/generated/ip_location.php.
    'mmdb_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'ip_geo' . DIRECTORY_SEPARATOR . 'GeoLite2-City.mmdb',

    // Preferred locale for names in the mmdb.
    // GeoLite2 supports 'zh-CN' and 'en' for most fields.
    'locale' => 'zh-CN',
];
