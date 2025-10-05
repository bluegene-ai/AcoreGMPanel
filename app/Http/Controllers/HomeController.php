<?php
/**
 * File: app/Http/Controllers/HomeController.php
 * Purpose: Defines class HomeController for the app/Http/Controllers module.
 * Classes:
 *   - HomeController
 * Functions:
 *   - index()
 */

namespace Acme\Panel\Http\Controllers;

use Acme\Panel\Core\{Config,Controller,Lang,Request,Response};
use Acme\Panel\Support\Markdown;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        [$readmePath, $readmeFile] = $this->resolveReadmePath();

        $readmeHtml = '';
        if ($readmePath !== null) {
            $content = @file_get_contents($readmePath);
            if ($content !== false) {
                $readmeHtml = Markdown::toHtml($content);
            }
        }

        return $this->view('home.index', [
            'title' => Config::get('app.name', 'Acore GM Panel'),
            'version' => Config::get('app.version', 'dev'),
            'readmeHtml' => $readmeHtml,
            'readmeSource' => $readmeFile,
        ]);
    }

    private function resolveReadmePath(): array
    {
        $root = dirname(__DIR__, 3);
        $locale = Lang::locale();
        $fallback = Lang::fallbackLocale();

        $candidates = $this->buildReadmeCandidates($locale);
        if ($fallback !== '' && $fallback !== $locale) {
            $candidates = array_merge($candidates, $this->buildReadmeCandidates($fallback));
        }
        $candidates[] = 'README.md';

        $seen = [];
        foreach ($candidates as $filename) {
            if ($filename === null || $filename === '') {
                continue;
            }
            if (isset($seen[$filename])) {
                continue;
            }
            $seen[$filename] = true;
            $fullPath = $root . DIRECTORY_SEPARATOR . $filename;
            if (is_file($fullPath) && is_readable($fullPath)) {
                return [$fullPath, $filename];
            }
        }

        return [null, null];
    }

    private function buildReadmeCandidates(string $locale): array
    {
        $trimmed = trim($locale);
        if ($trimmed === '') {
            return [];
        }

        $candidates = [];
        $normalizedHyphen = str_replace('_', '-', $trimmed);
        $candidates[] = 'README.' . $normalizedHyphen . '.md';
        $candidates[] = 'README.' . $trimmed . '.md';
        $candidates[] = 'README.' . strtolower($trimmed) . '.md';
        $candidates[] = 'README.' . strtoupper($trimmed) . '.md';

        return array_values(array_unique($candidates));
    }
}

