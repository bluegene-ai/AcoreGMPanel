<?php
session_id('clidbg');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '1');

if (!defined('PANEL_CLI_AUTH_BYPASS')) {
	define('PANEL_CLI_AUTH_BYPASS', true);
}

$projectSlug = basename(__DIR__);
$defaultBasePath = '/' . ltrim($projectSlug, '/') . '/';
if ($projectSlug === '' || $projectSlug === '.' || $projectSlug === DIRECTORY_SEPARATOR) {
	$defaultBasePath = '/';
}

$requestPath = $argv[1] ?? $defaultBasePath;
$forceAuth = true;

$parsed = parse_url($requestPath);
$path = $parsed['path'] ?? '';
$queryString = $parsed['query'] ?? '';

if ($path === '' || $path === '/') {
	$path = $defaultBasePath;
} elseif ($path[0] !== '/') {
	$path = '/' . ltrim($path, '/');
}

parse_str($queryString, $queryParams);

$_GET = $queryParams;
$_REQUEST = array_merge($_REQUEST ?? [], $queryParams);

$_SERVER['REQUEST_URI'] = $path . ($queryString !== '' ? '?' . $queryString : '');
$_SERVER['QUERY_STRING'] = $queryString;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = rtrim($defaultBasePath, '/') . '/public/index.php';
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/public/index.php';
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['HTTP_ACCEPT'] = 'text/html';

$_SESSION['panel_logged_in'] = true;
$_SESSION['panel_user'] = 'admin';
require __DIR__ . '/public/index.php';
