<?php
/**
 * File: app/Support/Markdown.php
 * Purpose: Lightweight Markdown renderer for README display on the home page.
 */

declare(strict_types=1);

namespace Acme\Panel\Support;

final class Markdown
{
    public static function toHtml(string $markdown): string
    {
    $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $lines = explode("\n", $markdown);
        $output = [];
        $paragraphBuffer = [];
        $codeBuffer = [];
        $codeLanguage = '';
        $inCodeBlock = false;
        $inList = false;
        $lineCount = count($lines);

        for ($index = 0; $index < $lineCount; $index++) {
            $line = $lines[$index];
            $trimmed = rtrim($line);

            if ($inCodeBlock) {
                if (preg_match('/^\s*```/', $trimmed)) {
                    $output[] = self::renderCodeBlock($codeBuffer, $codeLanguage);
                    $codeBuffer = [];
                    $codeLanguage = '';
                    $inCodeBlock = false;
                } else {
                    $codeBuffer[] = $line;
                }
                continue;
            }

            if (preg_match('/^\s*```([A-Za-z0-9_-]+)?\s*$/', $trimmed, $matches)) {
                self::flushParagraph($paragraphBuffer, $output);
                if ($inList) {
                    $output[] = '</ul>';
                    $inList = false;
                }
                $inCodeBlock = true;
                $codeLanguage = isset($matches[1]) ? trim($matches[1]) : '';
                $codeBuffer = [];
                continue;
            }

            if (preg_match('/^\s*[-*]\s+(.+)$/', $line, $matches)) {
                if (!$inList) {
                    self::flushParagraph($paragraphBuffer, $output);
                    $output[] = '<ul>';
                    $inList = true;
                }
                $output[] = '<li>' . self::renderInline($matches[1]) . '</li>';
                continue;
            }

            if ($inList && !preg_match('/^\s*[-*]\s+/', $line)) {
                $output[] = '</ul>';
                $inList = false;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', ltrim($line), $matches)) {
                self::flushParagraph($paragraphBuffer, $output);
                $level = strlen($matches[1]);
                $text = trim($matches[2]);
                $output[] = sprintf('<h%d>%s</h%d>', $level, self::renderInline($text), $level);
                continue;
            }

            if (preg_match('/^\s*\|.*\|\s*$/', $trimmed)) {
                self::flushParagraph($paragraphBuffer, $output);
                if ($inList) {
                    $output[] = '</ul>';
                    $inList = false;
                }

                $tableLines = [];
                while ($index < $lineCount && preg_match('/^\s*\|.*\|\s*$/', rtrim($lines[$index]))) {
                    $tableLines[] = trim($lines[$index]);
                    $index++;
                }
                $index--;
                $tableHtml = self::renderTable($tableLines);
                if ($tableHtml !== '') {
                    $output[] = $tableHtml;
                }
                continue;
            }

            if (trim($line) === '') {
                self::flushParagraph($paragraphBuffer, $output);
                continue;
            }

            $paragraphBuffer[] = trim($line);
        }

        if ($inCodeBlock) {
            $output[] = self::renderCodeBlock($codeBuffer, $codeLanguage);
        }

        if ($inList) {
            $output[] = '</ul>';
        }

        self::flushParagraph($paragraphBuffer, $output);

        return implode("\n", array_filter($output, static fn ($item) => $item !== ''));
    }

    private static function flushParagraph(array &$buffer, array &$output): void
    {
        if ($buffer === []) {
            return;
        }
        $text = implode(' ', $buffer);
        $output[] = '<p>' . self::renderInline($text) . '</p>';
        $buffer = [];
    }

    private static function renderInline(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        $escaped = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', static function (array $matches): string {
            $label = $matches[1];
            $url = $matches[2];
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        }, $escaped) ?? $escaped;

        $escaped = preg_replace_callback('/`([^`]+)`/', static function (array $matches): string {
            return '<code>' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8') . '</code>';
        }, $escaped) ?? $escaped;

        $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $escaped) ?? $escaped;

        return $escaped;
    }

    private static function renderCodeBlock(array $lines, string $language): string
    {
        $code = implode("\n", $lines);
        $code = rtrim($code, "\n");
        $escaped = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $class = '';
        if ($language !== '') {
            $class = ' class="language-' . htmlspecialchars($language, ENT_QUOTES, 'UTF-8') . '"';
        }
        return '<pre><code' . $class . '>' . $escaped . '</code></pre>';
    }

    private static function renderTable(array $lines): string
    {
        if ($lines === []) {
            return '';
        }

        $headerCells = self::splitTableRow($lines[0]);
        $bodyStart = 1;
        if (isset($lines[1]) && self::isTableDivider($lines[1])) {
            $bodyStart = 2;
        }

        $rows = [];
        for ($i = $bodyStart; $i < count($lines); $i++) {
            $rows[] = self::splitTableRow($lines[$i]);
        }

        $html = ['<table class="markdown-table">', '<thead><tr>'];
        foreach ($headerCells as $cell) {
            $html[] = '<th>' . self::renderInline($cell) . '</th>';
        }
        $html[] = '</tr></thead>';

        if ($rows !== []) {
            $html[] = '<tbody>';
            foreach ($rows as $row) {
                $html[] = '<tr>';
                foreach ($row as $cell) {
                    $html[] = '<td>' . self::renderInline($cell) . '</td>';
                }
                $html[] = '</tr>';
            }
            $html[] = '</tbody>';
        }

        $html[] = '</table>';

        return implode('', $html);
    }

    private static function splitTableRow(string $line): array
    {
        $line = trim($line);
        $line = trim($line, '|');
        $parts = explode('|', $line);

        return array_map(static fn ($item) => trim($item), $parts);
    }

    private static function isTableDivider(string $line): bool
    {
        $line = trim($line);
        if ($line === '') {
            return false;
        }
        $line = trim($line, '|');
        $segments = explode('|', $line);
        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            if (!preg_match('/^:?[-]{3,}:?$/', $segment)) {
                return false;
            }
        }
        return true;
    }
}

