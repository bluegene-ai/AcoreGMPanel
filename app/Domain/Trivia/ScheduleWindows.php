<?php

declare(strict_types=1);

namespace Acme\Panel\Domain\Trivia;

use Acme\Panel\Domain\Support\ScheduleWindows as SharedScheduleWindows;

/**
 * 聊天答题的「定时启停」时间段解析。
 *
 * 解析逻辑已上移到 Acme\Panel\Domain\Support\ScheduleWindows（活动 Boss 也用同一份实现，
 * 避免两套规则漂移）；这里保留 Trivia 命名空间下的类名，控制器与视图无需改动。
 *
 * 语法（与 TriviaReward.lua 的 parseScheduleWindows 保持一致，改一边必须改另一边）：
 *   多段用 ; 分隔；不带星期前缀 = 每天；"1-5@08:00-09:00"、跨夜 "22:00-02:00"。
 *
 * @see \Acme\Panel\Domain\Support\ScheduleWindows
 */
final class ScheduleWindows extends SharedScheduleWindows
{
}
