<?php
/**
 * File: resources/views/components/flash.php
 * Purpose: Provides functionality for the resources/views/components module.
 */

$singleMessage = null;
if (isset($message) && trim((string) $message) !== '') {
	$singleMessage = trim((string) $message);
}

$renderAll = isset($flashRenderAll) ? (bool) $flashRenderAll : false;
$messages = [];
$validTypes = ['info', 'success', 'error'];

if ($singleMessage !== null) {
	$messages[] = [
		'type' => 'info',
		'label' => 'info',
		'text' => $singleMessage,
	];
} elseif (function_exists('flash_pull_all')) {
	$pulled = flash_pull_all();
	if (!is_array($pulled) || !$pulled) {
		return;
	}

	if ($renderAll) {
		foreach ($pulled as $type => $items) {
			if (!is_array($items)) {
				continue;
			}
			foreach ($items as $text) {
				$text = (string) $text;
				if ($text === '') {
					continue;
				}
				$normalized = in_array($type, $validTypes, true) ? $type : 'info';
				$messages[] = [
					'type' => $normalized,
					'label' => $type,
					'text' => $text,
				];
			}
		}
	} else {
		foreach (['error', 'success', 'info'] as $type) {
			$text = $pulled[$type][0] ?? null;
			if ($text === null || $text === '') {
				continue;
			}
			$messages[] = [
				'type' => $type,
				'label' => $type,
				'text' => (string) $text,
			];
			break;
		}
	}
}

if (!$messages) {
	return;
}

    // 统一到 panel-flash：视图、JS 与校验脚本都走这一套。
foreach ($messages as $entry) {
	$type = in_array($entry['type'], $validTypes, true) ? $entry['type'] : 'info';
	echo '<div class="panel-flash panel-flash--' . $type . ' is-visible">' . htmlspecialchars($entry['text']) . '</div>';
}
?>
