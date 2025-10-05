<?php
/**
 * File: app/Core/Url.php
 * Purpose: Defines class Url for the app/Core module.
 * Classes:
 *   - Url
 * Functions:
 *   - to()
 *   - asset()
 */

namespace Acme\Panel\Core;

class Url
{



    public static function to(string $path = '/'): string
    {
        $base = rtrim(Config::get('app.base_path') ?? '', '/');
        $path = '/' . ltrim($path, '/');
    if ($base === '') return $path;
        return $base . $path;
    }




    public static function asset(string $path): string
    {
        return self::to('assets/' . ltrim($path, '/'));
    }
}

