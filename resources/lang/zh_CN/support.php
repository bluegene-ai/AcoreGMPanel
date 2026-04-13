<?php

return [
  'ip_location' => [
    'labels' => [
      'private' => '内网IP',
      'unknown' => '未知归属地',
    ],
    'errors' => [
      'empty' => 'IP不能为空',
      'invalid' => 'IP格式不正确',
      'provider_unreachable' => '无法连接 IP 数据服务',
      'response_invalid' => '返回格式异常',
      'failed' => '查询失败',
      'failed_reason' => '查询失败：:message',
      'mmdb_unavailable' => '本地 IP 库不可用（请配置 mmdb 并安装依赖）',
      'mmdb_reader_missing' => '缺少 MaxMind 读取能力（建议安装 PHP 扩展 maxminddb，或使用 composer 安装 maxmind-db/reader）',
      'mmdb_file_missing' => '本地 IP 库文件不存在（请下载 GeoLite2-City.mmdb 并放到 storage/ip_geo/）',
      'mmdb_open_failed' => '无法打开本地 IP 库文件（请检查文件权限与 PHP maxminddb 扩展）',
    ],
  ],
  'server_list' => [
    'default' => '默认服',
  ],
  'multi_server' => [
    'errors' => [
      'auth_config_missing' => '服务器 #:server 缺少 auth 配置。',
    ],
  ],
  'srp' => [
    'errors' => [
      'gmp_missing' => 'GMP 扩展未启用，无法生成 SRP verifier（请在 php.ini 启用 extension=gmp）。',
      'gmp_missing_binary' => 'GMP 扩展未启用，无法生成 SRP verifier（binary32）。',
    ],
  ],
  'soap_executor' => [
    'errors' => [
      'empty_command' => '命令不能为空。',
      'not_whitelisted' => '命令不在白名单中。',
      'request_failed' => 'SOAP 请求失败。',
      'unknown' => '未知错误。',
    ],
  ],
];