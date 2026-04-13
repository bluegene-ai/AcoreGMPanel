<?php

return [
  'ip_location' => [
    'labels' => [
      'private' => 'Private IP',
      'unknown' => 'Unknown location',
    ],
    'errors' => [
      'empty' => 'IP cannot be empty',
      'invalid' => 'Invalid IP format',
      'provider_unreachable' => 'Unable to reach the IP data service',
      'response_invalid' => 'Invalid response format',
      'failed' => 'Lookup failed',
      'failed_reason' => 'Lookup failed: :message',
      'mmdb_unavailable' => 'Local IP database is unavailable (configure mmdb and install dependencies)',
      'mmdb_reader_missing' => 'Missing MaxMind reader support (install the PHP maxminddb extension or composer package maxmind-db/reader)',
      'mmdb_file_missing' => 'Local IP database file is missing (download GeoLite2-City.mmdb into storage/ip_geo/)',
      'mmdb_open_failed' => 'Unable to open the local IP database file (check file permissions and the PHP maxminddb extension)',
    ],
  ],
  'server_list' => [
    'default' => 'Default realm',
  ],
  'multi_server' => [
    'errors' => [
      'auth_config_missing' => 'Realm #:server is missing auth configuration.',
    ],
  ],
  'srp' => [
    'errors' => [
      'gmp_missing' => 'The GMP extension is not enabled, so SRP verifier generation is unavailable (enable extension=gmp in php.ini).',
      'gmp_missing_binary' => 'The GMP extension is not enabled, so binary32 SRP verifier generation is unavailable.',
    ],
  ],
  'soap_executor' => [
    'errors' => [
      'empty_command' => 'Command cannot be empty.',
      'not_whitelisted' => 'Command is not on the whitelist.',
      'request_failed' => 'SOAP request failed.',
      'unknown' => 'Unknown error.',
    ],
  ],
];