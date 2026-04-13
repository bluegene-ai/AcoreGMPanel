<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$available = [
    'account' => __DIR__ . '/verify_account_runtime.php',
    'mail' => __DIR__ . '/verify_mail_runtime.php',
    'aegis' => __DIR__ . '/verify_aegis_runtime.php',
    'mass_mail' => __DIR__ . '/verify_mass_mail_runtime.php',
    'character_boost' => __DIR__ . '/verify_character_boost_runtime.php',
];

$selected = array_slice($argv, 1);
if ($selected === []) {
    $selected = array_keys($available);
}

$unknown = array_values(array_diff($selected, array_keys($available)));
if ($unknown !== []) {
    fwrite(STDERR, json_encode([
        'success' => false,
        'message' => 'Unknown runtime verification targets',
        'unknown' => $unknown,
        'available' => array_keys($available),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(2);
}

$phpBinary = PHP_BINARY !== '' ? PHP_BINARY : 'php';
$suite = [];
$success = true;

foreach ($selected as $target) {
    $script = $available[$target];
    $command = escapeshellarg($phpBinary) . ' ' . escapeshellarg($script);
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);
    $raw = trim(implode(PHP_EOL, $output));
    $decoded = $raw !== '' ? json_decode($raw, true) : null;

    $targetSuccess = $exitCode === 0
        && is_array($decoded)
        && ($decoded['success'] ?? false) === true;

    if (!$targetSuccess) {
        $success = false;
    }

    $suite[$target] = [
        'success' => $targetSuccess,
        'exit_code' => $exitCode,
        'checks' => is_array($decoded['checks'] ?? null) ? $decoded['checks'] : [],
        'raw_output' => $targetSuccess ? null : $raw,
        'script' => str_replace($root . DIRECTORY_SEPARATOR, '', $script),
    ];
}

echo json_encode([
    'success' => $success,
    'targets' => array_values($selected),
    'suite' => $suite,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($success ? 0 : 1);