<?php
/**
 * File: app/Core/View.php
 * Purpose: Defines class View for the app/Core module.
 * Classes:
 *   - View
 * Functions:
 *   - make()
 */

namespace Acme\Panel\Core;

class View
{
    public static string $basePath = __DIR__ . '/../../resources/views';

    public static function make(string $name, array $data=[]): string
    {
        $file = self::$basePath . '/' . str_replace('.','/',$name) . '.php';
        if(!is_file($file)) return 'View not found: '.$name;
        extract($data, EXTR_OVERWRITE);
        ob_start();
        include $file;
        return ob_get_clean();
    }
}

