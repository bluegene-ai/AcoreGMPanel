<?php

declare(strict_types=1);

namespace Acme\Panel\Domain\Trivia;

use Acme\Panel\Domain\Support\ScheduleWindows as SharedScheduleWindows;

/**
 * File: app/Domain/Trivia/ScheduleWindows.php
 * Purpose: 聊天答题的「定时启停」时间段解析：解析逻辑在 Domain\Support\ScheduleWindows，
 * 这里保留 Trivia 命名空间下的类名，控制器与视图无需改动。
 * 语法与 TriviaReward.lua 的 parseScheduleWindows 保持一致，改一边必须改另一边。
 * @see \Acme\Panel\Domain\Support\ScheduleWindows
 */
final class ScheduleWindows extends SharedScheduleWindows
{
}
