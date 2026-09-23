<?php

declare(strict_types=1);

namespace Acme\Panel\Domain\Trivia;

use RuntimeException;

/**
 * 聊天答题「定时启停」时间段解析（每天的时间段，例如 08:00-09:00）。
 *
 * 语法（与 TriviaReward.lua 里的 parseScheduleWindows 保持一致，改一边必须改另一边）：
 *   多段之间用 ; 或换行分隔；不带星期前缀 = 每天
 *   "08:00-09:00"                  每天 08:00-09:00
 *   "08:00-09:00, 20:00-22:00"     用逗号分隔也可以（片段里没有 @ 时逗号当分隔符）
 *   "1-5@08:00-09:00"              周一至周五（1=周一 … 7=周日，也认 mon-fri 与 一/日）
 *   "6,7@20:00-21:00"              周六、周日
 *   "22:00-02:00"                  跨夜（到次日凌晨 2 点）
 *
 * 面板侧负责"给出人话的错误"，真正执行（到点开关、结束当前题）在 Lua 的 tick 里。
 */
final class ScheduleWindows
{
    /** @var array<string,int> */
    private const DAY_NAMES = [
        'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7,
        '一' => 1, '二' => 2, '三' => 3, '四' => 4, '五' => 5, '六' => 6, '日' => 7, '天' => 7,
    ];

    /**
     * 解析成结构化时间段；非法片段被跳过（用于展示，不抛错）。
     *
     * @return array<int,array{from:int,to:int,days:array<int,bool>|null,text:string}>
     */
    public static function parse(string $raw): array
    {
        $windows = [];
        foreach (self::windows($raw) as [$days, $timeToken]) {
            $parsed = self::parseWindow($days, $timeToken);
            if ($parsed !== null) {
                $windows[] = $parsed;
            }
        }

        return $windows;
    }

    /**
     * 校验并归一化成写库用的文本；非法时抛 RuntimeException（reason 里是被拒绝的那一段）。
     */
    public static function normalize(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $parts = [];
        foreach (self::windows($raw) as [$days, $timeToken]) {
            $parsed = self::parseWindow($days, $timeToken);
            if ($parsed === null) {
                throw new RuntimeException($days === null ? $timeToken : ($days . '@' . $timeToken));
            }
            $parts[] = $parsed['text'];
        }

        return implode('; ', $parts);
    }

    /**
     * @return array<int,string> 归一化后的时间段文本
     */
    public static function describe(string $raw): array
    {
        $parts = [];
        foreach (self::parse($raw) as $window) {
            $parts[] = $window['text'];
        }

        return $parts;
    }

    /**
     * 把原始文本拆成 [星期掩码, 时间段] 组合。
     *
     * 规则（与 Lua 侧一致）：
     *   - 先用 ; 与换行切成段；
     *   - 段里有 @ 时，@ 之前是星期、之后是时间；时间部分再按逗号拆（共享同一组星期）
     *     —— 所以 "1-5@08:00-09:00, 20:00-22:00" 是"工作日两段"；
     *   - 段里没有 @ 时，整个段按逗号拆成多段（每天）。
     *
     * @return array<int,array{0:string|null,1:string}>
     */
    private static function windows(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[;\r\n]+/', $raw) ?: [] as $piece) {
            $piece = trim((string) $piece);
            if ($piece === '') {
                continue;
            }

            $dayPart = null;
            $timePart = $piece;
            if (str_contains($piece, '@')) {
                $position = strrpos($piece, '@');
                $dayPart = trim(substr($piece, 0, $position));
                $timePart = trim(substr($piece, $position + 1));
            }

            foreach (preg_split('/,+/', $timePart) ?: [] as $sub) {
                $sub = trim((string) $sub);
                if ($sub !== '') {
                    $out[] = [$dayPart, $sub];
                }
            }
        }

        return $out;
    }

    /**
     * @return array{from:int,to:int,days:array<int,bool>|null,text:string}|null
     */
    private static function parseWindow(?string $dayPart, string $timeToken): ?array
    {
        $days = null;
        if ($dayPart !== null) {
            $days = self::parseDaySet($dayPart);
            if ($days === null) {
                return null;
            }
        }

        $range = self::parseClockRange($timeToken);
        if ($range === null) {
            return null;
        }
        [$from, $to] = $range;
        if ($from === $to) {
            return null;
        }

        return [
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'text' => ($days !== null ? self::formatDaySet($days) . '@' : '') . self::formatRange($from, $to),
        ];
    }

    /**
     * @return array{0:int,1:int}|null
     */
    private static function parseClockRange(string $text): ?array
    {
        if (preg_match('/^(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})$/', trim($text), $m) !== 1) {
            return null;
        }

        $h1 = (int) $m[1];
        $m1 = (int) $m[2];
        $h2 = (int) $m[3];
        $m2 = (int) $m[4];
        if ($h1 > 23 || $h2 > 23 || $m1 > 59 || $m2 > 59) {
            return null;
        }

        return [$h1 * 60 + $m1, $h2 * 60 + $m2];
    }

    /**
     * @return array<int,bool>|null
     */
    private static function parseDaySet(string $text): ?array
    {
        $days = [];
        foreach (explode(',', $text) as $chunk) {
            $chunk = strtolower(str_replace(' ', '', trim($chunk)));
            if ($chunk === '') {
                continue;
            }

            $from = null;
            $to = null;
            if (str_contains($chunk, '-')) {
                [$a, $b] = explode('-', $chunk, 2);
                $from = self::dayNumber($a);
                $to = self::dayNumber($b);
            } else {
                $from = self::dayNumber($chunk);
                $to = $from;
            }

            if ($from === null || $to === null) {
                return null;
            }

            $day = $from;
            while (true) {
                $days[$day] = true;
                if ($day === $to) {
                    break;
                }
                $day = $day % 7 + 1;
            }
        }

        return $days === [] ? null : $days;
    }

    private static function dayNumber(string $token): ?int
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        if (ctype_digit($token)) {
            $number = (int) $token;

            return ($number >= 1 && $number <= 7) ? $number : null;
        }

        return self::DAY_NAMES[$token] ?? null;
    }

    /**
     * @param array<int,bool> $days
     */
    private static function formatDaySet(array $days): string
    {
        $numbers = array_map('intval', array_keys($days));
        sort($numbers);

        return implode(',', $numbers);
    }

    private static function formatRange(int $from, int $to): string
    {
        return sprintf('%02d:%02d-%02d:%02d', intdiv($from, 60), $from % 60, intdiv($to, 60), $to % 60);
    }
}
