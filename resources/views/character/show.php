<?php
/**
 * File: resources/views/character/show.php
 * Purpose: Character detail view.
 */

 $module='character'; include __DIR__.'/../layouts/base_top.php';
?>
<h1 class="page-title"><?= htmlspecialchars($title ?? __('app.character.show.title_default')) ?></h1>
<?php $charBase = \Acme\Panel\Core\Url::to('/character'); ?>
<p><a class="btn" href="<?= htmlspecialchars($charBase) ?>">&larr; <?= htmlspecialchars(__('app.character.show.back')) ?></a></p>

<?php if(!empty($error)): ?>
  <div class="panel-flash panel-flash--danger panel-flash--inline is-visible"><?= htmlspecialchars($error) ?></div>
<?php elseif(empty($summary)): ?>
  <div class="panel-flash panel-flash--info panel-flash--inline is-visible"><?= htmlspecialchars(__('app.character.show.not_found')) ?></div>
<?php else: ?>

  <div class="char-tabs">
    <div class="char-tab-item active" data-tab="summary"><?= htmlspecialchars(__('app.character.show.summary.title')) ?></div>
    <div class="char-tab-item" data-tab="inventory"><?= htmlspecialchars(__('app.character.show.inventory.title')) ?></div>
    <div class="char-tab-item" data-tab="spells"><?= htmlspecialchars(__('app.character.show.spells.title')) ?> & <?= htmlspecialchars(__('app.character.show.skills.title')) ?></div>
    <div class="char-tab-item" data-tab="quests"><?= htmlspecialchars(__('app.character.show.quests.title')) ?> & <?= htmlspecialchars(__('app.character.show.reputations.title')) ?></div>
    <div class="char-tab-item" data-tab="auras"><?= htmlspecialchars(__('app.character.show.auras.title')) ?></div>
    <div class="char-tab-item" data-tab="achievements"><?= htmlspecialchars(__('app.character.show.achievements.title')) ?></div>
  </div>

  <!-- Tab: Summary -->
  <div id="summary" class="char-tab-content active">
    <div class="char-card">
      <div class="char-summary-grid">
        <div>
          <h3 class="char-section-header"><?= htmlspecialchars(__('app.character.show.summary.title')) ?></h3>
          <table class="table table--compact">
            <tbody>
              <tr><th><?= htmlspecialchars(__('app.character.show.summary.guid')) ?></th><td><?= (int)$summary['guid'] ?></td></tr>
              <tr><th><?= htmlspecialchars(__('app.character.show.summary.name')) ?></th><td><?= htmlspecialchars($summary['name']) ?></td></tr>
              <tr><th><?= htmlspecialchars(__('app.character.show.summary.account')) ?></th><td><?= htmlspecialchars($summary['account_username'] ?? ('#'.($summary['account'] ?? ''))) ?></td></tr>
              <tr><th><?= htmlspecialchars(__('app.character.show.summary.level')) ?></th><td><?= (int)$summary['level'] ?></td></tr>
              <?php $classId = (int)($summary['class'] ?? 0); ?>
              <tr><th><?= htmlspecialchars(__('app.character.show.summary.class')) ?></th><td><span data-class-id="<?= $classId ?>"><?= htmlspecialchars(\Acme\Panel\Support\GameMaps::className($classId)) ?></span> (#<?= $classId ?>)</td></tr>
              <?php $raceId = (int)($summary['race'] ?? 0); ?>
              <tr><th><?= htmlspecialchars(__('app.character.show.summary.race')) ?></th><td><?= htmlspecialchars(\Acme\Panel\Support\GameMaps::raceName($raceId)) ?> (#<?= $raceId ?>)</td></tr>
              <tr><th><?= htmlspecialchars(__('app.character.show.summary.online')) ?></th><td><?= (int)$summary['online'] ? __('app.character.show.status.online') : __('app.character.show.status.offline') ?></td></tr>
              <?php $mapId = (int)($summary['map'] ?? 0); $mapName = \Acme\Panel\Support\GameMaps::mapName($mapId); $zoneId = (int)($summary['zone'] ?? 0); $zoneName = \Acme\Panel\Support\GameMaps::zoneName($zoneId); ?>
              <tr><th><?= htmlspecialchars(__('app.character.show.summary.map')) ?></th><td><?= $mapName ? (htmlspecialchars($mapName) . ' (#' . $mapId . ')') : (string)$mapId ?> / <?= $zoneName ? (htmlspecialchars($zoneName) . ' (#' . $zoneId . ')') : (string)$zoneId ?></td></tr>
              <tr><th><?= htmlspecialchars(__('app.character.show.summary.position')) ?></th><td><?= htmlspecialchars(number_format((float)$summary['position_x'],2)).', '.htmlspecialchars(number_format((float)$summary['position_y'],2)).', '.htmlspecialchars(number_format((float)$summary['position_z'],2)) ?></td></tr>
              <tr><th><?= htmlspecialchars(__('app.character.show.summary.money')) ?></th><td><?= htmlspecialchars(format_money_gsc($summary['money'] ?? 0)) ?></td></tr>
              <tr><th><?= htmlspecialchars(__('app.character.show.summary.mail')) ?></th><td><?= (int)($mail_count ?? 0) ?></td></tr>
              <tr><th><?= htmlspecialchars(__('app.character.show.summary.logout')) ?></th><td><?= htmlspecialchars(format_datetime($summary['logout_time'] ?? null)) ?></td></tr>
              <tr><th><?= htmlspecialchars(__('app.character.show.summary.homebind')) ?></th><td>
                <?php if(!empty($summary['homebind'])): $hb=$summary['homebind']; ?>
                  <?php $hbMapId = (int)($hb['mapId'] ?? 0); $hbMapName = \Acme\Panel\Support\GameMaps::mapName($hbMapId); $hbZoneId = (int)($hb['zoneId'] ?? 0); $hbZoneName = \Acme\Panel\Support\GameMaps::zoneName($hbZoneId); ?>
                  <?= $hbMapName ? (htmlspecialchars($hbMapName) . ' (#' . $hbMapId . ')') : (string)$hbMapId ?> / <?= $hbZoneName ? (htmlspecialchars($hbZoneName) . ' (#' . $hbZoneId . ')') : (string)$hbZoneId ?> (<?= htmlspecialchars(number_format((float)$hb['posX'],2)) ?>, <?= htmlspecialchars(number_format((float)$hb['posY'],2)) ?>, <?= htmlspecialchars(number_format((float)$hb['posZ'],2)) ?>)
                <?php else: ?>
                  <?= htmlspecialchars(__('app.character.show.summary.homebind_none')) ?>
                <?php endif; ?>
              </td></tr>
              <tr><th><?= htmlspecialchars(__('app.character.show.summary.gmlevel')) ?></th><td><?= isset($summary['gmlevel']) ? (int)$summary['gmlevel'] : '-' ?></td></tr>
              <tr><th><?= htmlspecialchars(__('app.character.show.summary.ban')) ?></th><td>
                <?php if(!empty($summary['ban'])): $b=$summary['ban']; ?>
                  <?= htmlspecialchars(__('app.character.show.ban.active', ['reason'=>$b['banreason'] ?? '-', 'end'=>$b['permanent'] ? __('app.character.show.ban.permanent') : format_datetime($b['unbandate'] ?? null)])) ?>
                <?php else: ?>
                  <?= htmlspecialchars(__('app.character.show.ban.none')) ?>
                <?php endif; ?>
              </td></tr>
            </tbody>
          </table>
        </div>
        <div>
          <h3 class="char-section-header"><?= htmlspecialchars(__('app.character.actions.title')) ?></h3>
          <div id="char-feedback" class="panel-flash panel-flash--inline char-feedback"></div>
          
          <div class="char-actions-container">
            <!-- Group: Stats -->
            <div class="char-action-group">
              <h4><?= htmlspecialchars(__('app.character.actions.group_stats')) ?></h4>
              <div class="char-action-row">
                <form class="js-char-action char-action-inline" data-endpoint="<?= htmlspecialchars($charBase.'/api/set-level') ?>">
                  <?= \Acme\Panel\Support\Csrf::field(); ?>
                  <input type="hidden" name="guid" value="<?= (int)$summary['guid'] ?>">
                  <label><?= htmlspecialchars(__('app.character.actions.level_label')) ?></label>
                  <div class="input-group">
                    <input type="number" name="level" min="1" max="255" value="<?= (int)$summary['level'] ?>">
                    <button class="btn btn-sm" type="submit"><?= htmlspecialchars(__('app.character.actions.set')) ?></button>
                  </div>
                </form>
                <form class="js-char-action char-action-inline" data-endpoint="<?= htmlspecialchars($charBase.'/api/set-gold') ?>">
                  <?= \Acme\Panel\Support\Csrf::field(); ?>
                  <input type="hidden" name="guid" value="<?= (int)$summary['guid'] ?>">
                  <label><?= htmlspecialchars(__('app.character.actions.gold_label')) ?></label>
                  <div class="input-group">
                    <input type="number" name="copper" min="0" value="<?= (int)$summary['money'] ?>">
                    <button class="btn btn-sm" type="submit"><?= htmlspecialchars(__('app.character.actions.set')) ?></button>
                  </div>
                </form>
              </div>
            </div>

            <!-- Group: Moderation -->
            <div class="char-action-group">
              <h4><?= htmlspecialchars(__('app.character.actions.group_moderation')) ?></h4>
              <form class="js-char-action char-action-form" data-endpoint="<?= htmlspecialchars($charBase.'/api/ban') ?>">
                <?= \Acme\Panel\Support\Csrf::field(); ?>
                <input type="hidden" name="guid" value="<?= (int)$summary['guid'] ?>">
                <label class="char-action-label"><?= htmlspecialchars(__('app.character.actions.ban_label')) ?></label>
                <div class="input-group">
                  <input class="char-input--narrow" type="number" name="hours" min="0" value="0" placeholder="<?= htmlspecialchars(__('app.character.actions.ban_hours')) ?>">
                  <input type="text" name="reason" placeholder="<?= htmlspecialchars(__('app.character.actions.reason_placeholder')) ?>">
                  <button class="btn btn-sm danger" type="submit"><?= htmlspecialchars(__('app.character.actions.ban')) ?></button>
                </div>
              </form>
              <div class="char-action-buttons">
                <form class="js-char-action" data-endpoint="<?= htmlspecialchars($charBase.'/api/unban') ?>">
                  <?= \Acme\Panel\Support\Csrf::field(); ?>
                  <input type="hidden" name="guid" value="<?= (int)$summary['guid'] ?>">
                  <button class="btn btn-sm success" type="submit"><?= htmlspecialchars(__('app.character.actions.unban')) ?></button>
                </form>
                <form class="js-char-action" data-endpoint="<?= htmlspecialchars($charBase.'/api/delete') ?>" onsubmit="return confirm('<?= htmlspecialchars(__('app.character.actions.confirm_delete')) ?>');">
                  <?= \Acme\Panel\Support\Csrf::field(); ?>
                  <input type="hidden" name="guid" value="<?= (int)$summary['guid'] ?>">
                  <button class="btn btn-sm danger" type="submit"><?= htmlspecialchars(__('app.character.actions.delete')) ?></button>
                </form>
              </div>
            </div>

            <!-- Group: Movement -->
            <div class="char-action-group">
              <h4><?= htmlspecialchars(__('app.character.actions.group_movement')) ?></h4>
              <form id="char-teleport-form" class="js-char-action char-action-form" data-endpoint="<?= htmlspecialchars($charBase.'/api/teleport') ?>">
                <?= \Acme\Panel\Support\Csrf::field(); ?>
                <input type="hidden" name="guid" value="<?= (int)$summary['guid'] ?>">
                <label class="char-action-label"><?= htmlspecialchars(__('app.character.actions.teleport_label')) ?></label>
                <?php
                  $isAlliance = in_array((int)($summary['race'] ?? 0), [1,3,4,7,11], true);
                  $teleportPresets = $isAlliance
                    ? [
                      ['key'=>'stormwind','map'=>0,'zone'=>1519,'x'=>-8913.23,'y'=>554.633,'z'=>93.7944],
                      ['key'=>'ironforge','map'=>0,'zone'=>1537,'x'=>-4981.25,'y'=>-881.542,'z'=>501.66],
                      ['key'=>'darnassus','map'=>1,'zone'=>1657,'x'=>9951.52,'y'=>2280.32,'z'=>1341.39],
                      ['key'=>'exodar','map'=>530,'zone'=>3557,'x'=>-3987.29,'y'=>-11846.6,'z'=>-2.01903],
                      ['key'=>'dalaran','map'=>571,'zone'=>4395,'x'=>5812.79,'y'=>647.158,'z'=>647.413],
                      ['key'=>'shattrath','map'=>530,'zone'=>3703,'x'=>-1863.03,'y'=>4998.05,'z'=>-21.1847],
                    ]
                    : [
                      ['key'=>'orgrimmar','map'=>1,'zone'=>1637,'x'=>1676.21,'y'=>-4315.29,'z'=>61.5293],
                      ['key'=>'undercity','map'=>0,'zone'=>1497,'x'=>1586.48,'y'=>239.562,'z'=>-52.149],
                      ['key'=>'thunder_bluff','map'=>1,'zone'=>1638,'x'=>-1196.22,'y'=>29.0941,'z'=>176.949],
                      ['key'=>'silvermoon','map'=>530,'zone'=>3487,'x'=>9473.03,'y'=>-7279.67,'z'=>14.2285],
                      ['key'=>'dalaran','map'=>571,'zone'=>4395,'x'=>5812.79,'y'=>647.158,'z'=>647.413],
                      ['key'=>'shattrath','map'=>530,'zone'=>3703,'x'=>-1863.03,'y'=>4998.05,'z'=>-21.1847],
                    ];
                ?>
                <select id="char-teleport-preset" class="teleport-preset-select">
                  <option value=""><?= htmlspecialchars(__('app.character.actions.teleport_preset_placeholder')) ?></option>
                  <?php foreach($teleportPresets as $p): ?>
                    <option
                      value="<?= htmlspecialchars($p['key']) ?>"
                      data-map="<?= (int)$p['map'] ?>"
                      data-zone="<?= (int)$p['zone'] ?>"
                      data-x="<?= htmlspecialchars((string)$p['x']) ?>"
                      data-y="<?= htmlspecialchars((string)$p['y']) ?>"
                      data-z="<?= htmlspecialchars((string)$p['z']) ?>"
                    >
                      <?= htmlspecialchars(__('app.character.actions.teleport_presets.'.$p['key'])) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="teleport-grid">
                  <input type="number" name="map" value="<?= (int)($summary['map'] ?? 0) ?>" placeholder="<?= htmlspecialchars(__('app.character.actions.teleport_map')) ?>">
                  <input type="number" name="zone" value="<?= (int)($summary['zone'] ?? 0) ?>" placeholder="<?= htmlspecialchars(__('app.character.actions.teleport_zone')) ?>">
                  <input type="number" step="0.01" name="x" value="<?= htmlspecialchars(number_format((float)$summary['position_x'],2,'.','')) ?>" placeholder="<?= htmlspecialchars(__('app.character.actions.teleport_x')) ?>">
                  <input type="number" step="0.01" name="y" value="<?= htmlspecialchars(number_format((float)$summary['position_y'],2,'.','')) ?>" placeholder="<?= htmlspecialchars(__('app.character.actions.teleport_y')) ?>">
                  <input type="number" step="0.01" name="z" value="<?= htmlspecialchars(number_format((float)$summary['position_z'],2,'.','')) ?>" placeholder="<?= htmlspecialchars(__('app.character.actions.teleport_z')) ?>">
                </div>
              </form>
              <form id="char-unstuck-form" class="js-char-action char-action-form char-action-form--hidden" data-endpoint="<?= htmlspecialchars($charBase.'/api/unstuck') ?>">
                <?= \Acme\Panel\Support\Csrf::field(); ?>
                <input type="hidden" name="guid" value="<?= (int)$summary['guid'] ?>">
              </form>
              <div class="char-action-buttons char-action-buttons--pair">
                <button class="btn btn-sm" type="submit" form="char-teleport-form"><?= htmlspecialchars(__('app.character.actions.teleport')) ?></button>
                <button class="btn btn-sm warn" type="submit" form="char-unstuck-form"><?= htmlspecialchars(__('app.character.actions.unstuck')) ?></button>
              </div>
            </div>

            <!-- Group: Tools -->
            <div class="char-action-group">
              <h4><?= htmlspecialchars(__('app.character.actions.group_tools')) ?></h4>
              <div class="char-action-buttons char-action-buttons--tools">
                <form class="js-char-action" data-endpoint="<?= htmlspecialchars($charBase.'/api/reset-talents') ?>">
                  <?= \Acme\Panel\Support\Csrf::field(); ?>
                  <input type="hidden" name="guid" value="<?= (int)$summary['guid'] ?>">
                  <button class="btn btn-sm outline" type="submit"><?= htmlspecialchars(__('app.character.actions.reset_talents')) ?></button>
                </form>
                <form class="js-char-action" data-endpoint="<?= htmlspecialchars($charBase.'/api/reset-spells') ?>">
                  <?= \Acme\Panel\Support\Csrf::field(); ?>
                  <input type="hidden" name="guid" value="<?= (int)$summary['guid'] ?>">
                  <button class="btn btn-sm outline" type="submit"><?= htmlspecialchars(__('app.character.actions.reset_spells')) ?></button>
                </form>
                <form class="js-char-action" data-endpoint="<?= htmlspecialchars($charBase.'/api/reset-cooldowns') ?>">
                  <?= \Acme\Panel\Support\Csrf::field(); ?>
                  <input type="hidden" name="guid" value="<?= (int)$summary['guid'] ?>">
                  <button class="btn btn-sm outline" type="submit"><?= htmlspecialchars(__('app.character.actions.reset_cooldowns')) ?></button>
                </form>
                <form class="js-char-action" data-endpoint="<?= htmlspecialchars($charBase.'/api/rename-flag') ?>">
                  <?= \Acme\Panel\Support\Csrf::field(); ?>
                  <input type="hidden" name="guid" value="<?= (int)$summary['guid'] ?>">
                  <button class="btn btn-sm" type="submit"><?= htmlspecialchars(__('app.character.actions.rename_flag')) ?></button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tab: Inventory -->
  <div id="inventory" class="char-tab-content">
    <div id="char-bag-query" data-guid="<?= (int)$summary['guid'] ?>" data-name="<?= htmlspecialchars($summary['name']) ?>"></div>
    <?php $bagQueryItemsTitle = __('app.character.show.inventory.title'); ?>
    <?php include __DIR__.'/../bag_query/_items_panel.php'; ?>
  </div>

  <!-- Tab: Spells & Skills -->
  <div id="spells" class="char-tab-content">
    <div class="char-card">
      <h3 class="char-section-header"><?= htmlspecialchars(__('app.character.show.skills.title')) ?></h3>
      <div class="char-table-scroll char-scroll-300 char-scroll-mb">
        <table class="table table--compact">
          <thead>
            <tr>
              <th><?= htmlspecialchars(__('app.character.show.skills.skill')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.skills.value')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.skills.max')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach(($skills ?? []) as $s): ?>
              <tr>
                <?php $skillId = (int)($s['skill'] ?? 0); ?>
                <td><span class="js-nfuwow" data-nfuwow-type="skill" data-nfuwow-id="<?= $skillId ?>"><?= $skillId ?></span><span class="js-nfuwow-name"></span></td>
                <td><?= (int)$s['value'] ?></td>
                <td><?= (int)$s['max'] ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if(empty($skills)): ?><tr class="js-empty-row"><td colspan="3" class="char-empty-cell">&nbsp;<?= htmlspecialchars(__('app.character.show.skills.empty')) ?></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <h3 class="char-section-header"><?= htmlspecialchars(__('app.character.show.spells.title')) ?></h3>
      <div class="char-filter-bar">
        <input type="search" class="js-table-filter char-filter-input" data-target="#spells-table" placeholder="<?= htmlspecialchars(__('app.character.show.controls.filter_placeholder')) ?>">
      </div>
      <div class="char-table-scroll char-scroll-400 char-scroll-mb">
        <table class="table table--compact" id="spells-table" data-filter-empty="<?= htmlspecialchars(__('app.character.show.controls.filter_no_results')) ?>">
          <thead>
            <tr>
              <th><?= htmlspecialchars(__('app.character.show.spells.spell')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.spells.active')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.spells.disabled')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach(($spells ?? []) as $sp): ?>
              <tr>
                <?php $spellId = (int)($sp['spell'] ?? 0); ?>
                <td><span class="js-nfuwow" data-nfuwow-type="spell" data-nfuwow-id="<?= $spellId ?>"><?= $spellId ?></span><span class="js-nfuwow-name"></span></td>
                <td><?= ((int)($sp['active'] ?? 0)) ? htmlspecialchars(__('app.character.show.bool.yes')) : htmlspecialchars(__('app.character.show.bool.no')) ?></td>
                <td><?= ((int)($sp['disabled'] ?? 0)) ? htmlspecialchars(__('app.character.show.bool.yes')) : htmlspecialchars(__('app.character.show.bool.no')) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if(empty($spells)): ?><tr class="js-empty-row"><td colspan="3" class="char-empty-cell">&nbsp;<?= htmlspecialchars(__('app.character.show.spells.empty')) ?></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <h3 class="char-section-header"><?= htmlspecialchars(__('app.character.show.cooldowns.title')) ?></h3>
      <div class="char-table-scroll char-scroll-300">
        <table class="table table--compact">
          <thead>
            <tr>
              <th><?= htmlspecialchars(__('app.character.show.cooldowns.spell')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.cooldowns.item')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.cooldowns.time')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.cooldowns.category')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach(($cooldowns ?? []) as $cd): ?>
              <tr>
                <td><?= (int)$cd['spellid'] ?></td>
                <td><?= (int)$cd['itemid'] ?></td>
                <td><?= htmlspecialchars(format_datetime($cd['time'] ?? null)) ?></td>
                <td><?= htmlspecialchars($cd['category'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if(empty($cooldowns)): ?><tr class="js-empty-row"><td colspan="4" class="char-empty-cell">&nbsp;<?= htmlspecialchars(__('app.character.show.cooldowns.empty')) ?></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Tab: Quests & Reps -->
  <div id="quests" class="char-tab-content">
    <div class="char-card">
      <h3 class="char-section-header"><?= htmlspecialchars(__('app.character.show.quests.title')) ?></h3>
      <h4 class="char-sub-header"><?= htmlspecialchars(__('app.character.show.quests.regular')) ?></h4>
      <?php
        $questStatusLabel = static function(int $status): string {
          return __('app.character.show.quests.status_map.'.$status, [], (string)$status);
        };
        $repFlagsLabel = static function(int $flags): string {
          $defs = [
            0x01 => 'visible',
            0x02 => 'at_war',
            0x04 => 'hidden',
            0x08 => 'inactive',
            0x10 => 'peace_forced',
            0x20 => 'unknown_20',
            0x40 => 'unknown_40',
            0x80 => 'rival',
          ];
          $labels = [];
          foreach($defs as $bit => $key){
            if(($flags & $bit) === $bit){
              $labels[] = __('app.character.show.reputations.flags_labels.'.$key);
            }
          }
          return $labels ? implode(', ', $labels) : '-';
        };
      ?>
      <div class="char-table-scroll char-scroll-300 char-scroll-mb">
        <table class="table table--compact">
          <thead>
            <tr>
              <th><?= htmlspecialchars(__('app.character.show.quests.quest')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.quests.status')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.quests.timer')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.quests.mob_counts')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.quests.item_counts')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach(($quests['regular'] ?? []) as $q): ?>
              <?php $mobCounts = [(int)$q['mobcount1'],(int)$q['mobcount2'],(int)$q['mobcount3'],(int)$q['mobcount4']]; ?>
              <?php $itemCounts = [(int)$q['itemcount1'],(int)$q['itemcount2'],(int)$q['itemcount3'],(int)$q['itemcount4']]; ?>
              <tr>
                <?php $questId = (int)($q['quest'] ?? 0); ?>
                <td><span class="js-nfuwow" data-nfuwow-type="quest" data-nfuwow-id="<?= $questId ?>"><?= $questId ?></span><span class="js-nfuwow-name"></span></td>
                <?php $questStatus = (int)($q['status'] ?? 0); $questStatusText = $questStatusLabel($questStatus); ?>
                <td><?= $questStatus ?><?= $questStatusText !== (string)$questStatus ? ' - '.htmlspecialchars($questStatusText) : '' ?></td>
                <td><?= htmlspecialchars(format_datetime($q['timer'] ?? null)) ?></td>
                <td><?= htmlspecialchars(implode(' / ',$mobCounts)) ?></td>
                <td><?= htmlspecialchars(implode(' / ',$itemCounts)) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if(empty($quests['regular'] ?? [])): ?><tr class="js-empty-row"><td colspan="5" class="char-empty-cell">&nbsp;<?= htmlspecialchars(__('app.character.show.quests.empty')) ?></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="char-quests-grid">
        <div>
          <h4 class="char-sub-header"><?= htmlspecialchars(__('app.character.show.quests.daily')) ?></h4>
          <?php if(!empty($quests['daily'] ?? [])): ?>
            <ul class="char-list">
              <?php foreach($quests['daily'] as $dq): ?>
                <?php $dailyQuestId = (int)($dq['quest'] ?? 0); ?>
                <li><span class="js-nfuwow" data-nfuwow-type="quest" data-nfuwow-id="<?= $dailyQuestId ?>"><?= $dailyQuestId ?></span><span class="js-nfuwow-name"></span></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="char-empty-note">&nbsp;<?= htmlspecialchars(__('app.character.show.quests.empty_daily')) ?></p>
          <?php endif; ?>
        </div>
        <div>
          <h4 class="char-sub-header"><?= htmlspecialchars(__('app.character.show.quests.weekly')) ?></h4>
          <?php if(!empty($quests['weekly'] ?? [])): ?>
            <ul class="char-list">
              <?php foreach($quests['weekly'] as $wq): ?>
                <?php $weeklyQuestId = (int)($wq['quest'] ?? 0); ?>
                <li><span class="js-nfuwow" data-nfuwow-type="quest" data-nfuwow-id="<?= $weeklyQuestId ?>"><?= $weeklyQuestId ?></span><span class="js-nfuwow-name"></span></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="char-empty-note">&nbsp;<?= htmlspecialchars(__('app.character.show.quests.empty_weekly')) ?></p>
          <?php endif; ?>
        </div>
      </div>

      <h3 class="char-section-header char-section-header--spaced"><?= htmlspecialchars(__('app.character.show.reputations.title')) ?></h3>
      <div class="char-filter-bar">
        <input type="search" class="js-table-filter char-filter-input" data-target="#reps-table" placeholder="<?= htmlspecialchars(__('app.character.show.controls.filter_placeholder')) ?>">
      </div>
      <div class="char-table-scroll char-scroll-300">
        <table class="table table--compact" id="reps-table" data-filter-empty="<?= htmlspecialchars(__('app.character.show.controls.filter_no_results')) ?>">
          <thead>
            <tr>
              <th><?= htmlspecialchars(__('app.character.show.reputations.faction')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.reputations.standing')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.reputations.flags')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach(($reputations ?? []) as $rep): ?>
              <tr>
                <?php $factionId = (int)($rep['faction'] ?? 0); ?>
                <td><span class="js-nfuwow" data-nfuwow-type="faction" data-nfuwow-id="<?= $factionId ?>"><?= $factionId ?></span><span class="js-nfuwow-name"></span></td>
                <td><?= (int)$rep['standing'] ?></td>
                <?php $repFlags = (int)($rep['flags'] ?? 0); ?>
                <td><span title="<?= htmlspecialchars((string)$repFlags) ?>"><?= htmlspecialchars($repFlagsLabel($repFlags)) ?></span></td>
              </tr>
            <?php endforeach; ?>
            <?php if(empty($reputations)): ?><tr class="js-empty-row"><td colspan="3" class="char-empty-cell">&nbsp;<?= htmlspecialchars(__('app.character.show.reputations.empty')) ?></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Tab: Auras -->
  <div id="auras" class="char-tab-content">
    <div class="char-card">
      <h3 class="char-section-header"><?= htmlspecialchars(__('app.character.show.auras.title')) ?></h3>
      <div class="char-filter-bar">
        <input type="search" class="js-table-filter char-filter-input" data-target="#auras-table" placeholder="<?= htmlspecialchars(__('app.character.show.controls.filter_placeholder')) ?>">
      </div>
      <div class="char-table-scroll char-scroll-500">
        <table class="table table--compact" id="auras-table" data-filter-empty="<?= htmlspecialchars(__('app.character.show.controls.filter_no_results')) ?>">
          <thead>
            <tr>
              <th><?= htmlspecialchars(__('app.character.show.auras.spell')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.auras.caster')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.auras.item')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.auras.mask')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.auras.amounts')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.auras.charges')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.auras.duration')) ?></th>
              <th><?= htmlspecialchars(__('app.character.show.auras.remaining')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach(($auras ?? []) as $au): ?>
              <?php $amounts = [(int)$au['amount0'],(int)$au['amount1'],(int)$au['amount2']]; ?>
              <tr>
                <?php $auraSpellId = (int)($au['spell'] ?? 0); ?>
                <td><span class="js-nfuwow" data-nfuwow-type="spell" data-nfuwow-id="<?= $auraSpellId ?>"><?= $auraSpellId ?></span><span class="js-nfuwow-name"></span></td>
                <td><?= htmlspecialchars($au['caster_guid'] ?? '') ?></td>
                <td><?= htmlspecialchars($au['item_guid'] ?? '') ?></td>
                <td><?= (int)$au['effect_mask'] ?></td>
                <td><?= htmlspecialchars(implode(' / ',$amounts)) ?></td>
                <td><?= htmlspecialchars($au['remaincharges'] ?? '') ?></td>
                <td><?= htmlspecialchars($au['maxduration'] ?? '') ?></td>
                <td><?= htmlspecialchars($au['remaintime'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if(empty($auras)): ?><tr class="js-empty-row"><td colspan="8" class="char-empty-cell">&nbsp;<?= htmlspecialchars(__('app.character.show.auras.empty')) ?></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Tab: Achievements -->
  <div id="achievements" class="char-tab-content">
    <div class="char-card">
      <h3 class="char-section-header"><?= htmlspecialchars(__('app.character.show.achievements.title')) ?></h3>
      <div class="char-achievements-grid">
        <div>
          <h4 class="char-sub-header"><?= htmlspecialchars(__('app.character.show.achievements.unlocks')) ?></h4>
          <div class="char-table-scroll char-scroll-400">
            <table class="table table--compact">
              <thead>
                <tr>
                  <th><?= htmlspecialchars(__('app.character.show.achievements.achievement')) ?></th>
                  <th><?= htmlspecialchars(__('app.character.show.achievements.date')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach(($achievements['unlocks'] ?? []) as $a): ?>
                  <tr>
                    <?php $achId = (int)($a['achievement'] ?? 0); ?>
                    <td><span class="js-nfuwow" data-nfuwow-type="achievement" data-nfuwow-id="<?= $achId ?>"><?= $achId ?></span><span class="js-nfuwow-name"></span></td>
                    <td><?= htmlspecialchars(format_datetime($a['date'] ?? null)) ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if(empty($achievements['unlocks'] ?? [])): ?><tr class="js-empty-row"><td colspan="2" class="char-empty-cell">&nbsp;<?= htmlspecialchars(__('app.character.show.achievements.empty_unlocks')) ?></td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div>
          <h4 class="char-sub-header"><?= htmlspecialchars(__('app.character.show.achievements.progress')) ?></h4>
          <div class="char-table-scroll char-scroll-400">
            <table class="table table--compact">
              <thead>
                <tr>
                  <th><?= htmlspecialchars(__('app.character.show.achievements.criteria')) ?></th>
                  <th><?= htmlspecialchars(__('app.character.show.achievements.counter')) ?></th>
                  <th><?= htmlspecialchars(__('app.character.show.achievements.date')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach(($achievements['progress'] ?? []) as $p): ?>
                  <tr>
                    <?php $critId = (int)($p['criteria'] ?? 0); ?>
                    <td><span class="js-nfuwow" data-nfuwow-type="achievementcriteria" data-nfuwow-id="<?= $critId ?>"><?= $critId ?></span><span class="js-nfuwow-name"></span></td>
                    <td><?= (int)$p['counter'] ?></td>
                    <td><?= htmlspecialchars(format_datetime($p['date'] ?? null)) ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if(empty($achievements['progress'] ?? [])): ?><tr class="js-empty-row"><td colspan="3" class="char-empty-cell">&nbsp;<?= htmlspecialchars(__('app.character.show.achievements.empty_progress')) ?></td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

<?php endif; ?>
<?php include __DIR__.'/../layouts/base_bottom.php'; ?>