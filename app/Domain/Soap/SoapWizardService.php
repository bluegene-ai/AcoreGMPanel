<?php
/**
 * File: app/Domain/Soap/SoapWizardService.php
 * Purpose: Defines class SoapWizardService for the app/Domain/Soap module.
 * Classes:
 *   - SoapWizardService
 * Functions:
 *   - __construct()
 *   - metadata()
 *   - categories()
 *   - command()
 *   - buildCommand()
 *   - normalizeValue()
 *   - applyTemplate()
 *   - mapArguments()
 *   - buildIndex()
 *   - loadDefaultCatalog()
 *   - localizeCatalog()
 */

namespace Acme\Panel\Domain\Soap;

use Acme\Panel\Core\Lang;
use Acme\Panel\Support\ConfigLocalization;
use InvalidArgumentException;

class SoapWizardService
{
    private array $catalog;
    private array $commandIndex = [];

    public function __construct(?array $catalog = null)
    {
        $this->catalog = $catalog ?? $this->loadDefaultCatalog();
        $this->buildIndex();
    }

    public function metadata(): array
    {
        return $this->catalog['metadata'] ?? [];
    }

    public function categories(): array
    {
        return $this->catalog['categories'] ?? [];
    }

    public function command(string $key): ?array
    {
        return $this->commandIndex[$key]['command'] ?? null;
    }

    public function buildCommand(string $key, array $input): array
    {
        if (!isset($this->commandIndex[$key])) {
            return [
                'success' => false,
                'message' => Lang::get('app.soap.wizard.errors.command_not_found'),
                'errors' => ['command' => Lang::get('app.soap.wizard.errors.command_missing')],
            ];
        }
        $definition = $this->commandIndex[$key]['command'];
        $category = $this->commandIndex[$key]['category'];
        $argumentMap = $this->mapArguments($definition['arguments'] ?? []);
        $errors = [];
        $resolved = [];

        foreach ($argumentMap as $argKey => $argDef) {
            $value = $input[$argKey] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            if (($value === null || $value === '' || $value === []) && array_key_exists('default', $argDef)) {
                $value = $argDef['default'];
            }

            if ($argDef['required'] ?? false) {
                if ($value === null || $value === '' || $value === []) {
                    $errors[$argKey] = Lang::get('app.soap.wizard.errors.argument_required');
                    continue;
                }
            }

            if ($value === null || $value === '' || $value === []) {
                $resolved[$argKey] = null;
                continue;
            }

            try {
                $resolved[$argKey] = $this->normalizeValue($value, $argDef);
            } catch (InvalidArgumentException $e) {
                $errors[$argKey] = $e->getMessage();
            }
        }

        if ($errors) {
            return [
                'success' => false,
                'message' => Lang::get('app.soap.wizard.errors.validation_failed'),
                'errors' => $errors,
            ];
        }

        $template = $definition['template'] ?? $definition['name'];
        [$commandString, $missing] = $this->applyTemplate($template, $argumentMap, $resolved);
        if ($missing) {
            $errors['_template'] = Lang::get('app.soap.wizard.errors.template_missing_list', ['fields' => implode(', ', $missing)]);
            return [
                'success' => false,
                'message' => Lang::get('app.soap.wizard.errors.template_incomplete'),
                'errors' => $errors,
            ];
        }

        $commandString = trim(preg_replace('/\s+/', ' ', $commandString));

        return [
            'success' => true,
            'command' => $commandString,
            'definition' => $definition,
            'category' => $category,
            'resolved_args' => $resolved,
        ];
    }

    private function normalizeValue(mixed $value, array $argDef): string
    {
        $type = $argDef['type'] ?? 'text';

        switch ($type) {
            case 'number':
                if ($value === '' || $value === null) {
                    throw new InvalidArgumentException(Lang::get('app.soap.wizard.errors.number_required'));
                }
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException(Lang::get('app.soap.wizard.errors.number_invalid'));
                }
                $numeric = $value + 0;
                if (isset($argDef['min']) && $numeric < $argDef['min']) {
                    throw new InvalidArgumentException(Lang::get('app.soap.wizard.errors.number_too_small', ['min' => $argDef['min']]));
                }
                if (isset($argDef['max']) && $numeric > $argDef['max']) {
                    throw new InvalidArgumentException(Lang::get('app.soap.wizard.errors.number_too_large', ['max' => $argDef['max']]));
                }
                $value = (string)(int)round($numeric);
                break;
            case 'select':
                $allowed = array_map(static fn ($row) => (string)($row['value'] ?? ''), $argDef['options'] ?? []);
                $value = (string)$value;
                if (!in_array($value, $allowed, true)) {
                    throw new InvalidArgumentException(Lang::get('app.soap.wizard.errors.invalid_option'));
                }
                break;
            case 'password':
            case 'text':
            case 'textarea':
            default:
                $value = (string)$value;
                break;
        }

        if (($argDef['wrap'] ?? null) === 'quotes') {
            $value = '"' . addcslashes($value, "\\\"") . '"';
        }

        return $value;
    }

    private function applyTemplate(string $template, array $argumentMap, array $values): array
    {
        $missing = [];
        $result = preg_replace_callback('/\{([a-z0-9_]+)(\?)?\}/i', function ($matches) use ($argumentMap, $values, &$missing) {
            $key = $matches[1];
            $optional = ($matches[2] ?? '') === '?';
            $argDef = $argumentMap[$key] ?? null;
            $value = $values[$key] ?? null;

            if ($value === null || $value === '') {
                if ($optional) {
                    return '';
                }
                $missing[] = $key;
                return '';
            }

            if ($argDef && ($argDef['wrap'] ?? null) === 'quotes' && !str_starts_with($value, '"')) {

                $value = '"' . addcslashes((string)$value, "\\\"") . '"';
            }

            return (string)$value;
        }, $template ?? '');

        return [$result ?? '', $missing];
    }

    private function mapArguments(array $arguments): array
    {
        $map = [];
        foreach ($arguments as $arg) {
            if (!isset($arg['key'])) {
                continue;
            }
            $map[$arg['key']] = $arg;
        }
        return $map;
    }

    private function buildIndex(): void
    {
        $this->commandIndex = [];
        foreach ($this->categories() as $category) {
            $catMeta = [
                'id' => $category['id'] ?? '',
                'label' => $category['label'] ?? '',
                'summary' => $category['summary'] ?? '',
            ];
            foreach ($category['commands'] ?? [] as $command) {
                if (empty($command['key'])) {
                    continue;
                }
                $this->commandIndex[$command['key']] = [
                    'category' => $catMeta,
                    'command' => $command,
                ];
            }
        }
    }

    private function loadDefaultCatalog(): array
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'soap_commands.php';
        if (!is_file($path)) {
            return ['categories' => []];
        }
        $data = include $path;
        if (!is_array($data)) {
            return ['categories' => []];
        }
        return $this->localizeCatalog($data);
    }

    private function localizeCatalog(array $catalog): array
    {
        return ConfigLocalization::localizeArray($catalog);
    }
}

