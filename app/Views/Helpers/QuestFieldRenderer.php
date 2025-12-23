<?php
/**
 * File: app/Views/Helpers/QuestFieldRenderer.php
 * Purpose: Defines class QuestFieldRenderer for the app/Views/Helpers module.
 */

namespace Acme\Panel\Views\Helpers;

class QuestFieldRenderer
{
    public static function renderGroup(string $groupKey, array $groupCfg, array $fieldsCfg, array $quest, ?string $tab = null): string
    {
        $tabAttr = $tab ? ' data-group-tab="' . htmlspecialchars($tab) . '"' : '';
        $html = '<section class="quest-group mb-4"' . $tabAttr . ' id="group-' . $groupKey . '">';
        $html .= '<h5 class="mb-2">' . htmlspecialchars($groupCfg['label']) . '</h5>';
        $html .= '<div class="row g-3">';

        foreach ($groupCfg['fields'] as $fieldName) {
            if (!isset($fieldsCfg[$fieldName])) {
                continue;
            }
            $fieldConfig = $fieldsCfg[$fieldName];
            $html .= self::renderField($fieldName, $fieldConfig, $quest);
        }

        $html .= '</div></section>';
        return $html;
    }

    private static function renderField(string $name, array $cfg, array $quest): string
    {
        $val = $quest[$name] ?? ($cfg['default'] ?? '');
        $label = $cfg['label'] ?? $name;
        $type = $cfg['type'] ?? 'string';
        $help = $cfg['help'] ?? '';
        $required = !empty($cfg['required']);

        $commonAttr = ' data-orig="' . htmlspecialchars((string) $val) . '" name="' . htmlspecialchars($name) . '"';
        $commonAttr .= $required ? ' required' : '';

        $colClass = 'col-md-6';
        $normalizedName = strtolower($name);
        if (
            $type === 'text'
            || str_contains($normalizedName, 'description')
            || str_contains($normalizedName, 'text')
        ) {
            $colClass = 'col-12';
        }

        $html = '<div class="' . $colClass . '">';
        $html .= '<label class="form-label mb-1">' . htmlspecialchars($label) . ($required ? '<span class="text-danger ms-1">*</span>' : '') . '</label>';

        if ($type === 'text') {
            $rows = (int) ($cfg['rows'] ?? 3);
            $html .= '<textarea class="form-control form-control-sm" rows="' . $rows . '"' . $commonAttr . '>' . htmlspecialchars((string) $val) . '</textarea>';
        } elseif ($type === 'enum') {
            $html .= '<select class="form-select form-select-sm"' . $commonAttr . '>';
            $html .= '</select>';
        } elseif ($type === 'bitmask') {
            $maskKey = htmlspecialchars($cfg['mask'] ?? $name);
            $html .= '<div class="bitmask-wrapper" data-mask-key="' . $maskKey . '">';
            $html .= '<div class="d-flex align-items-center mb-1 gap-2">';
            $html .= '<input type="text" class="form-control form-control-sm bitmask-value flex-grow-1" value="' . htmlspecialchars((string) $val) . '"' . $commonAttr . ' readonly />';
            $undoTitle = htmlspecialchars(\__('app.quest.edit.fields.bitmask.undo_tooltip'));
            $html .= '<button type="button" class="btn btn-outline-secondary btn-sm bitmask-undo" title="' . $undoTitle . '" aria-label="' . $undoTitle . '"><span class="small">↺</span></button>';
            $html .= '</div>';
            $html .= '<div class="bitmask-boxes row row-cols-4 g-1" data-target="' . htmlspecialchars($name) . '"></div>';
            $html .= '</div>';
        } else {
            $inputType = $type === 'int' ? 'number' : 'text';
            $extra = '';
            if (!empty($cfg['bitmask']) || $type === 'bitmask') {
                $extra = ' data-bitmask="' . htmlspecialchars($cfg['mask'] ?? $name) . '"';
                $inputType = 'text';
            }
            $html .= '<input type="' . $inputType . '" class="form-control form-control-sm" value="' . htmlspecialchars((string) $val) . '"' . $commonAttr . $extra . ' />';
        }

        if ($help) {
            $html .= '<div class="form-text small">' . htmlspecialchars($help) . '</div>';
        }

        $html .= '</div>';
        return $html;
    }
}

