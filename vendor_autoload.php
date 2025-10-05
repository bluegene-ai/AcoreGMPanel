<?php
/**
 * File: vendor_autoload.php
 * Purpose: Provides functionality for the project.
 */

spl_autoload_register(function(string $class){
    $prefix = 'Acme\\Panel\\';
    if(strpos($class,$prefix)!==0) return;
    $rel = substr($class, strlen($prefix));
    $path = __DIR__ . '/app/' . str_replace('\\','/',$rel) . '.php';
    if(is_file($path)) require $path;
});


