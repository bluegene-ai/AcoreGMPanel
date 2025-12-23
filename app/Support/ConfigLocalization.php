<?php
/**
 * File: app/Support/ConfigLocalization.php
 * Purpose: Defines class ConfigLocalization for the app/Support module.
 * Classes:
 *   - ConfigLocalization
 * Functions:
 *   - localize()
 *   - localizeArray()
 */

namespace Acme\Panel\Support;

use Acme\Panel\Core\Lang;







class ConfigLocalization
{
    private const array KNOWN_SUFFIXES = [
        'label',
        'hint',
        'description',
        'summary',
        'name',
        'category',
        'placeholder',
        'title',
        'text',
    ];

    private static function looksUntranslated(string $translated, string $key): bool
    {
        return $translated === $key;
    }

    private static function humanizeKey(string $key): string
    {
        $parts = array_values(array_filter(explode('.', $key), static fn ($p) => $p !== ''));
        if (!$parts) {
            return $key;
        }

        $last = $parts[count($parts) - 1];
        $base = $last;

        if (in_array($last, self::KNOWN_SUFFIXES, true) && count($parts) >= 2) {
            $base = $parts[count($parts) - 2];
            if (ctype_digit($base) && count($parts) >= 3) {
                $base = $parts[count($parts) - 3] . ' ' . $parts[count($parts) - 2];
            }
        }

        $base = str_replace(['-', '_'], ' ', $base);
        $base = preg_replace('/([A-Za-z])([0-9]+)/', '$1 $2', $base) ?? $base;
        $base = trim($base);

        if ($base === '') {
            return $key;
        }

        return mb_strtoupper(mb_substr($base, 0, 1)) . mb_substr($base, 1);
    }



    public static function localize(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::localize($item);
            }
            return $value;
        }

        if (is_string($value) && strncmp($value, 'lang:', 5) === 0) {
            $langKey = substr($value, 5);
            $translated = Lang::get($langKey);
            if (is_string($translated) && self::looksUntranslated($translated, $langKey)) {
                return self::humanizeKey($langKey);
            }
            return $translated;
        }

        return $value;
    }




    public static function localizeArray(array $value): array
    {
        return self::localize($value);
    }
}

