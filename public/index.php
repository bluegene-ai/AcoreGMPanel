<?php
/**
 * File: public/index.php
 * Purpose: Provides functionality for the public module.
 */

declare(strict_types=1);

use Acme\Panel\Core\Bootstrap;

if (!defined('PANEL_START_TIME')) {
	define('PANEL_START_TIME', microtime(true));
}

if (!defined('PANEL_START_MEMORY')) {
	define('PANEL_START_MEMORY', memory_get_usage(true));
}

require __DIR__ . '/../bootstrap/autoload.php';

Bootstrap::run();

