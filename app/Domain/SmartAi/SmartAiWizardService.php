<?php
/**
 * File: app/Domain/SmartAi/SmartAiWizardService.php
 * Purpose: Defines class SmartAiWizardService for the app/Domain/SmartAi module.
 * Classes:
 *   - SmartAiWizardService
 * Functions:
 *   - __construct()
 *   - metadata()
 *   - baseFields()
 *   - events()
 *   - actions()
 *   - targets()
 *   - catalog()
 *   - build()
 *   - buildSegment()
 *   - normalizeSegmentBase()
 *   - loadCatalog()
 *   - indexCatalog()
 *   - resolveParams()
 *   - normalizeParamValue()
 *   - intValue()
 *   - boolValue()
 *   - defaultColumns()
 *   - buildSql()
 *   - escapeSql()
 *   - hasErrors()
 *   - hasAnySegmentErrors()
 */

namespace Acme\Panel\Domain\SmartAi;

use Acme\Panel\Core\Lang;
use Acme\Panel\Support\ConfigLocalization;
use InvalidArgumentException;

class SmartAiWizardService
{
    private array $catalog;
    private array $events = [];
    private array $actions = [];
    private array $targets = [];
    private array $sourceTypes = [];

    public function __construct(?array $catalog = null)
    {
        $this->catalog = $catalog ?? $this->loadCatalog();
        $this->indexCatalog();
    }

    public function metadata(): array
    {
        return $this->catalog['metadata'] ?? [];
    }

    public function baseFields(): array
    {
        $base = $this->catalog['base'] ?? [];
        foreach ($base as &$field) {
            if (($field['type'] ?? '') === 'select' && isset($field['options'])) {
                $optionsKey = $field['options'];
                if (is_string($optionsKey) && isset($this->catalog[$optionsKey])) {
                    $field['options'] = $this->catalog[$optionsKey];
                }
            }
        }
        unset($field);
        return $base;
    }

    public function events(): array
    {
        return $this->catalog['events'] ?? [];
    }

    public function actions(): array
    {
        return $this->catalog['actions'] ?? [];
    }

    public function targets(): array
    {
        return $this->catalog['targets'] ?? [];
    }

    public function catalog(): array
    {
        return [
            'metadata' => $this->metadata(),
            'base' => $this->baseFields(),
            'events' => $this->events(),
            'actions' => $this->actions(),
            'targets' => $this->targets(),
        ];
    }

    public function build(array $payload): array
    {
        $baseInput = is_array($payload['base'] ?? null) ? $payload['base'] : [];

        $errors = [
            'base' => [],
            'segments' => [],
        ];

        $entry = $this->intValue($baseInput['entryorguid'] ?? null);
            if ($entry <= 0) {
                $errors['base']['entryorguid'] = Lang::get('app.smartai.builder.errors.base.entryorguid');
        }

        $defaultSource = $this->catalog['base'][1]['default'] ?? 0;
        $sourceType = $this->intValue($baseInput['source_type'] ?? $defaultSource);
            if (!isset($this->sourceTypes[$sourceType])) {
                $errors['base']['source_type'] = Lang::get('app.smartai.builder.errors.base.source_type');
        }

        $baseDefaults = [
            'entryorguid' => $entry,
            'source_type' => $sourceType,
            'id' => $this->intValue($baseInput['id'] ?? 0, true) ?? 0,
            'link' => $this->intValue($baseInput['link'] ?? 0, true) ?? 0,
            'event_phase_mask' => $this->intValue($baseInput['event_phase_mask'] ?? 0, true) ?? 0,
            'event_chance' => $this->intValue($baseInput['event_chance'] ?? 100, true) ?? 100,
            'event_flags' => $this->intValue($baseInput['event_flags'] ?? 0, true) ?? 0,
            'comment' => trim((string)($baseInput['comment'] ?? '')),
        ];

        if ($baseDefaults['event_chance'] < 0 || $baseDefaults['event_chance'] > 100) {
                $errors['base']['event_chance'] = Lang::get('app.smartai.builder.errors.base.event_chance');
        }
        if ($baseDefaults['event_flags'] < 0) {
                $errors['base']['event_flags'] = Lang::get('app.smartai.builder.errors.base.event_flags');
        }

        $includeDelete = $this->boolValue($baseInput['include_delete'] ?? true);

        $segmentsInput = $payload['segments'] ?? null;
        if (!is_array($segmentsInput) || empty($segmentsInput)) {
            $segmentsInput = [[
                'base' => [],
                'event' => $payload['event'] ?? [],
                'action' => $payload['action'] ?? [],
                'target' => $payload['target'] ?? [],
            ]];
        }

        $builtSegments = [];
        foreach (array_values($segmentsInput) as $index => $segmentInput) {
            if (!is_array($segmentInput)) {
                $segmentInput = [];
            }
            $segmentErrors = [
                'base' => [],
                'event' => [],
                'action' => [],
                'target' => [],
            ];

            $built = $this->buildSegment($segmentInput, $baseDefaults, $index, $segmentErrors);
            if ($this->hasErrors($segmentErrors)) {
                $segmentErrors['key'] = $segmentInput['key'] ?? null;
                $errors['segments'][$index] = $segmentErrors;
            } elseif ($built !== null) {
                $built['key'] = $segmentInput['key'] ?? null;
                $builtSegments[] = $built;
            }
        }

        $hasBaseErrors = !empty($errors['base']);
        $hasSegmentErrors = $this->hasAnySegmentErrors($errors['segments']);
            if ($hasBaseErrors || $hasSegmentErrors || empty($builtSegments)) {
                if (empty($builtSegments) && !$hasSegmentErrors) {
                    $errors['segments'][0]['event']['type'] = Lang::get('app.smartai.builder.errors.segment.event_required');
                }
                return [
                    'success' => false,
                    'message' => Lang::get('app.smartai.builder.messages.validation_failed'),
                    'errors' => $errors,
                ];
        }

        $sqlChunks = [];
        foreach ($builtSegments as $idx => $segment) {
            $sqlChunks[] = $this->buildSql($segment['columns'], $includeDelete && $idx === 0);
        }
        $sql = implode("\n\n", $sqlChunks);

        $responseSegments = array_map(static function (array $segment): array {
            return [
                'event' => $segment['event'],
                'action' => $segment['action'],
                'target' => $segment['target'],
                'base' => $segment['base'],
                'key' => $segment['key'] ?? null,
                'label' => $segment['label'] ?? null,
            ];
        }, $builtSegments);

        $result = [
            'success' => true,
            'sql' => $sql,
            'rows' => array_map(static fn ($segment) => $segment['columns'], $builtSegments),
            'segments' => $responseSegments,
            'include_delete' => $includeDelete,
        ];

        if (!empty($responseSegments)) {
            $first = $responseSegments[0];
            $result['event'] = $first['event'] ?? null;
            $result['action'] = $first['action'] ?? null;
            $result['target'] = $first['target'] ?? null;
        }

        return $result;
    }

    private function buildSegment(array $segmentInput, array $baseDefaults, int $index, array &$errors): ?array
    {
        $segmentBase = is_array($segmentInput['base'] ?? null) ? $segmentInput['base'] : [];
        $normalizedBase = $this->normalizeSegmentBase($segmentBase, $baseDefaults, $index, $errors);

        $eventInput = is_array($segmentInput['event'] ?? null) ? $segmentInput['event'] : [];
        $actionInput = is_array($segmentInput['action'] ?? null) ? $segmentInput['action'] : [];
        $targetInput = is_array($segmentInput['target'] ?? null) ? $segmentInput['target'] : [];

        $eventType = $this->intValue($eventInput['type'] ?? null, true);
        $eventDef = $eventType !== null && isset($this->events[$eventType]) ? $this->events[$eventType] : null;
        if (!$eventDef) {
            $errors['event']['type'] = Lang::get('app.smartai.builder.errors.event.type');
        }
        $eventParams = $this->resolveParams($eventDef['params'] ?? [], $eventInput['params'] ?? [], $errors['event']);

        $actionType = $this->intValue($actionInput['type'] ?? null, true);
        $actionDef = $actionType !== null && isset($this->actions[$actionType]) ? $this->actions[$actionType] : null;
        if (!$actionDef) {
            $errors['action']['type'] = Lang::get('app.smartai.builder.errors.action.type');
        }
        $actionParams = $this->resolveParams($actionDef['params'] ?? [], $actionInput['params'] ?? [], $errors['action']);

        $targetType = $this->intValue($targetInput['type'] ?? null, true);
        $targetDef = $targetType !== null && isset($this->targets[$targetType]) ? $this->targets[$targetType] : null;
        if (!$targetDef) {
            $errors['target']['type'] = Lang::get('app.smartai.builder.errors.target.type');
        }
        $targetParams = $this->resolveParams($targetDef['params'] ?? [], $targetInput['params'] ?? [], $errors['target']);

        if ($this->hasErrors($errors)) {
            return null;
        }

        $columns = $this->defaultColumns();
        $columns['entryorguid'] = $baseDefaults['entryorguid'];
        $columns['source_type'] = $baseDefaults['source_type'];
        $columns['id'] = $normalizedBase['id'];
        $columns['link'] = $normalizedBase['link'];
        $columns['event_type'] = $eventType;
        $columns['event_phase_mask'] = $normalizedBase['event_phase_mask'];
        $columns['event_chance'] = $normalizedBase['event_chance'];
        $columns['event_flags'] = $normalizedBase['event_flags'];
        $columns['action_type'] = $actionType;
        $columns['target_type'] = $targetType;
        $columns['comment'] = $normalizedBase['comment'];

        foreach ($eventParams as $column => $value) {
            $columns[$column] = $value;
        }
        foreach ($actionParams as $column => $value) {
            $columns[$column] = $value;
        }
        foreach ($targetParams as $column => $value) {
            $columns[$column] = $value;
        }

        return [
            'columns' => $columns,
            'event' => $eventDef,
            'action' => $actionDef,
            'target' => $targetDef,
            'base' => $normalizedBase,
            'label' => $segmentInput['label'] ?? null,
        ];
    }

    private function normalizeSegmentBase(array $segmentBase, array $baseDefaults, int $index, array &$errors): array
    {
        $normalized = [];

        $scriptId = $this->intValue($segmentBase['id'] ?? null, true);
        if ($scriptId === null) {
            $scriptId = ($baseDefaults['id'] ?? 0) + $index;
        }
            if ($scriptId < 0) {
                $errors['base']['id'] = Lang::get('app.smartai.builder.errors.base.id_negative');
        }
        $normalized['id'] = $scriptId;

        $link = $this->intValue($segmentBase['link'] ?? null, true);
        if ($link === null) {
            $link = $baseDefaults['link'] ?? 0;
        }
            if ($link < 0) {
                $errors['base']['link'] = Lang::get('app.smartai.builder.errors.base.link_negative');
        }
        $normalized['link'] = $link;

        $phaseMask = $this->intValue($segmentBase['event_phase_mask'] ?? null, true);
        if ($phaseMask === null) {
            $phaseMask = $baseDefaults['event_phase_mask'] ?? 0;
        }
            if ($phaseMask < 0) {
                $errors['base']['event_phase_mask'] = Lang::get('app.smartai.builder.errors.base.phase_negative');
        }
        $normalized['event_phase_mask'] = $phaseMask;

        $chance = $this->intValue($segmentBase['event_chance'] ?? null, true);
        if ($chance === null) {
            $chance = $baseDefaults['event_chance'] ?? 100;
        }
            if ($chance < 0 || $chance > 100) {
                $errors['base']['event_chance'] = Lang::get('app.smartai.builder.errors.base.event_chance');
        }
        $normalized['event_chance'] = $chance;

        $flags = $this->intValue($segmentBase['event_flags'] ?? null, true);
        if ($flags === null) {
            $flags = $baseDefaults['event_flags'] ?? 0;
        }
            if ($flags < 0) {
                $errors['base']['event_flags'] = Lang::get('app.smartai.builder.errors.base.event_flags');
        }
        $normalized['event_flags'] = $flags;

        $comment = trim((string)($segmentBase['comment'] ?? ($baseDefaults['comment'] ?? '')));
        $normalized['comment'] = $comment;

        return $normalized;
    }

    private function loadCatalog(): array
    {
        $file = dirname(__DIR__, 3) . '/config/smartai_catalog.php';
        if (!is_file($file)) {
            return [];
        }
    $data = require $file;
    return is_array($data) ? ConfigLocalization::localizeArray($data) : [];
    }

    private function indexCatalog(): void
    {
        $this->events = [];
        foreach ($this->catalog['events'] ?? [] as $event) {
            if (!isset($event['id'])) {
                continue;
            }
            $this->events[(int)$event['id']] = $event;
        }

        $this->actions = [];
        foreach ($this->catalog['actions'] ?? [] as $action) {
            if (!isset($action['id'])) {
                continue;
            }
            $this->actions[(int)$action['id']] = $action;
        }

        $this->targets = [];
        foreach ($this->catalog['targets'] ?? [] as $target) {
            if (!isset($target['id'])) {
                continue;
            }
            $this->targets[(int)$target['id']] = $target;
        }

        $this->sourceTypes = [];
        foreach ($this->catalog['source_types'] ?? [] as $row) {
            if (!isset($row['value'])) {
                continue;
            }
            $this->sourceTypes[(int)$row['value']] = $row;
        }
    }

    private function resolveParams(array $definitions, array $input, array &$errors): array
    {
        $resolved = [];
        foreach ($definitions as $def) {
            $column = $def['column'] ?? null;
            if (!$column) {
                continue;
            }
            $type = $def['type'] ?? 'number';
            $key = $column;
            $valueProvided = $input[$column] ?? $input[$def['key'] ?? $column] ?? null;
            if (($valueProvided === null || $valueProvided === '') && array_key_exists('default', $def)) {
                $valueProvided = $def['default'];
            }

                if ($def['required'] ?? false) {
                    if ($valueProvided === null || $valueProvided === '' || $valueProvided === []) {
                        $errors[$column] = Lang::get('app.common.validation.required');
                        continue;
                    }
            }

            if ($valueProvided === null || $valueProvided === '') {
                $resolved[$column] = $type === 'checkbox' ? 0 : ($type === 'text' ? '' : 0);
                continue;
            }

            try {
                $resolved[$column] = $this->normalizeParamValue($valueProvided, $def);
            } catch (InvalidArgumentException $e) {
                $errors[$column] = $e->getMessage();
            }
        }
        return $resolved;
    }

    private function normalizeParamValue(mixed $value, array $definition): int|float|string
    {
        $type = $definition['type'] ?? 'number';
        switch ($type) {
            case 'text':
            case 'textarea':
                $str = (string)$value;
                if (isset($definition['max']) && mb_strlen($str) > (int)$definition['max']) {
                        throw new InvalidArgumentException(Lang::get('app.common.validation.length_max', [
                            'max' => $definition['max'],
                        ]));
                }
                return $str;
            case 'checkbox':
                return $this->boolValue($value) ? 1 : 0;
            case 'number':
            default:
                if (!is_numeric($value)) {
                        throw new InvalidArgumentException(Lang::get('app.common.validation.number'));
                }
                $number = $value + 0;
                if (isset($definition['min']) && $number < $definition['min']) {
                        throw new InvalidArgumentException(Lang::get('app.common.validation.min', [
                            'min' => $definition['min'],
                        ]));
                }
                if (isset($definition['max']) && $number > $definition['max']) {
                        throw new InvalidArgumentException(Lang::get('app.common.validation.max', [
                            'max' => $definition['max'],
                        ]));
                }
                return (int)round($number);
        }
    }

    private function intValue(mixed $value, bool $allowNull = false): ?int
    {
        if ($value === null || $value === '') {
            return $allowNull ? null : 0;
        }
        if (is_numeric($value)) {
            return (int)round($value + 0);
        }
        return $allowNull ? null : 0;
    }

    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
        }
        if (is_numeric($value)) {
            return (int)$value === 1;
        }
        return false;
    }

    private function defaultColumns(): array
    {
        return [
            'entryorguid' => 0,
            'source_type' => 0,
            'id' => 0,
            'link' => 0,
            'event_type' => 0,
            'event_phase_mask' => 0,
            'event_chance' => 100,
            'event_flags' => 0,
            'event_param1' => 0,
            'event_param2' => 0,
            'event_param3' => 0,
            'event_param4' => 0,
            'event_param_string' => '',
            'action_type' => 0,
            'action_param1' => 0,
            'action_param2' => 0,
            'action_param3' => 0,
            'action_param4' => 0,
            'action_param5' => 0,
            'action_param6' => 0,
            'target_type' => 0,
            'target_param1' => 0,
            'target_param2' => 0,
            'target_param3' => 0,
            'target_x' => 0,
            'target_y' => 0,
            'target_z' => 0,
            'target_o' => 0,
            'comment' => '',
        ];
    }

    private function buildSql(array $columns, bool $includeDelete): string
    {
        $columnOrder = [
            'entryorguid','source_type','id','link','event_type','event_phase_mask','event_chance','event_flags',
            'event_param1','event_param2','event_param3','event_param4','event_param_string',
            'action_type','action_param1','action_param2','action_param3','action_param4','action_param5','action_param6',
            'target_type','target_param1','target_param2','target_param3','target_x','target_y','target_z','target_o','comment',
        ];

        $values = [];
        foreach ($columnOrder as $key) {
            $value = $columns[$key] ?? null;
            if (is_int($value) || is_float($value)) {
                $values[] = (string)$value;
            } elseif ($value === null || $value === '') {
                $values[] = "''";
            } else {
                $values[] = "'" . $this->escapeSql((string)$value) . "'";
            }
        }

        $sqlLines = [];
        if ($includeDelete) {
            $sqlLines[] = sprintf('DELETE FROM `smart_scripts` WHERE `entryorguid` = %d AND `source_type` = %d;', $columns['entryorguid'], $columns['source_type']);
        }

        $sqlLines[] = 'INSERT INTO `smart_scripts`';
        $sqlLines[] = '(' . implode(', ', array_map(fn ($key) => "`{$key}`", $columnOrder)) . ')';
        $sqlLines[] = 'VALUES';
        $sqlLines[] = '(' . implode(', ', $values) . ');';

        return implode("\n", $sqlLines);
    }

    private function escapeSql(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    private function hasErrors(array $errors): bool
    {
        foreach ($errors as $section) {
            if (!empty($section)) {
                return true;
            }
        }
        return false;
    }

    private function hasAnySegmentErrors(array $segments): bool
    {
        foreach ($segments as $segmentErrors) {
            if ($this->hasErrors(array_filter($segmentErrors, 'is_array'))) {
                return true;
            }
        }
        return false;
    }
}

