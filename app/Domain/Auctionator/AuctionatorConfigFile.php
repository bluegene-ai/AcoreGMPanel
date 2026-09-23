<?php
/**
 * File: app/Domain/Auctionator/AuctionatorConfigFile.php
 * Purpose: Read and rewrite the worldserver's `configs/modules/mod_auctionator.conf`.
 *
 * The module reads its configuration once, while the Auctionator singleton is constructed,
 * so every save here only takes effect after the worldserver is restarted. The file is
 * comment-heavy (it is the module's own documentation), therefore the writer never
 * regenerates it: it replaces the value on the matching `Key = value` line and leaves
 * every comment, blank line and unknown key untouched.
 *
 * Classes:
 *   - AuctionatorConfigFile
 * Functions:
 *   - __construct()
 *   - path()
 *   - exists()
 *   - read()
 *   - typedValues()
 *   - formatValue()
 *   - write()
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\Auctionator;

use RuntimeException;

final class AuctionatorConfigFile
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * Parse the file into raw string values.
     *
     * @return array{ok: bool, values: array<string, string>, error: string}
     */
    public function read(): array
    {
        if (!$this->exists()) {
            return ['ok' => false, 'values' => [], 'error' => 'missing'];
        }

        $contents = @file_get_contents($this->path);
        if ($contents === false) {
            return ['ok' => false, 'values' => [], 'error' => 'unreadable'];
        }

        return ['ok' => true, 'values' => self::parse($contents), 'error' => ''];
    }

    /**
     * @return array<string, string> key => raw value (without quotes)
     */
    public static function parse(string $contents): array
    {
        $values = [];
        foreach (preg_split('/\r\n|\n|\r/', $contents) ?: [] as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || $trimmed[0] === '#' || $trimmed[0] === ';') {
                continue;
            }

            if (!preg_match('/^\s*(Auctionator\.[A-Za-z0-9_.]+)\s*=\s*(.*?)\s*$/', $line, $matches)) {
                continue;
            }

            $values[$matches[1]] = self::unquote($matches[2]);
        }

        return $values;
    }

    /**
     * Cast the raw values to the types declared in the field map.
     *
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, mixed>
     */
    public function typedValues(array $fields): array
    {
        $read = $this->read();
        $typed = [];

        foreach ($fields as $key => $spec) {
            $raw = $read['values'][$key] ?? null;
            $type = (string) ($spec['type'] ?? 'string');

            if ($raw === null) {
                continue;
            }

            $typed[$key] = match ($type) {
                'bool' => self::toBool($raw) ? 1 : 0,
                'int' => self::toInt($raw),
                'float' => self::toFloat($raw),
                default => $raw,
            };
        }

        return $typed;
    }

    /**
     * Write the given typed values, one `Key = value` line each.
     *
     * @param array<string, mixed>                $values key => typed value
     * @param array<string, array<string, mixed>> $fields field specification (for the type)
     * @return array{ok: bool, changed: array<string, array{from: string, to: string}>, appended: string[], error: string, backup: string}
     */
    public function write(array $values, array $fields): array
    {
        $result = ['ok' => false, 'changed' => [], 'appended' => [], 'error' => '', 'backup' => ''];

        if (!$this->exists()) {
            $result['error'] = 'missing';

            return $result;
        }

        $contents = @file_get_contents($this->path);
        if ($contents === false) {
            $result['error'] = 'unreadable';

            return $result;
        }

        // Line ending used for *appended* lines only: every existing line keeps its own
        // ending, because the replacements below run on the raw bytes instead of on a
        // split/join round trip (that normalised a mixed-ending file to CRLF once).
        $newline = preg_match('/\r\n|\n|\r/', $contents, $ending) === 1 ? $ending[0] : "\n";
        $working = $contents;

        $appended = [];
        foreach ($values as $key => $value) {
            $spec = $fields[$key] ?? null;
            if ($spec === null) {
                continue;
            }

            $type = (string) ($spec['type'] ?? 'string');
            $formatted = self::formatValue($value, $type);
            $pattern = self::linePattern((string) $key);

            if (preg_match($pattern, $working, $matches) !== 1) {
                $appended[] = $key . ' = ' . $formatted;
                continue;
            }

            $current = self::unquote($matches[2]);
            if (self::sameValue($current, $value, $type)) {
                continue;
            }

            $result['changed'][$key] = ['from' => $current, 'to' => $formatted];
            $working = (string) preg_replace_callback(
                $pattern,
                static fn (array $line): string => $line[1] . $formatted . $line[3] . $line[4],
                $working
            );
        }

        if ($result['changed'] === [] && $appended === []) {
            $result['ok'] = true;

            return $result;
        }

        if ($appended !== []) {
            if (!str_ends_with($working, "\n") && !str_ends_with($working, "\r")) {
                $working .= $newline;
            }
            $working .= $newline . '# --- added by AGMP (keys missing from the shipped conf template) ---' . $newline
                . implode($newline, $appended) . $newline;
        }

        $payload = $working;

        $backup = $this->path . '.agmp.bak';
        if (@copy($this->path, $backup)) {
            $result['backup'] = $backup;
        }

        $temp = $this->path . '.agmp.tmp';
        if (@file_put_contents($temp, $payload) === false) {
            $result['error'] = 'not_writable';

            return $result;
        }

        if (!@rename($temp, $this->path)) {
            @unlink($temp);
            $result['error'] = 'not_writable';

            return $result;
        }

        $result['ok'] = true;
        $result['appended'] = array_map(static fn (string $line): string => explode(' = ', $line, 2)[0], $appended);

        return $result;
    }

    public static function formatValue(mixed $value, string $type): string
    {
        return match ($type) {
            'bool' => self::toBool($value) ? '1' : '0',
            'int' => (string) self::toInt($value),
            'float' => self::formatFloat(self::toFloat($value)),
            default => self::quote((string) $value),
        };
    }

    private static function formatFloat(float $value): string
    {
        $text = rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
        if ($text === '' || $text === '-') {
            $text = '0';
        }

        return $text;
    }

    /**
     * Matches exactly one `Key = value` line, capturing the prefix, the raw value, the
     * trailing whitespace and the line ending so a replacement can keep all three.
     */
    private static function linePattern(string $key): string
    {
        return '/^([ \t]*' . preg_quote($key, '/') . '[ \t]*=[ \t]*)(.*?)([ \t]*)(\r\n|\n|\r|$)/m';
    }

    private static function sameValue(string $current, mixed $value, string $type): bool
    {
        return match ($type) {
            'bool' => (self::toBool($current) ? 1 : 0) === (self::toBool($value) ? 1 : 0),
            'int' => self::toInt($current) === self::toInt($value),
            'float' => abs(self::toFloat($current) - self::toFloat($value)) < 0.00005,
            default => $current === trim((string) $value),
        };
    }

    private static function unquote(string $value): string
    {
        $value = trim($value);
        if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
            return stripcslashes(substr($value, 1, -1));
        }

        return $value;
    }

    private static function quote(string $value): string
    {
        return '"' . addcslashes($value, "\\\"") . '"';
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $text = strtolower(trim((string) $value));

        return in_array($text, ['1', 'true', 'on', 'yes'], true);
    }

    private static function toInt(mixed $value): int
    {
        return (int) round((float) (is_bool($value) ? (int) $value : $value));
    }

    private static function toFloat(mixed $value): float
    {
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        return (float) $value;
    }

    public static function assertWritable(string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            throw new RuntimeException('directory missing: ' . $directory);
        }

        if (is_file($path) && !is_writable($path)) {
            throw new RuntimeException('file not writable: ' . $path);
        }
    }
}
