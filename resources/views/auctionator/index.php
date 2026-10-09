<?php
/**
 * File: resources/views/auctionator/index.php
 * Purpose: mod-auctionator (拍卖机器人) management page: status / settings / item policy / GM actions.
 */

$snapshot = is_array($auctionator ?? null) ? $auctionator : [];
$paths = is_array($snapshot['paths'] ?? null) ? $snapshot['paths'] : [];
$conf = is_array($snapshot['conf'] ?? null) ? $snapshot['conf'] : [];
$fields = is_array($snapshot['fields'] ?? null) ? $snapshot['fields'] : [];
$groups = is_array($snapshot['groups'] ?? null) ? $snapshot['groups'] : [];
$listings = is_array($snapshot['listings'] ?? null) ? $snapshot['listings'] : [];
$market = is_array($snapshot['market'] ?? null) ? $snapshot['market'] : [];
$policy = is_array($snapshot['policy'] ?? null) ? $snapshot['policy'] : [];
$listingRows = is_array($snapshot['listing_rows'] ?? null) ? $snapshot['listing_rows'] : [];
$listingItems = is_array($listingRows['rows'] ?? null) ? $listingRows['rows'] : [];
$sales = is_array($snapshot['sales'] ?? null) ? $snapshot['sales'] : [];
$saleRowPage = is_array($snapshot['sale_rows'] ?? null) ? $snapshot['sale_rows'] : [];
$saleItems = is_array($saleRowPage['rows'] ?? null) ? $saleRowPage['rows'] : [];
$logEntries = is_array($snapshot['log'] ?? null) ? $snapshot['log'] : [];
$warnings = is_array($snapshot['warnings'] ?? null) ? $snapshot['warnings'] : [];
$notes = is_array($snapshot['notes'] ?? null) ? $snapshot['notes'] : [];
$typed = is_array($conf['typed'] ?? null) ? $conf['typed'] : [];
$tables = is_array($policy['tables'] ?? null) ? $policy['tables'] : [];

$capabilities = $__pageCapabilities ?? [
    'view' => $__can('auctionator.view'),
    'manage' => $__can('auctionator.manage'),
    'control' => $__can('auctionator.control'),
    'content_view' => $__can('content.view'),
];
$__pageCapabilities = $capabilities;
$canManage = (bool) ($capabilities['manage'] ?? false);
$canControl = (bool) ($capabilities['control'] ?? false);
// 物品名深链到物品管理页的编辑入口：目标页同样以 content.view 为门槛，没权限就只给纯文本，
// 免得给出一个必然被拒的链接。
$canEditContent = (bool) ($capabilities['content_view'] ?? $__can('content.view'));
$supported = (bool) ($notes['supported'] ?? true);
// 策略表"读不到"的三种说法要分开：缺表（该执行 SQL）/ 本区没部署 / 连不上库。
$policyTableKey = $supported
    ? 'table_missing'
    : (($notes['support_reason'] ?? '') === 'db_unreachable' ? 'table_unavailable' : 'table_not_deployed');
$capabilityNotice = $canManage ? null : __('app.common.capabilities.read_only');

// 页面里所有铜币数值统一按"金 / 银 / 铜"显示（单位取自语言文件，英文界面是 g/s/c）。
// 两个可编辑的价格框仍然收铜币（api/listing 与模块命令都按铜币走），换算值显示在输入框旁边。
$moneyUnit = static function (string $unit): string {
    return (string) __('app.auctionator.money.' . $unit);
};
$formatMoney = static function (int $copper) use ($moneyUnit): string {
    $copper = max(0, $copper);

    return intdiv($copper, 10000) . $moneyUnit('gold')
        . intdiv($copper % 10000, 100) . $moneyUnit('silver')
        . ($copper % 100) . $moneyUnit('copper');
};
$formatTime = static function (int $timestamp): string {
    return $timestamp > 0 ? date('Y-m-d H:i', $timestamp) : '--';
};
$toneClass = static function (string $tone): string {
    return 'au-badge au-badge--' . preg_replace('/[^a-z]/', '', strtolower($tone));
};
$houseLabel = static function (int $house): string {
    return match ($house) {
        2 => __('app.auctionator.houses.alliance'),
        6 => __('app.auctionator.houses.horde'),
        7 => __('app.auctionator.houses.neutral'),
        default => '#' . $house,
    };
};
$modeLabel = static function (string $mode): string {
    return match ($mode) {
        'buyout' => __('app.auctionator.modes.buyout'),
        'bid' => __('app.auctionator.modes.bid'),
        default => __('app.auctionator.modes.legacy'),
    };
};
// GM 上架卡片要说明整组最终会挂上拍卖行的价格：每行存的参数会被解析成整组的有效起拍价/买断价。
// 仍处于模块 legacy 模式的行用本区的 Auctionator.Seller.BidOnly / BidStartModifier 解析（与 ".auctionator addlist" 一致）。
$gmListing = static function (array $row) use ($typed): array {
    $cap = 2147483647;
    $mode = (string) ($row['mode'] ?? 'legacy');
    $stack = max(1, (int) ($row['stack'] ?? 1));
    $price = max(0, (int) ($row['price'] ?? 0));
    $bid = max(0, (int) ($row['bid'] ?? 0));

    if ($mode === 'buyout') {
        $total = min($cap, $price * $stack);

        return ['mode' => 'buyout', 'start' => $total, 'buyout' => $total];
    }

    if ($mode === 'bid') {
        return [
            'mode' => 'bid',
            'start' => min($cap, $bid * $stack),
            'buyout' => $price > 0 ? min($cap, $price * $stack) : 0,
        ];
    }

    if ((int) ($typed['Auctionator.Seller.BidOnly'] ?? 0) === 1) {
        return ['mode' => 'legacy', 'start' => min($cap, $price * $stack), 'buyout' => 0];
    }

    $buyout = min($cap, $price * $stack);
    $modifier = max(0.0, min(1.0, (float) ($typed['Auctionator.Seller.BidStartModifier'] ?? 0.0)));

    return [
        'mode' => 'legacy',
        'start' => max(1, (int) round($buyout * (1.0 - $modifier))),
        'buyout' => $buyout,
    ];
};
$gmPriceCell = static function (int $copper) use ($formatMoney): string {
    if ($copper <= 0) {
        return '<span class="muted small">—</span>';
    }

    // 原始铜币留在 title 里：上架表单填的就是这个数，排查"价格不对"时需要它。
    return '<span title="' . htmlspecialchars(__('app.auctionator.money.raw_title', ['copper' => $copper]), ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($formatMoney($copper), ENT_QUOTES, 'UTF-8') . '</span>';
};
// 行内价格输入框的换算提示（JS 按输入实时刷新，初始值由服务端渲染）。
// 单独成行（.au-hint-line）：与输入框并排会把两列价格各撑宽约 90px，
// 那部分宽度正是这张表溢出的来源。
$copperHint = static function (string $field, int $copper) use ($formatMoney): string {
    return '<span class="muted small au-hint-line" data-au-copper-hint="' . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') . '">'
        . ($copper > 0 ? htmlspecialchars($formatMoney($copper), ENT_QUOTES, 'UTF-8') : '') . '</span>';
};
// guid -> 角色链接；角色行不存在（被删号）时退化成"角色 #N（已不存在）"，仍然可点。
$characterCell = static function (int $guid, string $name): string {
    if ($guid <= 0) {
        return '<span class="muted small">—</span>';
    }

    $link = $name !== ''
        ? character_link($guid, $name)
        : '<a href="' . htmlspecialchars(character_view_url($guid), ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars((string) __('app.auctionator.character_missing', ['guid' => $guid]), ENT_QUOTES, 'UTF-8') . '</a>';

    return $link . ' <span class="muted small">#' . $guid . '</span>';
};
$gmOwnerLabel = static function (int $owner): string {
    return $owner > 0 ? (string) $owner : __('app.auctionator.policy.owner_bot');
};
// 筛选卡要一眼看懂，所以物品类别与品质在这里取本地化名；模块自带的标签表是英文，仅作兜底。
$typeLabel = static function (int $class, string $fallback): string {
    return (string) __('app.auctionator.types.' . $class, [], $fallback !== '' ? $fallback : ('#' . $class));
};
$qualityLabel = static function (int $quality): string {
    return (string) __('app.auctionator.qualities.' . $quality, [], '#' . $quality);
};
// 物品名单元格不再在这里拼：用项目统一的 item_name_link()（bootstrap/helpers.php），
// 样式也只有一份（app-core.css 的 .item-name-link），别在本模块再定义一套。
// $canEditContent 决定给不给链接：没 content.view 就给同色的纯文本，而不是必然被拒的链接。
//
// 卡片标题：长解释挂成标题旁的 ⓘ，正文只保留必须当场看见的规则（"配额 0 = 不上架"这类），
// 与标题或分区说明重复的解释一律进 ⓘ，不在正文里说第二遍。
$cardTitle = static function (string $title, ?string $hint = null): string {
    return '<h3>' . htmlspecialchars($title)
        . ($hint !== null && trim($hint) !== '' ? panel_hint($hint) : '')
        . '</h3>';
};
// 类别 / 子类改成下拉：让 GM 从"4 / 7"这种数字里认物品类型不现实，ItemMeta 本来就有本地化名。
// 子类依赖类别，所以整张表交给前端做级联（服务端先渲染默认类别的那一份，禁 JS 也能用）。
$classOptions = \Acme\Panel\Core\ItemMeta::classes();
$subclassMap = [];
foreach (array_keys($classOptions) as $classIdForMap) {
    $subclassMap[$classIdForMap] = \Acme\Panel\Core\ItemMeta::subclassesOf($classIdForMap);
}
// 表格里的子类别名查面板的本地化表（app.item.meta.subclasses），与上面两个下拉同源；
// 模块自己的标签表只有英文（"item enhancement" 这种），只在面板查不到时兜底。
// 这里直接用 subclassName() 而不是从 $subclassMap 里取：模块表里可能有面板子类别枚举之外的
// 旧行（武器 11/12 这种 Exotic 占位），它们的本地化名只在这张表里用得到。
$subclassLabel = static function (int $class, int $subclass, string $moduleLabel): string {
    $localized = \Acme\Panel\Core\ItemMeta::subclassName($class, $subclass);
    if ($localized !== '#' . $subclass) {
        return $localized;
    }

    return $moduleLabel !== '' ? $moduleLabel : '#' . $subclass;
};
// bonding 是"最低绑定门槛"，不是绑定类型枚举：0 = 不额外约束，1 = 拾取绑定（但 BoP 恒被排除），
// 2 = 装备绑定及以上，3 = 使用绑定及以上。见模块的 conf/mod_auctionator.conf.dist。
$bondingOptions = [
    0 => __('app.auctionator.policy.bonding_options.0'),
    1 => __('app.auctionator.policy.bonding_options.1'),
    2 => __('app.auctionator.policy.bonding_options.2'),
    3 => __('app.auctionator.policy.bonding_options.3'),
];
?>
<?php include __DIR__ . '/../components/page_header.php'; ?>
<?php include __DIR__ . '/../components/capability_notice.php'; ?>

<?php // 子类随类别级联，整张表交给前端；服务端已渲染默认类别那一份，禁 JS 也能提交 ?>
<script type="application/json" data-panel-json data-global="AU_SUBCLASSES"><?= json_encode(
    $subclassMap,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>

<div class="au-page" data-au-page
     data-au-supported="<?= (bool) ($notes['supported'] ?? true) ? '1' : '0' ?>"
     data-au-support-reason="<?= htmlspecialchars((string) ($notes['support_reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
  <?php
    // 分区按 GM 要做的事切，不按数据表切。
    // 一个 tab 可以由多个 <section data-au-panel="..."> 组成（JS 会把同名面板一起显示/隐藏），
    // 所以"精选清单"和消费它的"补货"按钮能同屏，即使它们在 DOM 里位于不同区域。
    // aria-controls 取 ID 列表，正好能指向同一个 tab 的多个面板。
  ?>
  <div class="au-tabs" role="tablist" aria-label="<?= htmlspecialchars(__('app.auctionator.tabs.label')) ?>">
    <button type="button" role="tab" id="au-tab-overview" class="au-tab au-tab--active" data-au-tab="overview" aria-selected="true" aria-controls="au-panel-overview" tabindex="0"><?= htmlspecialchars(__('app.auctionator.tabs.overview')) ?></button>
    <button type="button" role="tab" id="au-tab-live" class="au-tab" data-au-tab="live" aria-selected="false" aria-controls="au-panel-live" tabindex="-1"><?= htmlspecialchars(__('app.auctionator.tabs.live')) ?></button>
    <button type="button" role="tab" id="au-tab-stock" class="au-tab" data-au-tab="stock" aria-selected="false" aria-controls="au-panel-stock-1 au-panel-stock-2" tabindex="-1"><?= htmlspecialchars(__('app.auctionator.tabs.stock')) ?></button>
    <button type="button" role="tab" id="au-tab-filters" class="au-tab" data-au-tab="filters" aria-selected="false" aria-controls="au-panel-filters" tabindex="-1"><?= htmlspecialchars(__('app.auctionator.tabs.filters')) ?></button>
    <button type="button" role="tab" id="au-tab-settings" class="au-tab" data-au-tab="settings" aria-selected="false" aria-controls="au-panel-settings" tabindex="-1"><?= htmlspecialchars(__('app.auctionator.tabs.settings')) ?></button>
    <button type="button" role="tab" id="au-tab-maintenance" class="au-tab" data-au-tab="maintenance" aria-selected="false" aria-controls="au-panel-maintenance-1" tabindex="-1"><?= htmlspecialchars(__('app.auctionator.tabs.maintenance')) ?></button>
  </div>

  <div class="au-feedback panel-flash" id="auFeedback" hidden></div>

  <?php if ($warnings !== []): ?>
    <div class="au-warnings" id="auWarnings">
      <?php foreach ($warnings as $warning): ?>
        <div class="alert au-warning"><?= htmlspecialchars((string) $warning) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php // 「本区未部署」的提示由 snapshot 的 warnings 统一给出（带区服名），这里不重复 ?>

  <!-- ==== overview：本区机器人现在是什么状态 -->
  <section class="au-panel" data-au-panel="overview" id="au-panel-overview" role="tabpanel" aria-labelledby="au-tab-overview">
    <p class="au-panel__lead"><?= htmlspecialchars(__('app.auctionator.tabs.overview_lead')) ?></p>
    <?php // 三张只读统计卡在宽屏下一行摆开：各自的 kv 行很短，占一整行会让取值贴到卡片最右边 ?>
    <div class="au-grid au-grid--cards au-grid--overview">
      <div class="au-card">
        <?= $cardTitle(__('app.auctionator.master.title')) ?>
        <dl class="au-kv">
          <dt><?= htmlspecialchars(__('app.auctionator.fields.enabled')) ?></dt>
          <dd><span class="<?= $toneClass(((int) ($typed['Auctionator.Enabled'] ?? 0)) === 1 ? 'ok' : 'muted') ?>"><?= htmlspecialchars(((int) ($typed['Auctionator.Enabled'] ?? 0)) === 1 ? __('app.auctionator.state.on') : __('app.auctionator.state.off')) ?></span></dd>
          <?php // 买断模式：只留短取值，语义与对应的 conf 键都在 ⓘ 里（卡片只占 1/3 行宽） ?>
          <dt><?= htmlspecialchars(__('app.auctionator.fields.bid_only')) ?><?= panel_hint(__('app.auctionator.master.buyout_hint')) ?></dt>
          <dd>
            <?php $bidOnly = (int) ($typed['Auctionator.Seller.BidOnly'] ?? 0) === 1; ?>
            <span class="<?= $toneClass($bidOnly ? 'warn' : 'ok') ?>"><?= htmlspecialchars($bidOnly ? __('app.auctionator.state.buyout_off') : __('app.auctionator.state.buyout_on')) ?></span>
          </dd>
          <dt><?= htmlspecialchars(__('app.auctionator.fields.character_id')) ?></dt>
          <dd><?= htmlspecialchars((string) ($typed['Auctionator.CharacterId'] ?? '--')) ?></dd>
          <dt><?= htmlspecialchars(__('app.auctionator.fields.character_guid')) ?></dt>
          <dd><?= htmlspecialchars((string) ($typed['Auctionator.CharacterGuid'] ?? '--')) ?></dd>
          <dt><?= htmlspecialchars(__('app.auctionator.fields.auctions_per_run')) ?></dt>
          <dd><?= htmlspecialchars((string) ($typed['Auctionator.Seller.AuctionsPerRun'] ?? '--')) ?></dd>
          <dt><?= htmlspecialchars(__('app.auctionator.fields.cycle_minutes')) ?></dt>
          <dd><?= htmlspecialchars((string) ($typed['Auctionator.NeutralSeller.CycleMinutes'] ?? '--')) ?></dd>
          <dt><?= htmlspecialchars(__('app.auctionator.fields.max_auctions')) ?></dt>
          <dd><?= htmlspecialchars((string) ($typed['Auctionator.NeutralSeller.MaxAuctions'] ?? '--')) ?></dd>
        </dl>

        <?php if ($supported): ?>
          <?php // 按区一键启停：写本区 conf 的 Auctionator.Enabled + 发本区 .auctionator start|stop ?>
          <div class="au-power" data-au-power-panel>
            <strong class="au-power__title"><?= htmlspecialchars(__('app.auctionator.master.power_title')) ?></strong>
            <div class="au-power__actions">
              <button type="button" class="btn primary" data-au-power="start"<?= $canControl ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.master.power_start')) ?></button>
              <button type="button" class="btn outline danger" data-au-power="stop"<?= $canControl ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.master.power_stop')) ?></button>
            </div>
            <p class="muted small au-power__hint"><?= htmlspecialchars(__('app.auctionator.master.power_hint_short')) ?><?= panel_hint(__('app.auctionator.master.power_hint')) ?></p>
          </div>

          <?php // 买断模式快速开关：同样两步（写 conf + 运行时命令），下一轮卖家即生效 ?>
          <?php $buyoutOn = (int) ($typed['Auctionator.Seller.BidOnly'] ?? 0) !== 1; ?>
          <div class="au-power" data-au-buyout-panel>
            <strong class="au-power__title"><?= htmlspecialchars(__('app.auctionator.master.buyout_title')) ?></strong>
            <div class="au-power__actions">
              <button type="button" class="btn <?= $buyoutOn ? 'primary' : 'outline' ?>" data-au-buyout="1"<?= $canControl ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.master.buyout_on')) ?></button>
              <button type="button" class="btn <?= $buyoutOn ? 'outline' : 'primary' ?>" data-au-buyout="0"<?= $canControl ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.master.buyout_off')) ?></button>
            </div>
            <p class="muted small au-power__hint"><?= htmlspecialchars(__('app.auctionator.master.buyout_hint_short')) ?><?= panel_hint(__('app.auctionator.master.buyout_hint')) ?></p>
          </div>
        <?php endif; ?>
      </div>

      <div class="au-card">
        <?= $cardTitle(__('app.auctionator.listings.title')) ?>
        <?php if (!($listings['ok'] ?? false)): ?>
          <?php // 未部署区（not_deployed）与"读取失败"分开说，避免让人以为面板或库坏了 ?>
          <p class="muted small"><?= htmlspecialchars(__('app.auctionator.listings.' . ((string) ($listings['error'] ?? '') === 'not_deployed' ? 'not_deployed' : 'unavailable'))) ?></p>
        <?php else: ?>
          <dl class="au-kv">
            <dt><?= htmlspecialchars(__('app.auctionator.listings.total')) ?></dt>
            <dd><?= (int) ($listings['total'] ?? 0) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.listings.bot')) ?></dt>
            <dd><?= (int) ($listings['bot'] ?? 0) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.listings.player')) ?></dt>
            <dd><?= (int) ($listings['player'] ?? 0) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.listings.bid_only')) ?></dt>
            <dd><?= (int) ($listings['bid_only'] ?? 0) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.listings.with_buyout')) ?></dt>
            <dd><?= (int) ($listings['with_buyout'] ?? 0) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.listings.distinct_items')) ?></dt>
            <dd><?= (int) ($listings['distinct_items'] ?? 0) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.listings.expiry_window')) ?></dt>
            <dd><?= htmlspecialchars($formatTime((int) ($listings['min_expire'] ?? 0)) . ' → ' . $formatTime((int) ($listings['max_expire'] ?? 0))) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.listings.bot_mail')) ?></dt>
            <dd><?= (int) ($listings['bot_mail'] ?? 0) ?></dd>
          </dl>
          <div class="au-houses">
            <?php foreach (($listings['by_house'] ?? []) as $house => $count): ?>
              <span class="au-chip"><?= htmlspecialchars($houseLabel((int) $house)) ?>: <strong><?= (int) $count ?></strong></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="au-card">
        <?= $cardTitle(__('app.auctionator.market.title')) ?>
        <?php if (!($market['ok'] ?? false)): ?>
          <p class="alert au-warning small">
            <?= htmlspecialchars(__('app.auctionator.market.' . match ((string) ($market['error'] ?? '')) {
                'missing_table' => 'missing_table',
                'not_deployed' => 'not_deployed',
                default => 'unreadable',
            })) ?>
          </p>
        <?php else: ?>
          <dl class="au-kv">
            <dt><?= htmlspecialchars(__('app.auctionator.market.rows')) ?></dt>
            <dd><?= (int) ($market['rows'] ?? 0) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.market.items')) ?></dt>
            <dd><?= (int) ($market['distinct_items'] ?? 0) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.market.fresh_items')) ?></dt>
            <dd><?= (int) ($market['fresh_items'] ?? 0) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.market.newest')) ?></dt>
            <dd><?= htmlspecialchars((string) ($market['newest'] ?? '--') ?: '--') ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.market.oldest')) ?></dt>
            <dd><?= htmlspecialchars((string) ($market['oldest'] ?? '--') ?: '--') ?></dd>
          </dl>
          <?php if (($market['sources'] ?? []) !== []): ?>
            <div class="au-houses">
              <?php foreach (($market['sources'] ?? []) as $source => $count): ?>
                <span class="au-chip"><?= htmlspecialchars((string) $source) ?>: <strong><?= (int) $count ?></strong></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.market.hint_short')) ?><?= panel_hint(__('app.auctionator.market.hint')) ?></p>
      </div>
    </div>

    <div class="au-card au-card--wide">
      <?= $cardTitle(__('app.auctionator.log.title')) ?>
      <p class="muted small"><?= htmlspecialchars((string) ($paths['log_file'] ?? '')) ?></p>
      <?php if ($logEntries === []): ?>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.log.empty')) ?></p>
      <?php else: ?>
        <pre class="au-log"><?php foreach ($logEntries as $entry): ?><span class="au-log__line au-log__line--<?= htmlspecialchars((string) ($entry['tone'] ?? 'muted')) ?>"><?= htmlspecialchars((string) ($entry['line'] ?? '')) ?></span>
<?php endforeach; ?></pre>
      <?php endif; ?>
    </div>
  </section>

  <!-- ==== live（机器人现在在卖什么：逐条下架 / 改价，以及成交记录） -->
  <section class="au-panel" data-au-panel="live" id="au-panel-live" role="tabpanel" aria-labelledby="au-tab-live" hidden>
    <p class="au-panel__lead"><?= htmlspecialchars(__('app.auctionator.tabs.live_lead')) ?></p>
    <div class="au-card au-card--wide">
      <?= $cardTitle(__('app.auctionator.listing_detail.title'), __('app.auctionator.listing_detail.hint')) ?>

      <?php if (!($listings['ok'] ?? false)): ?>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.listings.' . ((string) ($listings['error'] ?? '') === 'not_deployed' ? 'not_deployed' : 'unavailable'))) ?></p>
      <?php elseif (($listingRows['error'] ?? '') === 'no_bot_guid'): ?>
        <?php // 没有 Auctionator.CharacterGuid 就没有"机器人拥有者"可筛，明细无从谈起 ?>
        <p class="alert au-warning small"><?= htmlspecialchars(__('app.auctionator.listing_detail.no_bot_guid')) ?></p>
      <?php else: ?>
        <div class="au-actions">
          <span class="muted small"><?= htmlspecialchars(__('app.auctionator.listing_detail.showing', [
              'shown' => count($listingItems),
              'total' => (int) ($listings['bot'] ?? 0),
          ])) ?></span>
          <form class="au-inline-form" method="get">
            <?php if ((int) ($current_server ?? 0) > 0): ?>
              <input type="hidden" name="server" value="<?= (int) $current_server ?>">
            <?php endif; ?>
            <?php // 三个列表共用一次 GET：把其他分页参数带上，翻这一页不会把那些页重置回第一页 ?>
            <input type="hidden" name="disabled_from" value="<?= (int) ($policy['disabled_from'] ?? 0) ?>">
            <input type="hidden" name="sale_from" value="<?= (int) ($saleRows['from'] ?? 0) ?>">
            <input class="au-input" type="number" min="0" name="listing_from" value="<?= (int) ($listingRows['from'] ?? 0) ?>" placeholder="<?= htmlspecialchars(__('app.auctionator.listing_detail.filter_from')) ?>">
            <button type="submit" class="btn btn-xs outline"><?= htmlspecialchars(__('app.auctionator.policy.filter_apply')) ?></button>
          </form>
        </div>

        <?php
          // 这里原来有一段散文解释"整组总价 vs 单价"——需要一段话解释的界面就是该修的界面。
          // 现在两个输入框各自在下方就地写出"整组 X（单价 Y）"（auctionator.js 随输入实时刷新），
          // 说明回到它该在的位置：贴着那个会让人搞错的输入框。
        ?>

        <div class="au-table-wrap">
          <table class="table table--au" data-au-searchable>
            <thead>
              <tr>
                <th scope="col" class="au-col-check">
                  <input type="checkbox" data-au-select-all aria-label="<?= htmlspecialchars(__('app.auctionator.listing_detail.select_all')) ?>">
                </th>
                <th scope="col"><?= htmlspecialchars(__('app.auctionator.listing_detail.auction_id')) ?></th>
                <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.item_name')) ?></th>
                <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.stack')) ?></th>
                <th scope="col"><?= htmlspecialchars(__('app.auctionator.listing_detail.startbid')) ?></th>
                <th scope="col"><?= htmlspecialchars(__('app.auctionator.listing_detail.buyout')) ?></th>
                <th scope="col"><?= htmlspecialchars(__('app.auctionator.listing_detail.current_bid')) ?></th>
                <th scope="col"><?= htmlspecialchars(__('app.auctionator.listing_detail.bidder')) ?></th>
                <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.house')) ?></th>
                <th scope="col"><?= htmlspecialchars(__('app.auctionator.listing_detail.expires')) ?></th>
                <th scope="col"></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($listingItems as $row): ?>
              <?php
                // 有出价的挂单既不能下架也不能改价：核心会把带出价的拍卖和出价人结算掉，
                // 那等于卖掉，而不是撤回。模块侧也会拒绝，这里先禁掉按钮以免误点。
                $hasBid = (bool) ($row['has_bid'] ?? false);
                $rowEditable = $canControl && $supported && !$hasBid;
              ?>
              <tr data-au-listing-row="<?= (int) ($row['id'] ?? 0) ?>" data-au-stack="<?= max(1, (int) ($row['stack'] ?? 1)) ?>">
                <td class="au-col-check">
                  <?php if ($rowEditable): ?>
                    <input type="checkbox" data-au-row-select value="<?= (int) ($row['id'] ?? 0) ?>"
                           aria-label="<?= htmlspecialchars(__('app.auctionator.listing_detail.select_row', ['id' => (int) ($row['id'] ?? 0)])) ?>">
                  <?php else: ?>
                    <?php // 有出价的挂单不能下架，也就不该能被勾选：给一个禁用的框并把原因放 title ?>
                    <input type="checkbox" disabled title="<?= htmlspecialchars(__('app.auctionator.listing_detail.bid_note')) ?>"
                           aria-label="<?= htmlspecialchars(__('app.auctionator.listing_detail.has_bid')) ?>">
                  <?php endif; ?>
                </td>
                <td><?= (int) ($row['id'] ?? 0) ?></td>
                <?php // 物品 ID 与物品名同格：单独一列只为放一个数字，多出来的宽度全是空白 ?>
                <td><span class="muted small"><?= (int) ($row['item'] ?? 0) ?></span> <?= item_name_link(
                    (int) ($row['item'] ?? 0),
                    (string) ($row['name'] ?? ''),
                    isset($row['quality']) && $row['quality'] !== null ? (int) $row['quality'] : null,
                    $canEditContent
                ) ?></td>
                <td><?= (int) ($row['stack'] ?? 0) ?></td>
                <td>
                  <?php if ($rowEditable): ?>
                    <input class="au-input au-input--tiny" type="number" min="1" data-au-field-name="startbid"
                           value="<?= (int) ($row['startbid'] ?? 0) ?>"
                           title="<?= htmlspecialchars(__('app.auctionator.money.raw_title', ['copper' => (int) ($row['startbid'] ?? 0)]), ENT_QUOTES, 'UTF-8') ?>">
                    <?= $copperHint('startbid', (int) ($row['startbid'] ?? 0)) ?>
                  <?php else: ?>
                    <?= $gmPriceCell((int) ($row['startbid'] ?? 0)) ?>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($rowEditable): ?>
                    <input class="au-input au-input--tiny" type="number" min="0" data-au-field-name="buyout"
                           value="<?= (int) ($row['buyout'] ?? 0) ?>"
                           title="<?= htmlspecialchars(__('app.auctionator.money.raw_title', ['copper' => (int) ($row['buyout'] ?? 0)]), ENT_QUOTES, 'UTF-8') ?>">
                    <?= $copperHint('buyout', (int) ($row['buyout'] ?? 0)) ?>
                  <?php else: ?>
                    <?= $gmPriceCell((int) ($row['buyout'] ?? 0)) ?>
                  <?php endif; ?>
                </td>
                <?php // 当前最高出价与出价人：核心只保留"当前最高"这一份，被超越的出价人和金额不会留在这里 ?>
                <td><?= $hasBid ? $gmPriceCell((int) ($row['bid'] ?? 0)) : '<span class="muted small">—</span>' ?></td>
                <td><?= $hasBid ? $characterCell((int) ($row['bidder'] ?? 0), (string) ($row['bidder_name'] ?? '')) : '<span class="muted small">—</span>' ?></td>
                <td><?= htmlspecialchars($houseLabel((int) ($row['house'] ?? 7))) ?></td>
                <td><?= htmlspecialchars($formatTime((int) ($row['expires'] ?? 0))) ?></td>
                <td class="au-table__actions">
                  <?php if ($hasBid): ?>
                    <span class="au-badge au-badge--warn" title="<?= htmlspecialchars(__('app.auctionator.listing_detail.bid_note')) ?>"><?= htmlspecialchars(__('app.auctionator.listing_detail.has_bid')) ?></span>
                  <?php elseif (!$canControl || !$supported): ?>
                    <?php // 只读：列不出来就算了，别给个按不动的按钮 ?>
                  <?php else: ?>
                    <?php // 改价按钮默认禁用（这一行没改过就没什么可改的）；auctionator.js 按 dirty 状态开关 ?>
                    <button type="button" class="btn btn-xs outline" data-au-listing="reprice" data-au-id="<?= (int) ($row['id'] ?? 0) ?>" disabled><?= htmlspecialchars(__('app.auctionator.listing_detail.reprice')) ?></button>
                    <button type="button" class="btn btn-xs" data-au-listing="reset" data-au-id="<?= (int) ($row['id'] ?? 0) ?>" hidden><?= htmlspecialchars(__('app.auctionator.listing_detail.reset')) ?></button>
                    <button type="button" class="btn btn-xs outline danger" data-au-listing="delist" data-au-id="<?= (int) ($row['id'] ?? 0) ?>"><?= htmlspecialchars(__('app.auctionator.listing_detail.delist')) ?></button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if ($listingItems === []): ?>
              <tr class="js-empty-row"><td colspan="11" class="muted small"><?= htmlspecialchars(__('app.auctionator.listing_detail.empty')) ?></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php // 多选批量条：选中任意行才出现（auctionator.js 控制 hidden）。批量下架逐条发命令，逐条进审计日志 ?>
        <div class="au-bulk-bar" data-au-bulk-bar hidden>
          <span class="muted small" data-au-bulk-count></span>
          <button type="button" class="btn btn-sm" data-au-bulk="save" disabled><?= htmlspecialchars(__('app.auctionator.listing_detail.bulk_save')) ?></button>
          <button type="button" class="btn btn-sm outline danger" data-au-bulk="delist" disabled><?= htmlspecialchars(__('app.auctionator.listing_detail.bulk_delist')) ?></button>
          <button type="button" class="btn btn-sm outline" data-au-bulk="clear"><?= htmlspecialchars(__('app.auctionator.listing_detail.bulk_clear')) ?></button>
        </div>

        <?php if (($listingRows['truncated'] ?? false) && (int) ($listingRows['next_from'] ?? 0) > 0): ?>
          <?php // 用 GET 表单而不是链接：要保持 server / disabled_from 这些同页参数 ?>
          <form class="au-inline-form" method="get">
            <?php if ((int) ($current_server ?? 0) > 0): ?>
              <input type="hidden" name="server" value="<?= (int) $current_server ?>">
            <?php endif; ?>
            <input type="hidden" name="disabled_from" value="<?= (int) ($policy['disabled_from'] ?? 0) ?>">
            <input type="hidden" name="sale_from" value="<?= (int) ($saleRowPage['from'] ?? 0) ?>">
            <input type="hidden" name="listing_from" value="<?= (int) ($listingRows['next_from'] ?? 0) ?>">
            <button type="submit" class="btn btn-xs outline"><?= htmlspecialchars(__('app.auctionator.listing_detail.next_page')) ?></button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php
      // 成交记录：模块自己写的 mod_auctionator_sale。这是"谁买走了机器人的东西、花了多少"的唯一来源
      // —— 核心不保留已结束的拍卖，它自己的 log_money 只覆盖 500 金以上的成交。只读。
    ?>
    <div class="au-card au-card--wide">
      <?= $cardTitle(__('app.auctionator.sales.title'), __('app.auctionator.sales.hint')) ?>

      <?php if (!$supported): ?>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.sales.not_deployed')) ?></p>
      <?php elseif (($sales['error'] ?? '') === 'missing'): ?>
        <?php // 旧版模块还没有这张表（SQL 更新没执行 / worldserver 没重编）：如实说，别显示成"没有成交" ?>
        <p class="alert au-warning small"><?= htmlspecialchars(__('app.auctionator.sales.missing_table')) ?></p>
      <?php elseif (!($sales['ok'] ?? false)): ?>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.sales.unavailable')) ?></p>
      <?php else: ?>
        <dl class="au-kv">
          <dt><?= htmlspecialchars(__('app.auctionator.sales.total')) ?></dt>
          <dd><?= (int) ($sales['rows'] ?? 0) ?></dd>
          <dt><?= htmlspecialchars(__('app.auctionator.sales.bot_sales')) ?></dt>
          <dd><?= (int) ($sales['bot'] ?? 0) ?></dd>
          <dt><?= htmlspecialchars(__('app.auctionator.sales.buyouts')) ?></dt>
          <dd><?= (int) ($sales['buyouts'] ?? 0) ?></dd>
          <dt><?= htmlspecialchars(__('app.auctionator.sales.buyers')) ?></dt>
          <dd><?= (int) ($sales['buyers'] ?? 0) ?></dd>
          <dt><?= htmlspecialchars(__('app.auctionator.sales.copper_total')) ?></dt>
          <dd><?= $gmPriceCell((int) ($sales['copper'] ?? 0)) ?></dd>
          <dt><?= htmlspecialchars(__('app.auctionator.sales.window')) ?></dt>
          <dd><?= htmlspecialchars(((string) ($sales['oldest'] ?? '') ?: '--') . ' → ' . ((string) ($sales['newest'] ?? '') ?: '--')) ?></dd>
        </dl>
        <?php
          // 只说实话：被排除掉多少行，其中多少行的来源模块从来没记过。
          //
          // 模块旧版（以及本区还没应用登记表那条 SQL 更新时）无法区分"自己创建的上架"和玩家挂单：
          // 它的判据是"押金 = 0"，而核心给玩家挂单算押金用的是
          // AH_MINIMUM_DEPOSIT × Rate.Auction.Deposit —— 该费率为 0 时玩家的押金也是 0。
          // 升级后写入的行带 module_listing，来源确定；旧行没有，就照实说"无法判断"。
          //
          // 只留一行"有多少条没算进来"，来龙去脉（押金判据、旧记录没有来源列）全在 ⓘ 里。
          $hiddenSales = (int) ($sales['hidden'] ?? 0);
          $hiddenUnknown = (int) ($sales['hidden_unknown'] ?? 0);
          $depositRateZero = ($sales['deposit_marker_sound'] ?? null) === false;
        ?>
        <?php if ($hiddenSales > 0): ?>
          <p class="muted small"><?= htmlspecialchars(__('app.auctionator.sales.hidden', ['count' => $hiddenSales])) ?>
            <?php if ($hiddenUnknown > 0): ?>
              <span class="au-badge au-badge--warn"><?= htmlspecialchars(__('app.auctionator.sales.hidden_unknown', ['count' => $hiddenUnknown])) ?></span>
              <?= panel_hint(
                  __('app.auctionator.sales.hidden_unknown_hint')
                  . ($depositRateZero ? ' ' . __('app.auctionator.sales.deposit_rate_zero') : '')
              ) ?>
            <?php endif; ?>
          </p>
        <?php endif; ?>

        <div class="au-actions">
          <span class="muted small"><?= htmlspecialchars(__('app.auctionator.sales.showing', [
              'shown' => count($saleItems),
              'total' => (int) ($sales['rows'] ?? 0),
          ])) ?></span>
          <form class="au-inline-form" method="get">
            <?php if ((int) ($current_server ?? 0) > 0): ?>
              <input type="hidden" name="server" value="<?= (int) $current_server ?>">
            <?php endif; ?>
            <?php // 与另外两个列表共用一次 GET：带上它们的分页参数，翻成交记录不会重置那两页 ?>
            <input type="hidden" name="disabled_from" value="<?= (int) ($policy['disabled_from'] ?? 0) ?>">
            <input type="hidden" name="listing_from" value="<?= (int) ($listingRows['from'] ?? 0) ?>">
            <input class="au-input" type="number" min="0" name="sale_from" value="<?= (int) ($saleRowPage['from'] ?? 0) ?>" placeholder="<?= htmlspecialchars(__('app.auctionator.sales.filter_from')) ?>">
            <button type="submit" class="btn btn-xs outline"><?= htmlspecialchars(__('app.auctionator.policy.filter_apply')) ?></button>
          </form>
        </div>

        <div class="au-table-wrap">
          <table class="table table--au" data-au-searchable>
            <thead>
              <tr>
                <th scope="col"><?= htmlspecialchars(__('app.auctionator.sales.time')) ?></th>
                <th scope="col"><?= htmlspecialchars(__('app.auctionator.sales.auction_id')) ?></th>
                <th scope="col"><?= htmlspecialchars(__('app.auctionator.sales.item')) ?></th>
                <th scope="col"><?= htmlspecialchars(__('app.auctionator.sales.count')) ?></th>
                <th scope="col"><?= htmlspecialchars(__('app.auctionator.sales.house')) ?></th>
                <th scope="col"><?= htmlspecialchars(__('app.auctionator.sales.seller')) ?></th>
                <th scope="col"><?= htmlspecialchars(__('app.auctionator.sales.buyer')) ?></th>
                <th scope="col"><?= htmlspecialchars(__('app.auctionator.sales.price')) ?></th>
                <th scope="col"><?= htmlspecialchars(__('app.auctionator.sales.kind')) ?></th>
                <th scope="col"><?= htmlspecialchars(__('app.auctionator.sales.cut')) ?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($saleItems as $row): ?>
              <?php $soldByBot = (bool) ($row['seller_is_bot'] ?? false); ?>
              <?php
                // 「指定角色」只在这行**确实**由模块创建时才成立——依据是模块在成交当时写下的
                // module_listing（来自它自己的上架登记表），不是从押金推断的：核心给玩家挂单算
                // 押金用的是 AH_MINIMUM_DEPOSIT × Rate.Auction.Deposit，本区该费率为 0 时玩家挂单
                // 的押金同样是 0，"押金 = 0" 证明不了任何事。未知来源只说"其他卖家"。
                $sellerKey = $soldByBot
                    ? 'app.auctionator.sales.seller_bot'
                    : (($row['module_listing'] ?? null) === true
                        ? 'app.auctionator.sales.seller_named'
                        : 'app.auctionator.sales.seller_other');
              ?>
              <tr data-au-sale-row="<?= (int) ($row['id'] ?? 0) ?>">
                <td><?= htmlspecialchars((string) ($row['sold_at'] ?? '') ?: '--') ?></td>
                <td><?= (int) ($row['auction_id'] ?? 0) ?></td>
                <td><?= (int) ($row['item'] ?? 0) ?> <?= item_name_link(
                    (int) ($row['item'] ?? 0),
                    (string) ($row['name'] ?? ''),
                    isset($row['quality']) && $row['quality'] !== null ? (int) $row['quality'] : null,
                    $canEditContent
                ) ?></td>
                <td><?= (int) ($row['count'] ?? 0) ?></td>
                <td><?= htmlspecialchars($houseLabel((int) ($row['house'] ?? 7))) ?></td>
                <td>
                  <?= $characterCell((int) ($row['seller'] ?? 0), (string) ($row['seller_name'] ?? '')) ?>
                  <span class="<?= $toneClass($soldByBot ? 'warn' : 'muted') ?>"><?= htmlspecialchars(__($sellerKey)) ?></span>
                </td>
                <td><?= $characterCell((int) ($row['buyer'] ?? 0), (string) ($row['buyer_name'] ?? '')) ?></td>
                <td><?= $gmPriceCell((int) ($row['price'] ?? 0)) ?></td>
                <td>
                  <?php $wasBuyout = (bool) ($row['is_buyout'] ?? false); ?>
                  <span class="<?= $toneClass($wasBuyout ? 'ok' : 'muted') ?>"><?= htmlspecialchars(__($wasBuyout ? 'app.auctionator.sales.kind_buyout' : 'app.auctionator.sales.kind_bid')) ?></span>
                </td>
                <td><?= $gmPriceCell((int) ($row['cut'] ?? 0)) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if ($saleItems === []): ?>
              <tr class="js-empty-row"><td colspan="10" class="muted small"><?= htmlspecialchars(__('app.auctionator.sales.empty')) ?></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if (($saleRowPage['truncated'] ?? false) && (int) ($saleRowPage['next_from'] ?? 0) > 0): ?>
          <form class="au-inline-form" method="get">
            <?php if ((int) ($current_server ?? 0) > 0): ?>
              <input type="hidden" name="server" value="<?= (int) $current_server ?>">
            <?php endif; ?>
            <input type="hidden" name="disabled_from" value="<?= (int) ($policy['disabled_from'] ?? 0) ?>">
            <input type="hidden" name="listing_from" value="<?= (int) ($listingRows['from'] ?? 0) ?>">
            <input type="hidden" name="sale_from" value="<?= (int) ($saleRowPage['next_from'] ?? 0) ?>">
            <button type="submit" class="btn btn-xs outline"><?= htmlspecialchars(__('app.auctionator.sales.next_page')) ?></button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>

  <!-- ==== settings -->
  <section class="au-panel" data-au-panel="settings" id="au-panel-settings" role="tabpanel" aria-labelledby="au-tab-settings" hidden>
    <p class="au-panel__lead"><?= htmlspecialchars(__('app.auctionator.tabs.settings_lead')) ?></p>
    <div class="au-card au-card--wide">
      <?= $cardTitle(__('app.auctionator.settings.title')) ?>
      <p class="muted small">
        <?= htmlspecialchars(__('app.auctionator.settings.path', ['path' => (string) ($paths['conf_file'] ?? '')])) ?>
        <?php if (!($conf['exists'] ?? false)): ?>
          <span class="au-badge au-badge--error"><?= htmlspecialchars(__('app.auctionator.settings.missing')) ?></span>
        <?php elseif (!($conf['writable'] ?? false)): ?>
          <span class="au-badge au-badge--error"><?= htmlspecialchars(__('app.auctionator.settings.read_only_file')) ?></span>
        <?php endif; ?>
      </p>
      <p class="alert au-note"><?= htmlspecialchars(__('app.auctionator.settings.restart_note_short')) ?><?= panel_hint(__('app.auctionator.settings.restart_note')) ?></p>

      <form id="auConfigForm" class="au-form" autocomplete="off">
        <?php foreach ($groups as $group): ?>
          <fieldset class="au-fieldset">
            <legend><?= htmlspecialchars(__('app.auctionator.groups.' . (string) ($group['key'] ?? 'other'))) ?></legend>
            <div class="au-form__grid">
              <?php foreach (($group['fields'] ?? []) as $key): ?>
                <?php
                  $spec = is_array($fields[$key] ?? null) ? $fields[$key] : [];
                  $type = (string) ($spec['type'] ?? 'string');
                  $label = __('app.auctionator.fields.' . (string) ($spec['label'] ?? 'value'));
                  // conf 里缺失的键回退到模块自带默认值（spec 声明了默认时），而不是显示空字段：
                  // 把服务端视为开启的键显示成"关闭"，比什么都不显示更糟。
                  $value = $typed[$key] ?? ($spec['default'] ?? '');
                  $disabled = $canManage && $supported ? '' : ' disabled';
                ?>
                <label class="au-form__row">
                  <span class="au-form__label" title="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $label) ?></span>
                  <?php if ($type === 'bool'): ?>
                    <select class="au-input" data-au-field="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>"<?= $disabled ?>>
                      <option value="1" <?= ((int) $value) === 1 ? 'selected' : '' ?>><?= htmlspecialchars(__('app.auctionator.state.on')) ?></option>
                      <option value="0" <?= ((int) $value) === 0 ? 'selected' : '' ?>><?= htmlspecialchars(__('app.auctionator.state.off')) ?></option>
                    </select>
                  <?php elseif ($type === 'int' || $type === 'float'): ?>
                    <input class="au-input" type="number" step="<?= $type === 'float' ? '0.01' : '1' ?>"
                           min="<?= htmlspecialchars((string) ($spec['min'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           max="<?= htmlspecialchars((string) ($spec['max'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           data-au-field="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>"
                           value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"<?= $disabled ?>>
                  <?php else: ?>
                    <input class="au-input" type="text" data-au-field="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>"
                           value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"<?= $disabled ?>>
                  <?php endif; ?>
                  <span class="au-form__key small muted"><?= htmlspecialchars((string) $key) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
        <?php endforeach; ?>

        <?php if ($canManage && $supported): ?>
          <div class="au-form__actions">
            <button type="submit" class="btn primary" id="auConfigSaveBtn"><?= htmlspecialchars(__('app.auctionator.settings.save')) ?></button>
            <span class="muted small"><?= htmlspecialchars(__('app.auctionator.settings.backup_note_short')) ?><?= panel_hint(__('app.auctionator.settings.backup_note')) ?></span>
          </div>
        <?php endif; ?>
      </form>
    </div>
  </section>

  <!-- ==== filters：机器人被允许卖什么 -->
  <section class="au-panel" data-au-panel="filters" id="au-panel-filters" role="tabpanel" aria-labelledby="au-tab-filters" hidden>
    <p class="au-panel__lead"><?= htmlspecialchars(__('app.auctionator.tabs.filters_lead')) ?></p>
    <div class="au-grid au-grid--cards au-grid--filters">
      <?php // 黑名单是长列表，独占一行（宽出来的宽度给物品名那列）；类别与品质并排，各自贴着表宽。 ?>
      <div class="au-card au-card--wide">
        <?= $cardTitle(__('app.auctionator.policy.disabled_title'), __('app.auctionator.policy.disabled_hint')) ?>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.disabled_hint_short')) ?></p>
        <?php $totals = is_array($policy['totals'] ?? null) ? $policy['totals'] : []; ?>
        <?php if ($tables['disabled_items']): ?>
          <div class="au-actions">
            <span class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.showing', ['shown' => count($policy['disabled'] ?? []), 'total' => (int) ($totals['disabled'] ?? 0)])) ?></span>
            <form class="au-inline-form" method="get">
              <?php if ((int) ($current_server ?? 0) > 0): ?>
                <input type="hidden" name="server" value="<?= (int) $current_server ?>">
              <?php endif; ?>
              <?php // 与挂单明细共用一次 GET：带上它的分页参数，翻这一页不会重置那一页 ?>
              <input type="hidden" name="listing_from" value="<?= (int) ($listingRows['from'] ?? 0) ?>">
              <input class="au-input" type="number" min="0" name="disabled_from" value="<?= (int) ($policy['disabled_from'] ?? 0) ?>" placeholder="<?= htmlspecialchars(__('app.auctionator.policy.filter_from')) ?>">
              <button type="submit" class="btn btn-xs outline"><?= htmlspecialchars(__('app.auctionator.policy.filter_apply')) ?></button>
            </form>
          </div>
        <?php endif; ?>
        <?php if (!$tables['disabled_items']): ?>
          <p class="alert au-warning small"><?= htmlspecialchars(__('app.auctionator.policy.' . $policyTableKey, ['table' => 'mod_auctionator_disabled_items'])) ?></p>
        <?php else: ?>
          <?php if ($canManage && $supported): ?>
            <form class="au-inline-form" data-au-policy="disabled_add">
              <?php // 按名字/ID 搜索选择物品：隐藏字段仍叫 item，表单与后端契约不变 ?>
              <div class="au-picker" data-au-picker>
                <input type="hidden" name="item" data-au-picker-value>
                <input class="au-input au-picker__search" type="text" data-au-picker-search
                       placeholder="<?= htmlspecialchars(__('app.auctionator.picker.search_item')) ?>"
                       autocomplete="off" role="combobox" aria-expanded="false" aria-autocomplete="list"
                       aria-label="<?= htmlspecialchars(__('app.auctionator.policy.item_id')) ?>">
                <ul class="au-picker__list" data-au-picker-list role="listbox" hidden></ul>
              </div>
              <button type="submit" class="btn btn-sm"><?= htmlspecialchars(__('app.auctionator.policy.add')) ?></button>
            </form>
          <?php endif; ?>
          <div class="au-table-wrap">
            <table class="table table--au" data-au-searchable>
              <thead><tr><th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.item_id')) ?></th><th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.item_name')) ?></th><th scope="col"></th></tr></thead>
              <tbody>
              <?php foreach (($policy['disabled'] ?? []) as $row): ?>
                <tr>
                  <td><?= (int) ($row['item'] ?? 0) ?></td>
                  <td><span<?= item_tooltip_attrs((int) ($row['item'] ?? 0), false, null) ?>><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></span></td>
                  <td class="au-table__actions">
                    <?php if ($canManage && $supported): ?>
                      <button type="button" class="btn btn-xs outline danger" data-au-policy="disabled_remove" data-au-item="<?= (int) ($row['item'] ?? 0) ?>"><?= htmlspecialchars(__('app.auctionator.policy.remove')) ?></button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (($policy['disabled'] ?? []) === []): ?>
                <tr class="js-empty-row"><td colspan="3" class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.empty')) ?></td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div class="au-card">
        <?= $cardTitle(__('app.auctionator.policy.itemclass_title'), __('app.auctionator.policy.itemclass_hint')) ?>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.itemclass_hint_short')) ?></p>
        <?php if ($tables['itemclass_config']): ?>
          <p class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.itemclass_totals', [
              'shown' => count($policy['itemclass'] ?? []),
              'total' => (int) ($totals['itemclass'] ?? 0),
              'listed' => count(array_filter($policy['itemclass'] ?? [], static fn (array $row): bool => (int) ($row['max_count'] ?? 0) > 0)),
          ])) ?></p>
        <?php endif; ?>
        <?php if (!$tables['itemclass_config']): ?>
          <p class="alert au-warning small"><?= htmlspecialchars(__('app.auctionator.policy.' . $policyTableKey, ['table' => 'mod_auctionator_itemclass_config'])) ?></p>
        <?php else: ?>
          <?php if ($canManage && $supported): ?>
            <form class="au-inline-form" data-au-policy="itemclass_save">
              <select class="au-input" name="class" required aria-label="<?= htmlspecialchars(__('app.auctionator.policy.class')) ?>" data-au-class-select>
                <?php foreach ($classOptions as $classId => $classLabel): ?>
                  <option value="<?= (int) $classId ?>"><?= htmlspecialchars((string) $classLabel) ?></option>
                <?php endforeach; ?>
              </select>
              <select class="au-input" name="subclass" required aria-label="<?= htmlspecialchars(__('app.auctionator.policy.subclass')) ?>" data-au-subclass-select>
                <?php // 默认类别（0）的子类；换类别时由 auctionator.js 按 AU_SUBCLASSES 重建 ?>
                <?php foreach (($subclassMap[0] ?? []) as $subId => $subLabel): ?>
                  <option value="<?= (int) $subId ?>"><?= htmlspecialchars((string) $subLabel) ?></option>
                <?php endforeach; ?>
              </select>
              <select class="au-input" name="bonding" aria-label="<?= htmlspecialchars(__('app.auctionator.policy.bonding')) ?>">
                <?php foreach ($bondingOptions as $bondingValue => $bondingLabel): ?>
                  <option value="<?= (int) $bondingValue ?>"><?= htmlspecialchars((string) $bondingLabel) ?></option>
                <?php endforeach; ?>
              </select>
              <input class="au-input" type="number" min="0" name="max_count" placeholder="<?= htmlspecialchars(__('app.auctionator.policy.max_count')) ?>" value="2">
              <input class="au-input" type="number" min="0" name="stack_count" placeholder="<?= htmlspecialchars(__('app.auctionator.policy.stack_count')) ?>" value="1">
              <button type="submit" class="btn btn-sm"><?= htmlspecialchars(__('app.auctionator.policy.save')) ?></button>
            </form>
          <?php endif; ?>
          <?php // 这张表只有 6 列，同样收缩到表格本身，不在卡片右侧留一条空边框 ?>
          <div class="au-table-wrap">
            <table class="table table--au" data-au-searchable>
              <thead>
                <tr>
                  <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.class')) ?></th>
                  <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.subclass')) ?></th>
                  <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.bonding')) ?></th>
                  <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.max_count')) ?></th>
                  <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.stack_count')) ?></th>
                  <th scope="col"></th>
                </tr>
              </thead>
              <tbody>
              <?php foreach (($policy['itemclass_by_class'] ?? []) as $group): ?>
                <?php
                  $classId = (int) ($group['class'] ?? 0);
                  $classRows = count($group['subclasses'] ?? []);
                  $classListed = (int) ($group['listed'] ?? 0);
                ?>
                <?php // 整类一行：一个下拉 + 一个按钮就能把这一类全部上架或全部停掉 ?>
                <tr class="au-table__group">
                  <td colspan="6">
                    <strong><?= htmlspecialchars($typeLabel($classId, (string) ($group['label'] ?? ''))) ?></strong>
                    <span class="muted small">#<?= $classId ?></span>
                    <span class="<?= $toneClass($classListed > 0 ? 'ok' : 'muted') ?>"><?= htmlspecialchars($classListed > 0 ? __('app.auctionator.state.listed') : __('app.auctionator.state.not_listed')) ?></span>
                    <span class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.itemclass_class_state', ['rows' => $classRows, 'listed' => $classListed])) ?></span>
                    <?php if ($canManage && $supported): ?>
                      <span class="au-table__actions">
                        <label class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.class_quota')) ?></label>
                        <select class="au-input au-input--tiny" data-au-field-name="max_count">
                          <option value="0"><?= htmlspecialchars(__('app.auctionator.state.not_listed')) ?> (0)</option>
                          <option value="1">1</option>
                          <option value="2">2</option>
                          <option value="3">3</option>
                          <option value="5">5</option>
                        </select>
                        <button type="button" class="btn btn-xs" data-au-policy="itemclass_class_save" data-au-class="<?= $classId ?>"><?= htmlspecialchars(__('app.auctionator.policy.apply_to_class')) ?></button>
                      </span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php if ($classRows === 0): ?>
                  <tr>
                    <td colspan="6" class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.class_no_rows_short')) ?><?= panel_hint(__('app.auctionator.policy.class_no_rows', [
                        'class' => $typeLabel($classId, (string) ($group['label'] ?? '')),
                    ])) ?></td>
                  </tr>
                <?php endif; ?>
                <?php foreach (($group['subclasses'] ?? []) as $row): ?>
                  <?php $rowListed = (int) ($row['max_count'] ?? 0) > 0; ?>
                  <tr data-au-itemclass="<?= (int) ($row['class'] ?? 0) ?>:<?= (int) ($row['subclass'] ?? 0) ?>">
                    <td></td>
                    <td class="au-cell-wrap"><?= htmlspecialchars($subclassLabel((int) ($row['class'] ?? 0), (int) ($row['subclass'] ?? 0), (string) ($row['subclass_label'] ?? ''))) ?> <span class="muted small">#<?= (int) ($row['subclass'] ?? 0) ?></span></td>
                    <td>
                      <?php if ($canManage && $supported): ?>
                        <input class="au-input au-input--tiny" type="number" min="0" max="3" data-au-field-name="bonding" value="<?= (int) ($row['bonding'] ?? 0) ?>">
                      <?php else: ?><?= (int) ($row['bonding'] ?? 0) ?><?php endif; ?>
                    </td>
                    <td>
                      <?php if ($canManage && $supported): ?>
                        <input class="au-input au-input--tiny" type="number" min="0" data-au-field-name="max_count" value="<?= (int) ($row['max_count'] ?? 0) ?>">
                      <?php else: ?><?= (int) ($row['max_count'] ?? 0) ?><?php endif; ?>
                      <span class="<?= $toneClass($rowListed ? 'ok' : 'muted') ?>"><?= htmlspecialchars($rowListed ? __('app.auctionator.state.listed') : __('app.auctionator.state.not_listed')) ?></span>
                    </td>
                    <td>
                      <?php if ($canManage && $supported): ?>
                        <input class="au-input au-input--tiny" type="number" min="0" data-au-field-name="stack_count" value="<?= (int) ($row['stack_count'] ?? 0) ?>">
                      <?php else: ?><?= (int) ($row['stack_count'] ?? 0) ?><?php endif; ?>
                    </td>
                    <td class="au-table__actions">
                      <?php if ($canManage && $supported): ?>
                        <button type="button" class="btn btn-xs outline" data-au-policy="itemclass_save" data-au-class="<?= (int) ($row['class'] ?? 0) ?>" data-au-subclass="<?= (int) ($row['subclass'] ?? 0) ?>"><?= htmlspecialchars(__('app.auctionator.policy.save')) ?></button>
                        <button type="button" class="btn btn-xs outline danger" data-au-policy="itemclass_delete" data-au-class="<?= (int) ($row['class'] ?? 0) ?>" data-au-subclass="<?= (int) ($row['subclass'] ?? 0) ?>"><?= htmlspecialchars(__('app.auctionator.policy.delete')) ?></button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <?php // 物品等级上限：conf 键 + 命令两步一个按钮（与买断开关同构） ?>
      <div class="au-card">
        <?= $cardTitle(__('app.auctionator.policy.max_item_level_title'), __('app.auctionator.policy.max_item_level_hint')) ?>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.max_item_level_hint_short')) ?></p>
        <?php $maxItemLevelValue = (int) ($typed['Auctionator.Seller.MaxItemLevel'] ?? 0); ?>
        <form class="au-inline-form" data-au-max-item-level>
          <input class="au-input au-input--tiny" type="number" min="0" max="10000" step="1" name="value"
                 value="<?= $maxItemLevelValue ?>"
                 aria-label="<?= htmlspecialchars(__('app.auctionator.policy.max_item_level_title')) ?>"
                 title="<?= htmlspecialchars((string) 'Auctionator.Seller.MaxItemLevel', ENT_QUOTES, 'UTF-8') ?>"
                 <?= $canControl && $supported ? '' : 'disabled' ?>>
          <button type="submit" class="btn btn-sm"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.policy.max_item_level_apply')) ?></button>
        </form>
        <p class="muted small">
          <?= htmlspecialchars(__('app.auctionator.policy.max_item_level_current', [
              'level' => $maxItemLevelValue === 0
                  ? __('app.auctionator.policy.max_item_level_off')
                  : (string) $maxItemLevelValue,
          ])) ?>
        </p>
      </div>

      <?php // 品质白名单：模块的候选查询直接读这张表，所以热生效、不必重启 ?>
      <div class="au-card">
        <?= $cardTitle(__('app.auctionator.policy.quality_title'), __('app.auctionator.policy.quality_hint')) ?>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.quality_hint_short')) ?></p>
        <?php if (!($tables['quality_config'] ?? false)): ?>
          <p class="alert au-warning small"><?= htmlspecialchars(__('app.auctionator.policy.quality_table_missing')) ?></p>
        <?php else: ?>
          <?php // 4 列、每格几个字：允许折行，卡片就能贴到并排栅格给的那点宽度 ?>
          <div class="au-table-wrap">
            <table class="table table--au table--au-wrap" data-au-searchable>
              <thead>
                <tr>
                  <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.quality')) ?></th>
                  <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.quality_items')) ?></th>
                  <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.quality_state')) ?></th>
                  <th scope="col"></th>
                </tr>
              </thead>
              <tbody>
              <?php foreach (($policy['quality'] ?? []) as $row): ?>
                <?php $qualityListed = (int) ($row['enabled'] ?? 1) === 1; ?>
                <tr>
                  <td><?= htmlspecialchars($qualityLabel((int) ($row['quality'] ?? 0))) ?> <span class="muted small">#<?= (int) ($row['quality'] ?? 0) ?></span></td>
                  <td><?= (int) ($row['items'] ?? 0) ?></td>
                  <td>
                    <span class="<?= $toneClass($qualityListed ? 'ok' : 'muted') ?>"><?= htmlspecialchars($qualityListed ? __('app.auctionator.state.listed') : __('app.auctionator.state.not_listed')) ?></span>
                    <?php if (!($row['configured'] ?? false)): ?>
                      <span class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.quality_default')) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="au-table__actions">
                    <?php if ($canManage && $supported): ?>
                      <button type="button" class="btn btn-xs outline<?= $qualityListed ? ' danger' : '' ?>" data-au-policy="quality_save" data-au-quality="<?= (int) ($row['quality'] ?? 0) ?>" data-au-enabled="<?= $qualityListed ? '0' : '1' ?>"><?= htmlspecialchars($qualityListed ? __('app.auctionator.policy.disable') : __('app.auctionator.policy.enable')) ?></button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ==== stock（续）：精选清单；补货与临时上架在同一个 tab 的后半段（见文件末尾） -->
  <section class="au-panel" data-au-panel="stock" id="au-panel-stock-1" role="tabpanel" aria-labelledby="au-tab-stock" hidden>
    <p class="au-panel__lead"><?= htmlspecialchars(__('app.auctionator.tabs.stock_lead')) ?></p>
    <div class="au-grid au-grid--cards">
      <div class="au-card au-card--wide">
        <?= $cardTitle(__('app.auctionator.policy.gm_title'), __('app.auctionator.policy.gm_hint')) ?>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.gm_hint_short')) ?></p>
        <?php if ($tables['gm_list']): ?>
          <p class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.gm_totals', [
              'shown' => count($policy['gm_list'] ?? []),
              'total' => (int) ($totals['gm_list'] ?? 0),
              'enabled' => (int) ($totals['gm_list_enabled'] ?? 0),
          ])) ?></p>
        <?php endif; ?>
        <?php if (!$tables['gm_list']): ?>
          <p class="alert au-warning small"><?= htmlspecialchars(__('app.auctionator.policy.' . $policyTableKey, ['table' => 'mod_auctionator_gm_list'])) ?></p>
        <?php elseif (!($tables['gm_list_mode'] ?? true)): ?>
          <p class="alert au-warning small"><?= htmlspecialchars(__('app.auctionator.policy.gm_columns_missing')) ?></p>
        <?php else: ?>
          <?php if ($canManage && $supported): ?>
            <form class="au-form au-form--gm" data-au-policy="gm_save" data-au-listing-form>
              <div class="au-form__grid">
                <label class="au-form__row au-form__row--wide">
                  <span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.item_id')) ?><?= panel_hint(__('app.auctionator.policy.gm_form_hint')) ?></span>
                  <div class="au-picker" data-au-picker>
                    <input type="hidden" name="item" data-au-picker-value>
                    <input class="au-input au-picker__search" type="text" data-au-picker-search
                           placeholder="<?= htmlspecialchars(__('app.auctionator.picker.search_item')) ?>"
                           autocomplete="off" role="combobox" aria-expanded="false" aria-autocomplete="list"
                           aria-label="<?= htmlspecialchars(__('app.auctionator.policy.item_id')) ?>">
                    <ul class="au-picker__list" data-au-picker-list role="listbox" hidden></ul>
                  </div>
                </label>
                <label class="au-form__row">
                  <span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.mode')) ?></span>
                  <select class="au-input" name="mode" required>
                    <option value=""><?= htmlspecialchars(__('app.auctionator.policy.mode_choose')) ?></option>
                    <option value="buyout"><?= htmlspecialchars(__('app.auctionator.modes.buyout_hint')) ?></option>
                    <option value="bid"><?= htmlspecialchars(__('app.auctionator.modes.bid_hint')) ?></option>
                  </select>
                </label>
                <label class="au-form__row" data-au-listing-field="bid" hidden>
                  <span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.bid_price')) ?></span>
                  <input class="au-input" type="number" min="1" name="bid" placeholder="500">
                </label>
                <label class="au-form__row" data-au-listing-field="price">
                  <span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.buyout_price')) ?></span>
                  <input class="au-input" type="number" min="0" name="price" placeholder="10000">
                </label>
                <label class="au-form__row">
                  <span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.stack')) ?></span>
                  <input class="au-input" type="number" min="1" name="stack" value="1">
                </label>
                <label class="au-form__row">
                  <span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.hours')) ?></span>
                  <input class="au-input" type="number" min="1" max="720" name="hours" value="48">
                </label>
                <label class="au-form__row">
                  <span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.house')) ?></span>
                  <select class="au-input" name="house">
                    <option value="7"><?= htmlspecialchars(__('app.auctionator.houses.neutral')) ?></option>
                    <option value="2"><?= htmlspecialchars(__('app.auctionator.houses.alliance')) ?></option>
                    <option value="6"><?= htmlspecialchars(__('app.auctionator.houses.horde')) ?></option>
                  </select>
                </label>
                <label class="au-form__row">
                  <span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.owner')) ?></span>
                  <input class="au-input" type="number" min="0" name="owner" value="0" placeholder="0">
                </label>
                <label class="au-form__row">
                  <span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.enabled')) ?></span>
                  <select class="au-input" name="enabled">
                    <option value="1"><?= htmlspecialchars(__('app.auctionator.state.on')) ?></option>
                    <option value="0"><?= htmlspecialchars(__('app.auctionator.state.off')) ?></option>
                  </select>
                </label>
              </div>
              <p class="muted small" data-au-listing-preview></p>
              <div class="au-form__actions">
                <button type="submit" class="btn btn-sm primary"><?= htmlspecialchars(__('app.auctionator.policy.save')) ?></button>
              </div>
            </form>
          <?php endif; ?>
          <div class="au-table-wrap">
            <table class="table table--au" data-au-searchable>
              <thead>
                <tr>
                  <?php // 物品 ID 与物品名同格，与挂单明细一致 ?>
                  <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.item_name')) ?></th>
                  <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.mode')) ?></th>
                  <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.bid_price')) ?></th>
                  <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.buyout_price')) ?></th>
                  <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.listing_totals')) ?></th>
                  <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.stack')) ?></th>
                  <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.hours')) ?></th>
                  <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.house')) ?></th>
                  <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.owner')) ?></th>
                  <th scope="col"><?= htmlspecialchars(__('app.auctionator.policy.enabled')) ?></th>
                  <th scope="col"></th>
                </tr>
              </thead>
              <tbody>
              <?php foreach (($policy['gm_list'] ?? []) as $row): ?>
                <?php $listing = $gmListing($row); ?>
                <tr>
                  <td><span class="muted small"><?= (int) ($row['item'] ?? 0) ?></span> <span<?= item_tooltip_attrs((int) ($row['item'] ?? 0), false, null) ?>><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></span></td>
                  <td>
                    <span class="au-badge au-badge--<?= $listing['mode'] === 'legacy' ? 'muted' : 'ok' ?>"><?= htmlspecialchars($modeLabel($listing['mode'])) ?></span>
                    <?php if ($listing['mode'] === 'legacy'): ?>
                      <?= panel_hint(__('app.auctionator.policy.legacy_note')) ?>
                    <?php endif; ?>
                  </td>
                  <td><?= $gmPriceCell((int) ($row['bid'] ?? 0)) ?></td>
                  <td><?= $gmPriceCell((int) ($row['price'] ?? 0)) ?></td>
                  <td>
                    <?= htmlspecialchars(__('app.auctionator.policy.totals_start')) ?>
                    <?= $gmPriceCell((int) $listing['start']) ?>
                    <br>
                    <?= htmlspecialchars(__('app.auctionator.policy.totals_buyout')) ?>
                    <?= $gmPriceCell((int) $listing['buyout']) ?>
                  </td>
                  <td><?= (int) ($row['stack'] ?? 0) ?></td>
                  <td><?= (int) ($row['hours'] ?? 0) ?></td>
                  <td><?= htmlspecialchars($houseLabel((int) ($row['house'] ?? 7))) ?></td>
                  <td><?= htmlspecialchars($gmOwnerLabel((int) ($row['owner'] ?? 0))) ?></td>
                  <td><span class="<?= $toneClass(((int) ($row['enabled'] ?? 0)) === 1 ? 'ok' : 'muted') ?>"><?= htmlspecialchars(((int) ($row['enabled'] ?? 0)) === 1 ? __('app.auctionator.state.on') : __('app.auctionator.state.off')) ?></span></td>
                  <td class="au-table__actions">
                    <?php if ($canManage && $supported): ?>
                      <button type="button" class="btn btn-xs outline" data-au-policy="gm_toggle" data-au-item="<?= (int) ($row['item'] ?? 0) ?>" data-au-enabled="<?= ((int) ($row['enabled'] ?? 0)) === 1 ? '0' : '1' ?>"><?= htmlspecialchars(((int) ($row['enabled'] ?? 0)) === 1 ? __('app.auctionator.policy.disable') : __('app.auctionator.policy.enable')) ?></button>
                      <button type="button" class="btn btn-xs outline danger" data-au-policy="gm_delete" data-au-item="<?= (int) ($row['item'] ?? 0) ?>"><?= htmlspecialchars(__('app.auctionator.policy.delete')) ?></button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (($policy['gm_list'] ?? []) === []): ?>
                <tr class="js-empty-row"><td colspan="11" class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.empty')) ?></td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ==== maintenance：只读查询 / 运行时开关 / 市场数据维护 -->
  <section class="au-panel" data-au-panel="maintenance" id="au-panel-maintenance-1" role="tabpanel" aria-labelledby="au-tab-maintenance" hidden>
    <p class="au-panel__lead"><?= htmlspecialchars(__('app.auctionator.tabs.maintenance_lead')) ?></p>
    <?php // 两张卡的控件都很小，并排两列就够，不必各占一整行（右边会空一大片） ?>
    <div class="au-grid au-grid--cards">
      <div class="au-card">
        <?= $cardTitle(__('app.auctionator.actions.read_title')) ?>
        <div class="au-actions">
          <button type="button" class="btn outline" data-au-action="status"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.status')) ?></button>
          <button type="button" class="btn outline" data-au-action="market"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.market')) ?></button>
        </div>
        <?= $cardTitle(__('app.auctionator.actions.runtime_title'), __('app.auctionator.actions.runtime_hint')) ?>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.actions.runtime_hint_short')) ?></p>
        <div class="au-actions">
          <select class="au-input" data-au-action-field="target">
            <?php foreach (['neutralseller', 'allianceseller', 'hordeseller', 'neutralbidder', 'alliancebidder', 'hordebidder', 'all'] as $target): ?>
              <option value="<?= htmlspecialchars($target) ?>"><?= htmlspecialchars($target) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn outline success" data-au-action="enable"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.enable')) ?></button>
          <button type="button" class="btn outline warn" data-au-action="disable"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.disable')) ?></button>
        </div>
        <div class="au-actions">
          <input class="au-input" type="number" min="0" max="1000" value="<?= (int) ($typed['Auctionator.Seller.AuctionsPerRun'] ?? 10) ?>" data-au-action-field="value" title="<?= htmlspecialchars(__('app.auctionator.fields.auctions_per_run')) ?>">
          <button type="button" class="btn outline" data-au-action="auctionspercycle"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.auctionspercycle')) ?></button>
        </div>
      </div>

      <div class="au-card">
        <?= $cardTitle(__('app.auctionator.actions.market_title'), __('app.auctionator.actions.market_hint')) ?>
        <?php // 采样本区拍卖行：聚合完全在模块里的一条 SQL 里做，面板只负责触发（SOAP） ?>
        <div class="au-actions">
          <button type="button" class="btn primary" data-au-action="marketscan"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.marketscan')) ?></button>
          <span class="muted small"><?= htmlspecialchars(__('app.auctionator.actions.marketscan_hint_short')) ?><?= panel_hint(__('app.auctionator.actions.marketscan_hint')) ?></span>
        </div>
        <div class="au-actions">
          <button type="button" class="btn outline" data-au-action="marketimport"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.marketimport')) ?></button>
          <label class="au-checkbox">
            <input type="checkbox" data-au-action-field="force">
            <span>force</span>
          </label>
        </div>
        <div class="au-actions">
          <input class="au-input" type="number" min="1" max="3650" value="30" data-au-action-field="days" title="<?= htmlspecialchars(__('app.auctionator.market.retention_days')) ?>">
          <button type="button" class="btn outline warn" data-au-action="marketprune"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.marketprune')) ?></button>
        </div>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.actions.market_hint_short')) ?></p>
      </div>
    </div>
  </section>

  <!-- ==== stock（续）：批量补货 / 强制过期 / 临时上架 -->
  <section class="au-panel" data-au-panel="stock" id="au-panel-stock-2" role="tabpanel" aria-labelledby="au-tab-stock" hidden>
    <div class="au-grid au-grid--cards">
      <div class="au-card au-card--wide">
        <?= $cardTitle(__('app.auctionator.actions.gm_title'), __('app.auctionator.actions.gm_hint')) ?>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.actions.gm_hint_short')) ?></p>

        <div class="au-actions">
          <select class="au-input" data-au-action-field="house" title="<?= htmlspecialchars(__('app.auctionator.policy.house')) ?>">
            <option value="7"><?= htmlspecialchars(__('app.auctionator.houses.neutral')) ?></option>
            <option value="2"><?= htmlspecialchars(__('app.auctionator.houses.alliance')) ?></option>
            <option value="6"><?= htmlspecialchars(__('app.auctionator.houses.horde')) ?></option>
          </select>
          <select class="au-input" data-au-action-field="owner_override" title="<?= htmlspecialchars(__('app.auctionator.actions.addlist_owner')) ?>">
            <option value="row"><?= htmlspecialchars(__('app.auctionator.actions.addlist_owner_row')) ?></option>
            <option value="bot"><?= htmlspecialchars(__('app.auctionator.policy.owner_bot')) ?></option>
          </select>
          <button type="button" class="btn primary" data-au-action="addlist"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.addlist')) ?></button>
          <label class="au-checkbox">
            <input type="checkbox" data-au-action-field="all">
            <span><?= htmlspecialchars(__('app.auctionator.actions.expire_all_included')) ?></span>
          </label>
          <button type="button" class="btn outline danger" data-au-action="expireall"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.expireall')) ?></button>
        </div>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.actions.addlist_hint_short')) ?><?= panel_hint(__('app.auctionator.actions.addlist_hint')) ?></p>

        <form class="au-form au-form--add" id="auAddForm" data-au-listing-form>
          <div class="au-form__grid">
            <label class="au-form__row au-form__row--wide"><span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.actions.items')) ?></span>
              <?php // 多选：每个选中的物品变成一枚可移除的标签，隐藏字段 name="items" 仍是逗号分隔的 entry 列表 ?>
              <div class="au-picker" data-au-picker data-au-picker-multiple="1">
                <input type="hidden" name="items" data-au-picker-value>
                <div class="au-picker__chips" data-au-picker-chips></div>
                <input class="au-input au-picker__search" type="text" data-au-picker-search
                       placeholder="<?= htmlspecialchars(__('app.auctionator.picker.search_add_items')) ?>"
                       autocomplete="off" role="combobox" aria-expanded="false" aria-autocomplete="list"
                       aria-label="<?= htmlspecialchars(__('app.auctionator.actions.items')) ?>"<?= $canControl && $supported ? '' : ' disabled' ?>>
                <ul class="au-picker__list" data-au-picker-list role="listbox" hidden></ul>
              </div>
            </label>
            <label class="au-form__row"><span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.house')) ?></span>
              <select class="au-input" name="house"<?= $canControl && $supported ? '' : ' disabled' ?>>
                <option value="7"><?= htmlspecialchars(__('app.auctionator.houses.neutral')) ?></option>
                <option value="2"><?= htmlspecialchars(__('app.auctionator.houses.alliance')) ?></option>
                <option value="6"><?= htmlspecialchars(__('app.auctionator.houses.horde')) ?></option>
              </select></label>
            <label class="au-form__row"><span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.mode')) ?></span>
              <select class="au-input" name="mode"<?= $canControl && $supported ? '' : ' disabled' ?>>
                <option value="buyout" selected><?= htmlspecialchars(__('app.auctionator.modes.buyout_hint')) ?></option>
                <option value="bid"><?= htmlspecialchars(__('app.auctionator.modes.bid_hint')) ?></option>
              </select></label>
            <label class="au-form__row" data-au-listing-field="bid" hidden><span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.bid_price')) ?></span>
              <input class="au-input" type="number" min="1" name="bid"<?= $canControl && $supported ? '' : ' disabled' ?>></label>
            <label class="au-form__row" data-au-listing-field="price"><span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.buyout_price_short')) ?></span>
              <input class="au-input" type="number" min="0" name="price"<?= $canControl && $supported ? '' : ' disabled' ?>></label>
            <label class="au-form__row"><span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.stack')) ?></span>
              <input class="au-input" type="number" min="1" name="stack" value="1"<?= $canControl && $supported ? '' : ' disabled' ?>></label>
            <label class="au-form__row"><span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.hours')) ?><?= panel_hint(__('app.auctionator.actions.add_hint', ['hours' => (int) ($notes['listing_hours'] ?? 12)])) ?></span>
              <input class="au-input" type="number" min="1" max="720" name="hours" value="48"<?= $canControl && $supported ? '' : ' disabled' ?>></label>
            <label class="au-form__row"><span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.owner')) ?><?= panel_hint(__('app.auctionator.actions.add_owner_hint')) ?></span>
              <input class="au-input" type="text" name="owner" placeholder="bot"<?= $canControl && $supported ? '' : ' disabled' ?>></label>
          </div>
          <p class="muted small" data-au-listing-preview></p>
          <div class="au-form__actions">
            <button type="submit" class="btn primary"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.add')) ?></button>
            <span class="muted small"><?= htmlspecialchars(__('app.auctionator.actions.add_owner_hint_short')) ?></span>
          </div>
        </form>
      </div>
    </div>
  </section>

  <?php // 命令输出常驻在 tabs 之外：从哪个 tab 触发命令，结果都落在同一处，不会被别的 tab 藏起来 ?>
  <div class="au-card au-card--wide au-output-panel" id="auOutputPanel" hidden>
    <h3><?= htmlspecialchars(__('app.auctionator.actions.output_title')) ?></h3>
    <pre class="au-output" id="auOutput"><?= htmlspecialchars(__('app.auctionator.actions.output_empty')) ?></pre>
  </div>
</div>
