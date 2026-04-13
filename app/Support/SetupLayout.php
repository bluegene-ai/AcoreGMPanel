<?php

declare(strict_types=1);

namespace Acme\Panel\Support;

use Acme\Panel\Core\Lang;
use Acme\Panel\Core\View;

final class SetupLayout
{
    public static function wrap(string $content, array $data = []): string
    {
        return View::make('setup.layout', self::layoutData($content, $data));
    }

    private static function layoutData(string $content, array $data): array
    {
        $currentStep = self::resolveCurrentStep($data);
        $layoutPage = SetupPageData::layoutData($currentStep);

        return $data + [
            'content' => $content,
            '__layoutHead' => PageMetadata::present(
                is_array($data['__pageMeta'] ?? null) ? $data['__pageMeta'] : [],
                $data['title'] ?? null
            ),
            '__setupLayout' => [
                'localeCode' => str_replace('_', '-', (string) ($data['currentLocale'] ?? Lang::locale())),
                'currentStep' => $currentStep,
                'page' => $layoutPage,
            ],
        ];
    }

    private static function resolveCurrentStep(array $data): int
    {
        $step = (int) ($data['__setupStep'] ?? ($data['currentStep'] ?? ($_GET['step'] ?? 1)));

        return max(1, min(5, $step));
    }
}