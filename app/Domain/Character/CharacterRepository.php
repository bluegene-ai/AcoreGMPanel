<?php
/**
 * File: app/Domain/Character/CharacterRepository.php
 * Purpose: Realm-aware character data access and moderation helpers.
 */

namespace Acme\Panel\Domain\Character;

use PDO;
use Acme\Panel\Domain\Support\MultiServerRepository;
use Acme\Panel\Support\Paginator;

class CharacterRepository extends MultiServerRepository
{
    public function search(array $filters,int $page,int $perPage,bool $loadAll,string $sort): Paginator
    {
        $page = max(1,$page);
        $perPage = max(1,$perPage);

        $name = trim((string)($filters['name'] ?? ''));
        $guid = (int)($filters['guid'] ?? 0);
        $accountName = trim((string)($filters['account'] ?? ''));
        $online = $filters['online'] ?? 'any';
        $levelMin = (int)($filters['level_min'] ?? 0);
        $levelMax = (int)($filters['level_max'] ?? 0);

        $hasCriteria = $loadAll || $name !== '' || $guid > 0 || $accountName !== '' || $levelMin > 0 || $levelMax > 0 || in_array($online,['online','offline'],true);
        if(!$hasCriteria){
            return new Paginator([],0,$page,$perPage);
        }

        $pdo = $this->characters();
        $wheres = [];
        $params = [];

        if($name !== ''){
            $wheres[] = 'c.name LIKE :name';
            $params[':name'] = '%'.$name.'%';
        }
        if($guid > 0){
            $wheres[] = 'c.guid = :guid';
            $params[':guid'] = $guid;
        }
        if($levelMin > 0){
            $wheres[] = 'c.level >= :lmin';
            $params[':lmin'] = $levelMin;
        }
        if($levelMax > 0){
            $wheres[] = 'c.level <= :lmax';
            $params[':lmax'] = $levelMax;
        }
        if(in_array($online,['online','offline'],true)){
            $wheres[] = $online === 'online' ? 'c.online = 1' : 'c.online = 0';
        }

        if($accountName !== ''){
            $auth = $this->auth();
            $st = $auth->prepare('SELECT id FROM account WHERE username LIKE :u LIMIT 200');
            $st->execute([':u'=>'%'.$accountName.'%']);
            $ids = $st->fetchAll(PDO::FETCH_COLUMN,0);
            $ids = array_values(array_unique(array_map('intval',$ids)));
            if(!$ids){
                return new Paginator([],0,$page,$perPage);
            }
            $ph = [];
            foreach($ids as $idx=>$id){ $k=':acc'.$idx; $ph[]=$k; $params[$k]=$id; }
            $wheres[] = 'c.account IN ('.implode(',',$ph).')';
        }

        $whereSql = $wheres ? 'WHERE '.implode(' AND ',$wheres) : '';

        $sortMap = [
            'guid_desc' => 'c.guid DESC',
            'guid_asc' => 'c.guid ASC',
            'logout_desc' => 'c.logout_time DESC, c.guid DESC',
            'logout_asc' => 'c.logout_time ASC, c.guid ASC',
            'level_desc' => 'c.level DESC, c.guid DESC',
            'level_asc' => 'c.level ASC, c.guid ASC',
            'online_desc' => 'c.online DESC, c.guid DESC',
            'online_asc' => 'c.online ASC, c.guid ASC',
        ];
        $orderBy = $sortMap[$sort] ?? $sortMap['guid_desc'];

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM characters c $whereSql");
        foreach($params as $k=>$v){ $cnt->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
        $cnt->execute();
        $total = (int)$cnt->fetchColumn();

        $offset = ($page-1)*$perPage;
        $sql = "SELECT c.guid,c.name,c.account,c.level,c.class,c.race,c.gender,c.map,c.zone,c.online,c.logout_time
                 FROM characters c
                 $whereSql
                 ORDER BY $orderBy
                 LIMIT :limit OFFSET :offset";
        $st = $pdo->prepare($sql);
        foreach($params as $k=>$v){ $st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
        $st->bindValue(':limit',$perPage,PDO::PARAM_INT);
        $st->bindValue(':offset',$offset,PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        if(!$rows){
            return new Paginator([],0,$page,$perPage);
        }

        $accountIds = array_values(array_unique(array_map(fn($r)=>(int)$r['account'],$rows)));
        $accountMap = $this->fetchAccounts($accountIds);

        foreach($rows as &$r){
            $accId = (int)($r['account'] ?? 0);
            if(isset($accountMap[$accId])){
                $r['account_username'] = $accountMap[$accId]['username'];
                $r['gmlevel'] = $accountMap[$accId]['gmlevel'];
            }
            $r['online'] = (int)$r['online'];
        }
        unset($r);

        $this->attachBans($rows);

        return new Paginator($rows,$total,$page,$perPage);
    }

    public function findSummary(int $guid): ?array
    {
        $pdo = $this->characters();
        $st = $pdo->prepare('SELECT guid,name,account,race,class,gender,level,money,xp,position_x,position_y,position_z,map,zone,online,totaltime,logout_time,at_login,stable_slots FROM characters WHERE guid=:g LIMIT 1');
        $st->execute([':g'=>$guid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if(!$row){
            return null;
        }
        $row['online'] = (int)$row['online'];
        $row['money'] = (int)$row['money'];
        $row['level'] = (int)$row['level'];
        $row['account'] = (int)$row['account'];

        $row['homebind'] = $this->homebind($guid);

        $account = $this->fetchAccounts([$row['account']]);
        if(isset($account[$row['account']])){
            $row['account_username'] = $account[$row['account']]['username'];
            $row['gmlevel'] = $account[$row['account']]['gmlevel'];
        }

        $row['ban'] = $this->banStatus($guid);

        return $row;
    }

    public function inventory(int $guid): array
    {
        $pdo = $this->characters();
        $sql = 'SELECT ci.bag,ci.slot,ci.item,ii.itemEntry,ii.count,ii.randomPropertyId,ii.durability,ii.text
                FROM character_inventory ci
                LEFT JOIN item_instance ii ON ci.item=ii.guid
                WHERE ci.guid=:g
                ORDER BY ci.bag ASC, ci.slot ASC';
        $st = $pdo->prepare($sql);
        $st->execute([':g'=>$guid]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function skills(int $guid): array
    {
        $pdo = $this->characters();
        $st = $pdo->prepare('SELECT skill, value, max FROM character_skills WHERE guid=:g ORDER BY skill ASC');
        $st->execute([':g'=>$guid]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function spells(int $guid): array
    {
        $pdo = $this->characters();
        try {
            $st = $pdo->prepare('SELECT spell, active, disabled FROM character_spell WHERE guid=:g ORDER BY spell ASC');
            $st->execute([':g'=>$guid]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch(\Throwable $e) {
            try {
                $colsStmt = $pdo->query('SHOW COLUMNS FROM character_spell');
                $colsRaw = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
                $cols = array_map(fn($c)=>strtolower($c['Field'] ?? ''), $colsRaw);
                $hasActive = in_array('active',$cols,true);
                $hasDisabled = in_array('disabled',$cols,true);
                $select = [ 'spell' ];
                $select[] = $hasActive ? 'active' : '1 AS active';
                $select[] = $hasDisabled ? 'disabled' : '0 AS disabled';
                $sql = 'SELECT '.implode(', ',$select).' FROM character_spell WHERE guid=:g ORDER BY spell ASC';
                $st = $pdo->prepare($sql);
                $st->execute([':g'=>$guid]);
                return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch(\Throwable $e2) {
                return [];
            }
        }
    }

    public function reputations(int $guid): array
    {
        $pdo = $this->characters();
        $st = $pdo->prepare('SELECT faction, standing, flags FROM character_reputation WHERE guid=:g ORDER BY faction ASC');
        $st->execute([':g'=>$guid]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function quests(int $guid): array
    {
        $pdo = $this->characters();
        $st = $pdo->prepare('SELECT quest, status, timer, mobcount1, mobcount2, mobcount3, mobcount4, itemcount1, itemcount2, itemcount3, itemcount4 FROM character_queststatus WHERE guid=:g ORDER BY quest ASC');
        $st->execute([':g'=>$guid]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $daily = $this->questsFromTable($guid,'character_queststatus_daily','quest');
        $weekly = $this->questsFromTable($guid,'character_queststatus_weekly','quest');
        return [
            'regular' => $rows,
            'daily' => $daily,
            'weekly' => $weekly,
        ];
    }

    private function questsFromTable(int $guid,string $table,string $col): array
    {
        $pdo = $this->characters();
        try {
            $st = $pdo->prepare("SELECT $col FROM $table WHERE guid=:g ORDER BY $col ASC");
            $st->execute([':g'=>$guid]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch(\Throwable $e){
            return [];
        }
    }

    public function auras(int $guid): array
    {
        $pdo = $this->characters();
        try {
            $st = $pdo->prepare('SELECT caster_guid, item_guid, spell, effect_mask, amount0, amount1, amount2, remaincharges, maxduration, remaintime FROM character_aura WHERE guid=:g ORDER BY spell ASC');
            $st->execute([':g'=>$guid]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch(\Throwable $e) {
            try {
                $colsStmt = $pdo->query('SHOW COLUMNS FROM character_aura');
                $colsRaw = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
                $cols = array_map(fn($c)=>strtolower($c['Field'] ?? ''), $colsRaw);

                $pick = function(array $candidates, string $alias) use ($cols): string {
                    foreach($candidates as $col){
                        if(in_array(strtolower($col), $cols, true)){
                            return $col . ' AS ' . $alias;
                        }
                    }
                    return 'NULL AS ' . $alias;
                };

                $select = [
                    $pick(['spell'],'spell'),
                    $pick(['caster_guid','casterGuid'],'caster_guid'),
                    $pick(['item_guid','itemGuid'],'item_guid'),
                    $pick(['effect_mask','effectMask'],'effect_mask'),
                    $pick(['amount0'],'amount0'),
                    $pick(['amount1'],'amount1'),
                    $pick(['amount2'],'amount2'),
                    $pick(['remaincharges','remainCharges'],'remaincharges'),
                    $pick(['maxduration','maxDuration'],'maxduration'),
                    $pick(['remaintime','remainTime'],'remaintime'),
                ];

                $sql = 'SELECT '.implode(', ',$select).' FROM character_aura WHERE guid=:g ORDER BY spell ASC';
                $st = $pdo->prepare($sql);
                $st->execute([':g'=>$guid]);
                return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch(\Throwable $e2) {
                return [];
            }
        }
    }

    public function cooldowns(int $guid): array
    {
        $pdo = $this->characters();
        try {
            $st = $pdo->prepare('SELECT spellid, itemid, time, category FROM character_cooldown WHERE guid=:g ORDER BY time DESC');
            $st->execute([':g'=>$guid]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch(\Throwable $e) {
            try {
                // If the table or some columns are missing, adapt to what's available per AzerothCore schema.
                $tables = $pdo->query("SHOW TABLES LIKE 'character_cooldown'");
                $hasTable = $tables && $tables->fetchColumn();
                if(!$hasTable){
                    return [];
                }

                $colsStmt = $pdo->query('SHOW COLUMNS FROM character_cooldown');
                $colsRaw = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
                $cols = array_map(fn($c)=>strtolower($c['Field'] ?? ''), $colsRaw);

                $pick = function(array $candidates, string $alias) use ($cols): string {
                    foreach($candidates as $col){
                        if(in_array(strtolower($col), $cols, true)){
                            return $col . ' AS ' . $alias;
                        }
                    }
                    return 'NULL AS ' . $alias;
                };

                $select = [
                    $pick(['spellid','spell','spell_id'],'spellid'),
                    $pick(['itemid','item','item_id'],'itemid'),
                    $pick(['time','start_time','end_time'],'time'),
                    $pick(['category','categoryId','category_id'],'category'),
                ];

                $sql = 'SELECT '.implode(', ',$select).' FROM character_cooldown WHERE guid=:g ORDER BY time DESC';
                $st = $pdo->prepare($sql);
                $st->execute([':g'=>$guid]);
                return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch(\Throwable $e2) {
                return [];
            }
        }
    }

    public function achievements(int $guid): array
    {
        $pdo = $this->characters();
        try {
            $st = $pdo->prepare('SELECT achievement, date FROM character_achievement WHERE guid=:g ORDER BY date DESC');
            $st->execute([':g'=>$guid]);
            $unlocks = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch(\Throwable $e){ $unlocks = []; }
        try {
            $st2 = $pdo->prepare('SELECT criteria, counter, date FROM character_achievement_progress WHERE guid=:g ORDER BY date DESC');
            $st2->execute([':g'=>$guid]);
            $progress = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch(\Throwable $e){ $progress = []; }
        return ['unlocks'=>$unlocks,'progress'=>$progress];
    }

    public function mailCount(int $guid): int
    {
        $pdo = $this->characters();
        try {
            $st = $pdo->prepare('SELECT COUNT(*) FROM mail WHERE receiver=:g');
            $st->execute([':g'=>$guid]);
            return (int)$st->fetchColumn();
        } catch(\Throwable $e){
            return 0;
        }
    }

    public function banStatus(int $guid): ?array
    {
        $pdo = $this->characters();
        $st = $pdo->prepare('SELECT guid,bandate,unbandate,banreason,active FROM character_banned WHERE guid=:g AND active=1 ORDER BY bandate DESC LIMIT 1');
        $st->execute([':g'=>$guid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if(!$row){
            return null;
        }
        $now = time();
        $unbandate = (int)$row['unbandate'];
        $permanent = ($unbandate === 0) || ($unbandate <= $now);
        $remaining = $permanent ? -1 : max(0, $unbandate - $now);
        return [
            'bandate'=>(int)$row['bandate'],
            'unbandate'=>(int)$row['unbandate'],
            'banreason'=>$row['banreason'],
            'permanent'=>$permanent,
            'remaining_seconds'=>$remaining,
        ];
    }

    public function ban(int $guid,string $reason,int $durationHours=0): bool
    {
        $pdo = $this->characters();
        $bandate = time();
        $unban = $durationHours > 0 ? $bandate + $durationHours*3600 : 0;
        $st = $pdo->prepare('INSERT INTO character_banned (guid,bandate,unbandate,bannedby,banreason,active) VALUES (:g,:bd,:ud,:bb,:br,1)');
        return $st->execute([':g'=>$guid,':bd'=>$bandate,':ud'=>$unban,':bb'=>'panel',':br'=>$reason]);
    }

    public function unban(int $guid): int
    {
        $pdo = $this->characters();
        $st = $pdo->prepare('UPDATE character_banned SET active=0 WHERE guid=:g AND active=1');
        $st->execute([':g'=>$guid]);
        return $st->rowCount();
    }

    public function setLevel(int $guid,int $level): bool
    {
        $pdo = $this->characters();
        $st = $pdo->prepare('UPDATE characters SET level=:lvl WHERE guid=:g');
        return $st->execute([':lvl'=>$level, ':g'=>$guid]);
    }

    public function setGold(int $guid,int $copper): bool
    {
        $pdo = $this->characters();
        $copper = max(0,$copper);
        $st = $pdo->prepare('UPDATE characters SET money=:m WHERE guid=:g');
        return $st->execute([':m'=>$copper, ':g'=>$guid]);
    }

    public function teleport(int $guid,int $map,int $zone,float $x,float $y,float $z): bool
    {
        $pdo = $this->characters();
        $st = $pdo->prepare('UPDATE characters SET map=:map,zone=:zone,position_x=:x,position_y=:y,position_z=:z WHERE guid=:g');
        return $st->execute([
            ':map'=>$map,
            ':zone'=>$zone,
            ':x'=>$x,
            ':y'=>$y,
            ':z'=>$z,
            ':g'=>$guid,
        ]);
    }

    public function unstuck(int $guid): bool
    {
        $hb = $this->homebind($guid);
        if(!$hb){
            return false;
        }
        return $this->teleport($guid,(int)$hb['mapId'],(int)$hb['zoneId'],(float)$hb['posX'],(float)$hb['posY'],(float)$hb['posZ']);
    }

    public function resetTalents(int $guid): bool
    {
        $pdo = $this->characters();
        $st = $pdo->prepare('DELETE FROM character_talent WHERE guid=:g');
        return $st->execute([':g'=>$guid]);
    }

    public function resetSpells(int $guid): bool
    {
        $pdo = $this->characters();
        $st = $pdo->prepare('DELETE FROM character_spell WHERE guid=:g');
        return $st->execute([':g'=>$guid]);
    }

    public function resetCooldowns(int $guid): bool
    {
        $pdo = $this->characters();
        $st = $pdo->prepare('DELETE FROM character_cooldown WHERE guid=:g');
        return $st->execute([':g'=>$guid]);
    }

    public function setRenameFlag(int $guid): bool
    {
        $pdo = $this->characters();
        $st = $pdo->prepare('UPDATE characters SET at_login = COALESCE(at_login,0) | 1 WHERE guid=:g');
        return $st->execute([':g'=>$guid]);
    }

    private function deleteFkChildren(
        \PDO $pdo,
        string $referencedTable,
        string $referencedColumn,
        int $value
    ): void {
        try {
            $st = $pdo->prepare(
                'SELECT TABLE_NAME, COLUMN_NAME'
                .' FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE'
                .' WHERE TABLE_SCHEMA = DATABASE()'
                .' AND REFERENCED_TABLE_NAME = :rt'
                .' AND REFERENCED_COLUMN_NAME = :rc'
            );
            $st->execute([':rt' => $referencedTable, ':rc' => $referencedColumn]);
            $refs = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch(\Throwable $e){
            return;
        }

        foreach($refs as $ref){
            $table = (string)($ref['TABLE_NAME'] ?? '');
            $col = (string)($ref['COLUMN_NAME'] ?? '');
            if($table==='' || $col==='') continue;
            if(strcasecmp($table, $referencedTable) === 0) continue;

            $tableSafe = str_replace('`','``',$table);
            $colSafe = str_replace('`','``',$col);
            try {
                $pdo->prepare("DELETE FROM `{$tableSafe}` WHERE `{$colSafe}`=:v")->execute([':v'=>$value]);
            } catch(\Throwable $e){
                // ignore
            }
        }
    }

    public function deleteCharacter(int $guid): bool
    {
        return (bool)($this->deleteCharacterDetailed($guid)['success'] ?? false);
    }

    public function deleteCharacterDetailed(int $guid): array
    {
        $guid = (int)$guid;
        if($guid <= 0){
            return ['success'=>false,'message'=>'invalid guid'];
        }

        $pdo = $this->characters();

        // Ensure target exists to avoid reporting success on no-op.
        try {
            $st = $pdo->prepare('SELECT 1 FROM characters WHERE guid=:g LIMIT 1');
            $st->execute([':g'=>$guid]);
            if(!(bool)$st->fetchColumn()){
                return ['success'=>false,'message'=>'not found'];
            }
        } catch(\Throwable $e){
            return ['success'=>false,'message'=>$e->getMessage()];
        }

        $step = 'begin';
        $pdo->beginTransaction();
        try {
            // If FK constraints exist, delete dependent rows first.
            $step = 'delete_fk_children';
            $this->deleteFkChildren($pdo, 'characters', 'guid', $guid);

            // Delete related character data first (AzerothCore characters DB tables).
            $tablesByGuid = [
                'arena_team_member',
                'character_account_data',
                'character_achievement',
                'character_achievement_progress',
                'character_action',
                'character_aura',
                'character_aura_effect',
                'character_battleground_data',
                'character_battleground_random',
                'character_banned',
                'character_cooldown',
                'character_declinedname',
                'character_equipmentsets',
                'character_gifts',
                'character_glyphs',
                'character_homebind',
                'character_instance',
                'character_inventory',
                'character_pet',
                'character_pet_declinedname',
                'character_queststatus',
                'character_queststatus_daily',
                'character_queststatus_rewarded',
                'character_queststatus_seasonal',
                'character_queststatus_weekly',
                'character_reputation',
                'character_skills',
                'character_social',
                'character_spell',
                'character_spell_cooldown',
                'character_stats',
                'character_talent',
                'corpse',
                'guild_member',
            ];

            foreach($tablesByGuid as $t){
                try {
                    $step = 'delete_table:' . $t;
                    $pdo->prepare("DELETE FROM {$t} WHERE guid=:g")->execute([':g'=>$guid]);
                } catch(\PDOException $e){
                    $code = (string)$e->getCode();
                    if($code !== '42S02' && $code !== '42S22'){
                        throw $e;
                    }
                }
            }

            // Mail (receiver/sender)
            try {
                $mailIds = [];
                // NOTE: do not reuse the same named placeholder twice (can trigger HY093)
                $step = 'mail_select_ids';
                $st = $pdo->prepare('SELECT id FROM mail WHERE receiver=:g1 OR sender=:g2');
                $st->execute([':g1'=>$guid, ':g2'=>$guid]);
                $mailIds = array_values(array_unique(array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN, 0) ?: [])));
                if($mailIds){
                    $in = implode(',', array_fill(0, count($mailIds), '?'));
                    try {
                        $step = 'mail_items_delete_in';
                        $pdo->prepare("DELETE FROM mail_items WHERE mail_id IN ($in)")->execute($mailIds);
                    } catch(\PDOException $e){
                        $code = (string)$e->getCode();
                        if($code !== '42S02' && $code !== '42S22'){
                            throw $e;
                        }
                    }
                    $step = 'mail_delete_in';
                    $pdo->prepare("DELETE FROM mail WHERE id IN ($in)")->execute($mailIds);
                } else {
                    $step = 'mail_delete_by_sender_receiver';
                    $pdo->prepare('DELETE FROM mail WHERE receiver=:g1 OR sender=:g2')->execute([':g1'=>$guid, ':g2'=>$guid]);
                }
            } catch(\PDOException $e){
                $code = (string)$e->getCode();
                if($code !== '42S02' && $code !== '42S22'){
                    throw $e;
                }
            }

            // Auctionhouse (owner column names differ)
            try {
                $step = 'auctionhouse_delete';
                $this->deleteByAnyColumn($pdo, 'auctionhouse', ['itemowner','owner'], $guid);
            } catch(\PDOException $e){
                $code = (string)$e->getCode();
                if($code !== '42S02' && $code !== '42S22'){
                    throw $e;
                }
            }

            // Item instances owned by character (if supported by schema)
            try {
                if($this->tableHasColumn($pdo, 'item_instance', 'owner_guid')){
                    $step = 'item_instance_delete_owner_guid';
                    $pdo->prepare('DELETE FROM item_instance WHERE owner_guid=:g')->execute([':g'=>$guid]);
                } elseif($this->tableHasColumn($pdo, 'item_instance', 'owner')){
                    $step = 'item_instance_delete_owner';
                    $pdo->prepare('DELETE FROM item_instance WHERE owner=:g')->execute([':g'=>$guid]);
                }
            } catch(\PDOException $e){
                $code = (string)$e->getCode();
                if($code !== '42S02' && $code !== '42S22'){
                    throw $e;
                }
            }

            // Finally delete the character row.
            $step = 'characters_delete';
            $st = $pdo->prepare('DELETE FROM characters WHERE guid=:g');
            $st->execute([':g'=>$guid]);
            if($st->rowCount() <= 0){
                throw new \RuntimeException('Character not deleted');
            }

            $pdo->commit();
            return ['success'=>true];
        } catch(\Throwable $e){
            $pdo->rollBack();
            $msg = $e->getMessage();
            if($e instanceof \PDOException){
                $code = (string)$e->getCode();
                $msg = $code !== '' ? ($code.': '.$msg) : $msg;
            }
            return ['success'=>false,'message'=>$step.': '.$msg];
        }
    }

    private function tableHasColumn(\PDO $pdo, string $table, string $column): bool
    {
        try {
            $st = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1');
            $st->execute([':t'=>$table,':c'=>$column]);
            return (bool)$st->fetchColumn();
        } catch(\Throwable $e){
            return false;
        }
    }

    private function deleteByAnyColumn(\PDO $pdo, string $table, array $columns, int $guid): void
    {
        foreach($columns as $col){
            try {
                $pdo->prepare("DELETE FROM {$table} WHERE {$col}=:g")->execute([':g'=>$guid]);
                return;
            } catch(\PDOException $e){
                $code = (string)$e->getCode();
                if($code === '42S02'){
                    return; // table missing
                }
                if($code === '42S22'){
                    continue; // try next column
                }
                throw $e;
            }
        }
    }

    private function homebind(int $guid): ?array
    {
        $pdo = $this->characters();
        $st = $pdo->prepare('SELECT mapId,zoneId,posX,posY,posZ FROM character_homebind WHERE guid=:g LIMIT 1');
        $st->execute([':g'=>$guid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function fetchAccounts(array $ids): array
    {
        if(!$ids){
            return [];
        }
        $auth = $this->auth();
        $ph = [];
        $params = [];
        foreach(array_values(array_unique($ids)) as $idx=>$id){ $k=':a'.$idx; $ph[]=$k; $params[$k]=(int)$id; }
        $sql = 'SELECT a.id,a.username,aa.gmlevel FROM account a LEFT JOIN account_access aa ON aa.id=a.id WHERE a.id IN ('.implode(',',$ph).')';
        $st = $auth->prepare($sql);
        foreach($params as $k=>$v){ $st->bindValue($k,$v,PDO::PARAM_INT); }
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach($rows as $r){ $map[(int)$r['id']] = ['username'=>$r['username'],'gmlevel'=>$r['gmlevel'] ?? null]; }
        return $map;
    }

    private function attachBans(array &$rows): void
    {
        if(!$rows){
            return;
        }
        $guids = array_values(array_unique(array_map(fn($r)=>(int)$r['guid'],$rows)));
        if(!$guids){
            return;
        }
        $pdo = $this->characters();
        $ph=[]; $params=[];
        foreach($guids as $idx=>$g){ $k=':g'.$idx; $ph[]=$k; $params[$k]=$g; }
        $sql = 'SELECT guid,bandate,unbandate,banreason,active FROM character_banned WHERE active=1 AND guid IN ('.implode(',',$ph).')';
        $st = $pdo->prepare($sql);
        foreach($params as $k=>$v){ $st->bindValue($k,$v,PDO::PARAM_INT); }
        $st->execute();
        $bans = $st->fetchAll(PDO::FETCH_ASSOC);
        if(!$bans){ return; }
        $banMap = [];
        $now = time();
        foreach($bans as $b){
            $unbandate = (int)($b['unbandate'] ?? 0);
            $permanent = ($unbandate === 0) || ($unbandate <= $now);
            $b['permanent'] = $permanent;
            $b['remaining_seconds'] = $permanent ? -1 : max(0, $unbandate - $now);
            $banMap[(int)$b['guid']] = $b;
        }
        foreach($rows as &$r){
            $g=(int)$r['guid'];
            if(isset($banMap[$g])){
                $r['ban'] = $banMap[$g];
            }
        }
        unset($r);
    }
}
