<?php
/**
 * File: config/app.php
 * Purpose: Provides functionality for the config module.
 */

return [
  'name' => 'Acore GM Panel',
  'env' => 'local',
  'debug' => true,
  'version' => '0.1.0',
  'timezone' => 'Asia/Shanghai',
  'locale' => 'zh_CN',
  'fallback_locale' => 'en',
  'available_locales' => ['zh_CN', 'en'],
  'base_path' => '',
  'security' => [
    'trusted_proxies' => [],
    'login_rate_limit' => [
      'enabled' => true,
      'max_attempts' => 5,
      'window_seconds' => 300,
      'lockout_seconds' => 900,
    ],
    'headers' => [
      'enabled' => true,
      'x_content_type_options' => 'nosniff',
      'x_frame_options' => 'SAMEORIGIN',
      'referrer_policy' => 'strict-origin-when-cross-origin',
      'permissions_policy' => 'accelerometer=(), autoplay=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()',
      'content_security_policy' => "default-src 'self' data: blob:; img-src 'self' data: blob: https:; style-src 'self' https:; script-src 'self' https:; connect-src 'self' https:; font-src 'self' data: https:; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'",
    ],
  ],
];
