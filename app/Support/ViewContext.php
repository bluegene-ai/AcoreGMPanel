<?php
declare(strict_types=1);

namespace Acme\Panel\Support;

final class ViewContext
{
    public static function templateData(): array
    {
        $__can = static function(string $capability): bool {
            return Auth::can($capability);
        };

        $__canAny = static function(array $capabilities) use ($__can): bool {
            foreach ($capabilities as $capability) {
                if ($__can((string) $capability))
                    return true;
            }

            return false;
        };

        $__canAll = static function(array $capabilities) use ($__can): bool {
            foreach ($capabilities as $capability) {
                if (!$__can((string) $capability))
                    return false;
            }

            return true;
        };

        return [
            '__can' => $__can,
            '__canAny' => $__canAny,
            '__canAll' => $__canAll,
        ];
    }
}