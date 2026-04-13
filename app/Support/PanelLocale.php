<?php
declare(strict_types=1);

namespace Acme\Panel\Support;

use Acme\Panel\Core\Lang;

final class PanelLocale
{
    public static function jsLocaleForPage(?string $module): array
    {
        $locale = [
            'common' => self::commonJsLocale(),
        ];

        $bundles = array_values(array_unique(array_filter(ModuleAssets::jsLocaleBundlesForPage($module))));
        if ($bundles === [])
            return $locale;

        $locale['modules'] = [];
        foreach ($bundles as $bundle) {
            $locale['modules'][$bundle] = Lang::getArray(ModuleAssets::jsLocaleTranslationKey($bundle));
        }

        return $locale;
    }

    private static function commonJsLocale(): array
    {
        return [
            'loading' => Lang::get('app.js.common.loading'),
            'no_data' => Lang::get('app.js.common.no_data'),
            'search_placeholder' => Lang::get('app.js.common.search_placeholder'),
            'errors' => [
                'network' => Lang::get('app.js.common.errors.network'),
                'timeout' => Lang::get('app.js.common.errors.timeout'),
                'invalid_json' => Lang::get('app.js.common.errors.invalid_json'),
                'unknown' => Lang::get('app.js.common.errors.unknown'),
            ],
            'api' => [
                'errors' => [
                    'request_failed' => Lang::get('app.common.api.errors.request_failed'),
                    'request_failed_retry' => Lang::get('app.common.api.errors.request_failed_retry'),
                    'request_failed_message' => Lang::get('app.common.api.errors.request_failed_message'),
                    'request_failed_reason' => Lang::get('app.common.api.errors.request_failed_reason'),
                    'unknown' => Lang::get('app.common.api.errors.unknown'),
                ],
                'success' => [
                    'generic' => Lang::get('app.common.api.success.generic'),
                    'queued' => Lang::get('app.common.api.success.queued'),
                ],
            ],
            'actions' => [
                'close' => Lang::get('app.js.common.actions.close'),
                'confirm' => Lang::get('app.js.common.actions.confirm'),
                'cancel' => Lang::get('app.js.common.actions.cancel'),
                'retry' => Lang::get('app.js.common.actions.retry'),
            ],
            'yes' => Lang::get('app.js.common.yes'),
            'no' => Lang::get('app.js.common.no'),
        ];
    }
}