<?php
/**
 * File: index.php
 * Purpose: Provides functionality for the project.
 */

declare(strict_types=1);




if (!headers_sent()) {
	header('X-Frame-Options: SAMEORIGIN');
	header('X-Content-Type-Options: nosniff');
}

$public = __DIR__ . '/public/index.php';
if (!is_file($public)) {
	http_response_code(500);
	echo 'public/index.php missing; deployment invalid.';
	exit;
}

require $public;

