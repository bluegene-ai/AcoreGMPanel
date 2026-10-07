<?php
/**
 * File: app/Support/ItemTooltip.php
 * Purpose: 生成"鼠标悬停物品名"时弹出的物品属性卡（HTML）。
 *
 * 除物品编辑页外，面板里所有出现物品名的地方都用这张卡代替浏览器原生 title：
 * 原生 title 只能塞一行字，而玩家/GM 想知道的是"这件东西到底是什么"。
 *
 * 数据面：
 *   - 单行读取 item_template（主键查询），不做服务端缓存——编辑物品后马上悬停必须看到新值；
 *     客户端（panel.js）按 entry 缓存，同一页面同一物品最多请求一次。
 *   - 名称优先 locales_item 的本地化列，缺失时回落 item_template.name。
 *
 * stat_type 编号（核心 ItemModType）的含义不是猜的：本机 item_template 里大量物品的属性组合，
 * 与客户端宝石 DBC（GemProperties.dbc → SpellItemEnchantment.dbc 的 EffectArg）交叉验证后得到。
 * 例如 Delicate Cardinal Ruby(敏捷)=3、Bold Cardinal Ruby(力量)=4、Brilliant(智力)=5、
 * Sparkling(精神)=6、Solid(耐力)=7、Thick(防御)=12、Subtle(躲闪)=13、Flashing(招架)=14、
 * Rigid(命中)=31、Smooth(暴击)=32、Mystic(韧性)=35、Quick(急速)=36、Precise(精准)=37、
 * Bright(攻强)=38、Lustrous(五秒回蓝)=43、Fractured(护甲穿透)=44、Runed(法强)=45；
 * 再由 3.2.2/3.3.5 物品（Archus=法强45、Glorenzelg=力量4/耐力7/暴击32/精准37、
 * 永恒凛冬之盾=防御12/躲闪13/招架14/格挡15、敏锐暮光鳞片=护甲穿透44、PvP 法系=法术穿透47）
 * 补齐 15/18/20/21/30/39/48。语言表 app.item.tooltip.stats 只收录能确定的编号，
 * 其余退化成"未知属性 #n"，不猜。
 */

declare(strict_types=1);

namespace Acme\Panel\Support;

use Acme\Panel\Core\Database;
use Acme\Panel\Core\ItemMeta;
use Acme\Panel\Core\ItemQuality;
use Acme\Panel\Core\Lang;
use PDO;
use Throwable;

final class ItemTooltip
{
    /** 每个请求内最多解析一次 locales_item 的列集合（表可能根本不存在）。 */
    private static ?array $localeColumns = null;

    /** 同一请求内重复解析同一 entry（例如一次渲染多张卡）时的行缓存。 */
    private static array $rowCache = [];

    /** 服务端缓存按区服区分连接，避免切换区服后读到上一区的物品。 */
    private static ?int $boundServer = null;
    private static ?PDO $world = null;

    /**
     * 渲染属性卡的内层 HTML；物品不存在返回 null。
     *
     * @param int      $entry    物品 entry
     * @param int|null $serverId 目标区服，默认当前区服
     * @param bool     $linkHint 调用方渲染的是可点击的物品名时传 true，卡片底部会多一行"点开去哪儿"
     */
    public static function html(int $entry, ?int $serverId = null, bool $linkHint = false): ?string
    {
        $entry = (int) $entry;
        if ($entry <= 0) {
            return null;
        }

        $row = self::find($entry, $serverId);
        if ($row === null) {
            return null;
        }

        return self::render($row, $linkHint);
    }

    /** item_template 行（键名统一小写）；不存在返回 null。 */
    private static function find(int $entry, ?int $serverId = null): ?array
    {
        $entry = (int) $entry;
        if ($entry <= 0) {
            return null;
        }

        $serverId = $serverId ?? ServerContext::currentId();
        $cacheKey = $serverId . ':' . $entry;
        if (array_key_exists($cacheKey, self::$rowCache)) {
            return self::$rowCache[$cacheKey];
        }

        $pdo = self::world($serverId);
        $stmt = $pdo->prepare('SELECT * FROM item_template WHERE entry = :entry LIMIT 1');
        $stmt->execute([':entry' => $entry]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row) || $row === []) {
            return self::$rowCache[$cacheKey] = null;
        }

        $row = array_change_key_case($row, CASE_LOWER);
        $row['name'] = self::localizedName($pdo, $entry) ?? (string) ($row['name'] ?? '');

        return self::$rowCache[$cacheKey] = $row;
    }

    // ---------------------------------------------------------------- rendering

    private static function render(array $row, bool $linkHint): string
    {
        $quality = self::int($row, 'quality');
        $html = [];

        $html[] = '<div class="item-tooltip__name ' . htmlspecialchars(ItemQuality::css($quality), ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars(self::itemName($row), ENT_QUOTES, 'UTF-8') . '</div>';

        $meta = self::rows($row);
        if ($meta !== []) {
            $html[] = '<div class="item-tooltip__section">' . self::renderRows($meta) . '</div>';
        }

        $stats = self::statLines($row);
        if ($stats !== []) {
            $html[] = '<div class="item-tooltip__sep"></div>';
            $html[] = '<div class="item-tooltip__section item-tooltip__section--stats">';
            foreach ($stats as $line) {
                $html[] = '<div class="item-tooltip__stat">' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</div>';
            }
            $html[] = '</div>';
        }

        $description = trim((string) ($row['description'] ?? ''));
        if ($description !== '') {
            $html[] = '<div class="item-tooltip__sep"></div>';
            $html[] = '<div class="item-tooltip__desc">'
                . nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) . '</div>';
        }

        $html[] = '<div class="item-tooltip__footer">'
            . htmlspecialchars(self::footerText($row), ENT_QUOTES, 'UTF-8')
            . ($linkHint
                ? ' · ' . htmlspecialchars(Lang::get('app.item.tooltip.labels.open_in_manager'), ENT_QUOTES, 'UTF-8')
                : '')
            . '</div>';

        return implode('', $html);
    }

    /**
     * 属性卡的"元信息"部分：等级、类别、部位、绑定、价格这类一眼要看的字段。
     * @return array<int,array{label:string,value:string,muted:bool}>
     */
    private static function rows(array $row): array
    {
        $rows = [];
        $add = static function (string $label, string $value, bool $muted = false) use (&$rows): void {
            if ($value === '') {
                return;
            }
            $rows[] = ['label' => $label, 'value' => $value, 'muted' => $muted];
        };
        // 整行只有一句断言（"唯一"这类），右侧没有值。
        $addBare = static function (string $label, bool $muted = false) use (&$rows): void {
            $rows[] = ['label' => $label, 'value' => '', 'muted' => $muted];
        };

        $quality = self::int($row, 'quality');
        $add(
            Lang::get('app.item.tooltip.labels.quality'),
            ItemQuality::label($quality, false) . ' (' . $quality . ')'
        );

        $itemLevel = self::int($row, 'itemlevel');
        if ($itemLevel > 0) {
            $add(Lang::get('app.item.tooltip.labels.item_level'), (string) $itemLevel);
        }

        $classId = self::int($row, 'class');
        $subId = self::int($row, 'subclass');
        $add(
            Lang::get('app.item.tooltip.labels.class'),
            ItemMeta::className($classId) . ' / ' . ItemMeta::subclassName($classId, $subId)
        );

        $inventoryType = self::int($row, 'inventorytype');
        $slot = self::enumLabel('inventory_types', $inventoryType);
        if ($slot !== null) {
            $add(Lang::get('app.item.tooltip.labels.slot'), $slot);
        }

        $bonding = self::int($row, 'bonding');
        $bondingLabel = self::enumLabel('bonding_types', $bonding);
        if ($bondingLabel !== null) {
            $add(Lang::get('app.item.tooltip.labels.bonding'), $bondingLabel);
        }

        if (self::int($row, 'maxcount') === 1) {
            $addBare(Lang::get('app.item.tooltip.labels.unique'));
        }

        $damage = self::damageLine($row);
        if ($damage !== null) {
            $add(Lang::get('app.item.tooltip.labels.damage'), $damage);
        }

        $speed = self::speedLine($row);
        if ($speed !== null) {
            $add(Lang::get('app.item.tooltip.labels.speed'), $speed);
        }

        $dps = self::dpsLine($row);
        if ($dps !== null) {
            $add(Lang::get('app.item.tooltip.labels.dps'), $dps);
        }

        $armor = self::int($row, 'armor');
        if ($armor > 0) {
            $add(Lang::get('app.item.tooltip.labels.armor'), (string) $armor);
        }
        $block = self::int($row, 'block');
        if ($block > 0) {
            $add(Lang::get('app.item.tooltip.labels.block'), (string) $block);
        }

        foreach (self::resistanceLines($row) as $line) {
            $add($line['label'], $line['value']);
        }

        $requiredLevel = self::int($row, 'requiredlevel');
        if ($requiredLevel > 0) {
            $add(Lang::get('app.item.tooltip.labels.required_level'), (string) $requiredLevel);
        }

        $requiredSkill = self::int($row, 'requiredskill');
        if ($requiredSkill > 0) {
            $rank = self::int($row, 'requiredskillrank');
            $add(
                Lang::get('app.item.tooltip.labels.required_skill'),
                '#' . $requiredSkill . ($rank > 0 ? ' (' . $rank . ')' : '')
            );
        }

        $containerSlots = self::int($row, 'containerslots');
        if ($containerSlots > 0) {
            $add(Lang::get('app.item.tooltip.labels.container_slots'), (string) $containerSlots);
        }

        $stackable = self::int($row, 'stackable');
        if ($stackable > 1) {
            $add(Lang::get('app.item.tooltip.labels.stackable'), (string) $stackable);
        }

        $buyPrice = self::int($row, 'buyprice');
        if ($buyPrice > 0) {
            $add(Lang::get('app.item.tooltip.labels.buy_price'), self::money($buyPrice));
        }
        $sellPrice = self::int($row, 'sellprice');
        if ($sellPrice > 0) {
            $add(Lang::get('app.item.tooltip.labels.sell_price'), self::money($sellPrice));
        }

        return $rows;
    }

    /**
     * 属性加成行（+"值 名称"）。stat_type/stat_value 成对出现，type=0 视为空槽。
     * @return array<int,string>
     */
    private static function statLines(array $row): array
    {
        $lines = [];
        for ($i = 1; $i <= 10; $i++) {
            $type = self::int($row, 'stat_type' . $i);
            if ($type <= 0) {
                continue;
            }
            $value = self::int($row, 'stat_value' . $i);
            if ($value === 0) {
                continue;
            }

            $label = self::enumLabel('stats', $type)
                ?? Lang::get('app.item.tooltip.labels.unknown_stat', ['type' => $type]);

            $lines[] = ($value > 0 ? '+' : '') . $value . ' ' . $label;
        }

        return $lines;
    }

    private static function damageLine(array $row): ?string
    {
        $min = self::float($row, 'dmg_min1');
        $max = self::float($row, 'dmg_max1');
        if ($max <= 0) {
            return null;
        }

        $line = self::number($min) . ' - ' . self::number($max);
        $type = self::enumLabel('damage_types', self::int($row, 'dmg_type1'));
        if ($type !== null && self::int($row, 'dmg_type1') !== 0) {
            $line .= ' (' . $type . ')';
        }

        $min2 = self::float($row, 'dmg_min2');
        $max2 = self::float($row, 'dmg_max2');
        if ($max2 > 0) {
            $line .= ' + ' . self::number($min2) . ' - ' . self::number($max2);
            $type2 = self::enumLabel('damage_types', self::int($row, 'dmg_type2'));
            if ($type2 !== null && self::int($row, 'dmg_type2') !== 0) {
                $line .= ' (' . $type2 . ')';
            }
        }

        return $line;
    }

    private static function speedLine(array $row): ?string
    {
        $delay = self::int($row, 'delay');
        if ($delay <= 0) {
            return null;
        }

        return Lang::get('app.item.tooltip.labels.seconds', ['value' => self::number($delay / 1000, 2)]);
    }

    private static function dpsLine(array $row): ?string
    {
        $delay = self::int($row, 'delay');
        $max = self::float($row, 'dmg_max1');
        $min = self::float($row, 'dmg_min1');
        if ($delay <= 0 || $max <= 0) {
            return null;
        }

        $average = ($min + $max) / 2;
        return self::number($average / ($delay / 1000), 1);
    }

    /** @return array<int,array{label:string,value:string}> */
    private static function resistanceLines(array $row): array
    {
        $lines = [];
        foreach (['holy', 'fire', 'nature', 'frost', 'shadow', 'arcane'] as $school) {
            $value = self::int($row, $school . '_res');
            if ($value === 0) {
                continue;
            }

            $name = Lang::get('app.item.tooltip.resistance_types.' . $school);
            $lines[] = [
                'label' => Lang::get('app.item.tooltip.labels.resistance', ['name' => $name]),
                'value' => ($value > 0 ? '+' : '') . $value,
            ];
        }

        return $lines;
    }

    private static function renderRows(array $rows): string
    {
        $html = '';
        foreach ($rows as $row) {
            $classes = 'item-tooltip__line'
                . ($row['muted'] ? ' item-tooltip__line--muted' : '')
                . ($row['value'] === '' ? ' item-tooltip__line--bare' : '');
            $html .= '<div class="' . $classes . '">'
                . '<span class="item-tooltip__label">' . htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') . '</span>'
                . ($row['value'] === ''
                    ? ''
                    : '<span class="item-tooltip__value">' . htmlspecialchars($row['value'], ENT_QUOTES, 'UTF-8') . '</span>')
                . '</div>';
        }

        return $html;
    }

    private static function footerText(array $row): string
    {
        $entry = self::int($row, 'entry');
        $codes = self::int($row, 'class') . ' / ' . self::int($row, 'subclass');

        return Lang::get('app.item.tooltip.labels.entry') . ' ' . $entry
            . ' · ' . Lang::get('app.item.tooltip.labels.codes') . ' ' . $codes;
    }

    private static function itemName(array $row): string
    {
        $name = trim((string) ($row['name'] ?? ''));

        return $name !== '' ? $name : ('#' . self::int($row, 'entry'));
    }

    // ---------------------------------------------------------------- helpers

    /**
     * 语言表里查枚举名；查不到返回 null，由调用方决定怎么退化（不猜）。
     */
    private static function enumLabel(string $section, int $id): ?string
    {
        if ($id <= 0 && $section !== 'damage_types') {
            return null;
        }

        $map = Lang::getArray('app.item.tooltip.' . $section);
        $label = $map[$id] ?? null;

        return is_string($label) && $label !== '' ? $label : null;
    }

    /** 铜 → 金银铜（单位文案走语言表，英文区是 g/s/c）。 */
    private static function money(int $copper): string
    {
        $copper = max(0, $copper);
        $gold = intdiv($copper, 10000);
        $silver = intdiv($copper % 10000, 100);
        $left = $copper % 100;

        $goldUnit = Lang::get('app.item.tooltip.labels.money_gold');
        $silverUnit = Lang::get('app.item.tooltip.labels.money_silver');
        $copperUnit = Lang::get('app.item.tooltip.labels.money_copper');

        $parts = [];
        if ($gold > 0) {
            $parts[] = $gold . $goldUnit;
        }
        if ($silver > 0) {
            $parts[] = $silver . $silverUnit;
        }
        if ($left > 0 || $parts === []) {
            $parts[] = $left . $copperUnit;
        }

        return implode(' ', $parts);
    }

    /**
     * locales_item 的本地化名称。表不存在（很多 AzerothCore 装完没有这张表）或列为空时返回 null，
     * 由调用方回落到 item_template.name——直接 JOIN 一张不存在的表会让整个查询抛异常。
     */
    private static function localizedName(PDO $pdo, int $entry): ?string
    {
        $columns = self::localeColumns($pdo);
        if ($columns === []) {
            return null;
        }

        $parts = [];
        foreach ($columns as $column) {
            $parts[] = 'NULLIF(`' . $column . '`, \'\')';
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT COALESCE(' . implode(', ', $parts) . ') AS localized_name
                 FROM locales_item WHERE entry = :entry LIMIT 1'
            );
            $stmt->execute([':entry' => $entry]);
            $name = $stmt->fetchColumn();
        } catch (Throwable $e) {
            return null;
        }

        if (!is_string($name)) {
            return null;
        }

        $name = trim($name);

        return $name === '' ? null : $name;
    }

    /** @return array<int,string> 可用的 name_loc* 列，按面板惯用优先级排序。 */
    private static function localeColumns(PDO $pdo): array
    {
        if (self::$localeColumns !== null) {
            return self::$localeColumns;
        }

        $available = [];
        try {
            $stmt = $pdo->prepare(
                'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME LIKE :c'
            );
            $stmt->execute([':t' => 'locales_item', ':c' => 'name_loc%']);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $column) {
                if (is_string($column) && preg_match('/^name_loc[0-9]+$/', $column) === 1) {
                    $available[] = $column;
                }
            }
        } catch (Throwable $e) {
            $available = [];
        }

        $ordered = [];
        foreach (['name_loc4', 'name_loc8', 'name_loc5', 'name_loc6', 'name_loc7'] as $preferred) {
            if (in_array($preferred, $available, true)) {
                $ordered[] = $preferred;
            }
        }
        foreach ($available as $column) {
            if (!in_array($column, $ordered, true)) {
                $ordered[] = $column;
            }
        }

        return self::$localeColumns = $ordered;
    }

    private static function world(int $serverId): PDO
    {
        if (self::$world === null || self::$boundServer !== $serverId) {
            self::$world = Database::forServer($serverId, 'world');
            self::$boundServer = $serverId;
        }

        return self::$world;
    }

    private static function int(array $row, string $key): int
    {
        $value = $row[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    private static function float(array $row, string $key): float
    {
        $value = $row[$key] ?? 0;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private static function number(float $value, int $decimals = 0): string
    {
        return number_format($value, $decimals, '.', '');
    }
}
