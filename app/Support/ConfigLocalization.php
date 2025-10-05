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



    public static function localize(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::localize($item);
            }
            return $value;
        }

        if (is_string($value) && strncmp($value, 'lang:', 5) === 0) {
            return Lang::get(substr($value, 5));
        }

        return $value;
    }




    public static function localizeArray(array $value): array
    {
        return self::localize($value);
    }
}

