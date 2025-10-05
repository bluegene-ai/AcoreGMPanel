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

foreach ($messages as $entry) {
	$type = $entry['type'];
	$label = strtoupper($entry['label']);
	$cls = 'flash-' . (in_array($type, $validTypes, true) ? $type : 'info');
	echo '<div class="flash-msg ' . $cls . '"><strong>' . htmlspecialchars($label) . '</strong>' . htmlspecialchars($entry['text']) . '</div>';
}
?>
