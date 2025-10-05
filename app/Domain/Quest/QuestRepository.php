<?php
/**
 * File: app/Domain/Quest/QuestRepository.php
 * Purpose: Defines class QuestRepository for the app/Domain/Quest module.
 * Classes:
 *   - QuestRepository
 * Functions:
 *   - __construct()
 *   - questInfoLabelOverrides()
 *   - repoMessage()
 *   - repoError()
 *   - validColumns()
 *   - questInfoOptions()
 *   - questInfoFallback()
 *   - firstQuestId()
 *   - search()
 *   - enrichListRows()
 *   - loadQuestXpByLevel()
 *   - loadItemSummaries()
 *   - loadQuestInfoLabels()
 *   - resolveQuestXp()
 *   - formatMoney()
 *   - buildRewardItems()
 *   - describeItem()
 *   - isMissingTable()
 *   - find()
 *   - create()
 *   - delete()
 *   - updatePartial()
 *   - execLimitedSql()
 *   - shortVal()
 *   - logsDir()
 *   - appendDeletedLog()
 *   - appendSqlLog()
 *   - currentUser()
 *   - tailLog()
 *   - parseLogLine()
 *   - rowHash()
 */

namespace Acme\Panel\Domain\Quest;

use Acme\Panel\Core\Lang;
use Acme\Panel\Domain\Support\MultiServerRepository;
use Acme\Panel\Support\Audit;
use Acme\Panel\Support\Paginator;
use Acme\Panel\Support\ServerContext;
use Acme\Panel\Support\Snapshot;
use PDO;
use PDOException;

class QuestRepository extends MultiServerRepository
{
    private PDO $world;
    private ?array $questInfoCache = null;
    private const QUEST_INFO_LABEL_KEYS = [
        0 => 'app.quest.repository.info_labels.0',
        1 => 'app.quest.repository.info_labels.1',
        21 => 'app.quest.repository.info_labels.21',
        41 => 'app.quest.repository.info_labels.41',
        62 => 'app.quest.repository.info_labels.62',
        81 => 'app.quest.repository.info_labels.81',
        82 => 'app.quest.repository.info_labels.82',
        83 => 'app.quest.repository.info_labels.83',
        84 => 'app.quest.repository.info_labels.84',
        85 => 'app.quest.repository.info_labels.85',
        88 => 'app.quest.repository.info_labels.88',
        89 => 'app.quest.repository.info_labels.89',
    ];
    public function __construct(){ parent::__construct(); $this->world = $this->world(); }

    private function questInfoLabelOverrides(): array
    {
        static $cache = null;
        if($cache !== null){
            return $cache;
        }
        $labels = [];
        foreach(self::QUEST_INFO_LABEL_KEYS as $id => $key){
            $labels[$id] = Lang::get($key);
        }
        $cache = $labels;
        return $cache;
    }

    private function repoMessage(string $key, array $params = []): string
    {
        return Lang::get('app.quest.repository.messages.'.$key, $params);
    }

    private function repoError(string $key, array $params = []): string
    {
        return Lang::get('app.quest.repository.errors.'.$key, $params);
    }





    public static function validColumns(): array
    {
        return [
            'ID','QuestType','QuestLevel','MinLevel','QuestSortID','QuestInfoID','SuggestedGroupNum','Flags','RewardNextQuest','RewardXPDifficulty','RewardMoney','RewardMoneyDifficulty','RewardDisplaySpell','RewardSpell','RewardHonor','RewardKillHonor','RewardTitle','RewardTalents','RewardArenaPoints','LogTitle','LogDescription','QuestDescription','AreaDescription','QuestCompletionLog','ObjectiveText1','ObjectiveText2','ObjectiveText3','ObjectiveText4','RequiredFactionId1','RequiredFactionValue1','RequiredFactionId2','RequiredFactionValue2','RequiredPlayerKills','TimeAllowed','AllowableRaces','StartItem','RewardItem1','RewardAmount1','RewardItem2','RewardAmount2','RewardItem3','RewardAmount3','RewardItem4','RewardAmount4','RewardChoiceItemID1','RewardChoiceItemQuantity1','RewardChoiceItemID2','RewardChoiceItemQuantity2','RewardChoiceItemID3','RewardChoiceItemQuantity3','RewardChoiceItemID4','RewardChoiceItemQuantity4','RewardChoiceItemID5','RewardChoiceItemQuantity5','RewardChoiceItemID6','RewardChoiceItemQuantity6','RequiredNpcOrGo1','RequiredNpcOrGoCount1','RequiredNpcOrGo2','RequiredNpcOrGoCount2','RequiredNpcOrGo3','RequiredNpcOrGoCount3','RequiredNpcOrGo4','RequiredNpcOrGoCount4','RequiredItemId1','RequiredItemCount1','RequiredItemId2','RequiredItemCount2','RequiredItemId3','RequiredItemCount3','RequiredItemId4','RequiredItemCount4','RequiredItemId5','RequiredItemCount5','RequiredItemId6','RequiredItemCount6','RewardFactionID1','RewardFactionValue1','RewardFactionOverride1','RewardFactionID2','RewardFactionValue2','RewardFactionOverride2','RewardFactionID3','RewardFactionValue3','RewardFactionOverride3','RewardFactionID4','RewardFactionValue4','RewardFactionOverride4','RewardFactionID5','RewardFactionValue5','RewardFactionOverride5','POIContinent','POIx','POIy','POIPriority','ItemDrop1','ItemDropQuantity1','ItemDrop2','ItemDropQuantity2','ItemDrop3','ItemDropQuantity3','ItemDrop4','ItemDropQuantity4','Unknown0','VerifiedBuild'
        ];
    }

    public function questInfoOptions(): array
    {
        if($this->questInfoCache !== null){
            return $this->questInfoCache;
        }
        $map = [];
        try{
            $stmt = $this->world->query('SELECT ID,Name FROM quest_info ORDER BY ID ASC');
        }catch(PDOException $e){
            if($this->isMissingTable($e, 'quest_info')){
                $this->questInfoCache = $this->questInfoFallback();
                return $this->questInfoCache;
            }
            throw $e;
        }
        if($stmt){
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
                $id = isset($row['ID']) ? (int)$row['ID'] : 0;
                if($id < 0){
                    continue;
                }
                $name = trim((string)($row['Name'] ?? ''));
                if($name === ''){
                    $name = self::QUEST_INFO_LABEL_OVERRIDES[$id] ?? ('#'.$id);
                }
                $map[$id] = $name;
            }
        }
        $overrides = $this->questInfoLabelOverrides();
        $map = array_replace($map, $overrides);
        foreach($overrides as $id => $label){
            if(!isset($map[$id])){
                $map[$id] = $label;
            }
        }
        ksort($map, SORT_NUMERIC);
        $this->questInfoCache = $map;
        return $this->questInfoCache;
    }

    private function questInfoFallback(): array
    {
        $map = $this->questInfoLabelOverrides();
        ksort($map, SORT_NUMERIC);
        return $map;
    }

    public function firstQuestId(): ?int
    {
        $stmt = $this->world->query('SELECT ID FROM quest_template ORDER BY ID ASC LIMIT 1');
        if(!$stmt){
            return null;
        }
        $value = $stmt->fetchColumn();
        return $value === false ? null : (int)$value;
    }

    public function search(array $opts): Paginator
    {
        $id = isset($opts['filter_id']) ? (int)$opts['filter_id'] : 0;
        $title = trim($opts['filter_title'] ?? '');
        $levelOp = $opts['filter_level_op'] ?? 'any'; $levelVal = $opts['filter_level_val'] ?? '';
        $minOp = $opts['filter_min_level_op'] ?? 'any'; $minVal = $opts['filter_min_level_val'] ?? '';
    $questInfoFilter = $opts['filter_type'] ?? '';
        $sortBy = $opts['sort_by'] ?? 'ID'; $sortDir = strtoupper($opts['sort_dir'] ?? 'ASC');
        $allowedSort=['ID','QuestLevel','MinLevel','LogTitle']; if(!in_array($sortBy,$allowedSort,true)) $sortBy='ID'; if(!in_array($sortDir,['ASC','DESC'],true)) $sortDir='ASC';
        $page=max(1,(int)($opts['page']??1)); $limit=max(10,min(200,(int)($opts['limit']??50))); $offset=($page-1)*$limit;
        $where=[]; $params=[];
        if($id>0){ $where[]='ID = :id'; $params[':id']=$id; }
        if($title!==''){ $where[]='LogTitle LIKE :title'; $params[':title']='%'.$title.'%'; }
        if($levelVal!=='' && in_array($levelOp,['ge','le','eq','between'],true)){
            if($levelOp==='between'){
                $parts=explode('-',$levelVal); if(count($parts)==2 && is_numeric($parts[0])&&is_numeric($parts[1])){ $where[]='QuestLevel BETWEEN :lmin AND :lmax'; $params[':lmin']=(int)$parts[0]; $params[':lmax']=(int)$parts[1]; }
            } else { $map=['ge'=>'>=','le'=>'<=','eq'=>'=']; if(is_numeric($levelVal)) { $where[]='QuestLevel '.$map[$levelOp].' :lvl'; $params[':lvl']=(int)$levelVal; } }
        }
        if($minVal!=='' && in_array($minOp,['ge','le','eq','between'],true)){
            if($minOp==='between'){
                $parts=explode('-',$minVal); if(count($parts)==2 && is_numeric($parts[0])&&is_numeric($parts[1])){ $where[]='MinLevel BETWEEN :mlmin AND :mlmax'; $params[':mlmin']=(int)$parts[0]; $params[':mlmax']=(int)$parts[1]; }
            } else { $map=['ge'=>'>=','le'=>'<=','eq'=>'=']; if(is_numeric($minVal)) { $where[]='MinLevel '.$map[$minOp].' :mlv'; $params[':mlv']=(int)$minVal; } }
        }
        if($questInfoFilter !== ''){
            $where[]='QuestInfoID = :quest_info';
            $params[':quest_info']=(int)$questInfoFilter;
        }
        $whereSql = $where?(' WHERE '.implode(' AND ',$where)) : '';
        $cnt=$this->world->prepare('SELECT COUNT(*) FROM quest_template'.$whereSql); foreach($params as $k=>$v){ $cnt->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);} $cnt->execute(); $total=(int)$cnt->fetchColumn();
        $sql='SELECT ID,LogTitle,QuestDescription,QuestLevel,MinLevel,QuestType,QuestInfoID,QuestSortID,RewardXPDifficulty,RewardMoney,RewardMoneyDifficulty,
            RewardItem1,RewardAmount1,RewardItem2,RewardAmount2,RewardItem3,RewardAmount3,RewardItem4,RewardAmount4,
            RewardChoiceItemID1,RewardChoiceItemQuantity1,RewardChoiceItemID2,RewardChoiceItemQuantity2,RewardChoiceItemID3,RewardChoiceItemQuantity3,
            RewardChoiceItemID4,RewardChoiceItemQuantity4,RewardChoiceItemID5,RewardChoiceItemQuantity5,RewardChoiceItemID6,RewardChoiceItemQuantity6
            FROM quest_template'.$whereSql.' ORDER BY `'.$sortBy.'` '.$sortDir.' LIMIT :limit OFFSET :offset';
        $st=$this->world->prepare($sql); foreach($params as $k=>$v){ $st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);} $st->bindValue(':limit',$limit,PDO::PARAM_INT); $st->bindValue(':offset',$offset,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll(PDO::FETCH_ASSOC);
        $rows = $this->enrichListRows($rows);
        return new Paginator($rows,$total,$page,$limit);
    }

    private function enrichListRows(array $rows): array
    {
        if(!$rows){
            return $rows;
        }

        $xpMatrix = [];
        $questInfoIds = [];
        $itemIds = [];
        foreach($rows as $row){
            $questLevel = isset($row['QuestLevel']) ? (int)$row['QuestLevel'] : 0;
            $xpDifficulty = isset($row['RewardXPDifficulty']) ? (int)$row['RewardXPDifficulty'] : -1;
            if($questLevel > 0 && $xpDifficulty >= 0){
                if(!isset($xpMatrix[$questLevel])){
                    $xpMatrix[$questLevel] = [];
                }
                $xpMatrix[$questLevel][$xpDifficulty] = true;
            }
            $infoId = isset($row['QuestInfoID']) ? (int)$row['QuestInfoID'] : 0;
            $questInfoIds[$infoId] = true;
            for($i=1;$i<=4;$i++){
                $itemId = isset($row['RewardItem'.$i]) ? (int)$row['RewardItem'.$i] : 0;
                if($itemId>0){
                    $itemIds[$itemId] = true;
                }
            }
            for($i=1;$i<=6;$i++){
                $itemId = isset($row['RewardChoiceItemID'.$i]) ? (int)$row['RewardChoiceItemID'.$i] : 0;
                if($itemId>0){
                    $itemIds[$itemId] = true;
                }
            }
        }

        $xpTable = $this->loadQuestXpByLevel($xpMatrix);
        $itemsMeta = $this->loadItemSummaries(array_keys($itemIds));
        $questInfoMap = $this->loadQuestInfoLabels(array_keys($questInfoIds));

        foreach($rows as &$row){
            $row['reward_money_text'] = $this->formatMoney((int)($row['RewardMoney'] ?? 0));
            $row['reward_money_difficulty'] = isset($row['RewardMoneyDifficulty']) ? (int)$row['RewardMoneyDifficulty'] : -1;
            $row['reward_xp_amount'] = $this->resolveQuestXp((int)($row['QuestLevel'] ?? 0), (int)($row['RewardXPDifficulty'] ?? -1), $xpTable);
            $infoId = isset($row['QuestInfoID']) ? (int)$row['QuestInfoID'] : 0;
            $label = $questInfoMap[$infoId] ?? null;
            if($label === null || $label === ''){
                if($infoId === 0){
                    $overrides = $this->questInfoLabelOverrides();
                    $label = $overrides[0] ?? Lang::get(self::QUEST_INFO_LABEL_KEYS[0]);
                }
            }
            $row['quest_info_label'] = $label;
            $items = $this->buildRewardItems($row,$itemsMeta);
            $row['reward_items_fixed'] = $items['fixed'];
            $row['reward_items_choice'] = $items['choice'];
        }
        unset($row);

        return $rows;
    }

    private function loadQuestXpByLevel(array $xpMatrix): array
    {
        if(!$xpMatrix){
            return [];
        }
        $levels = array_keys($xpMatrix);
        $levels = array_values(array_unique(array_map('intval', $levels)));
        $levels = array_filter($levels, fn($lvl) => $lvl > 0);
        if(!$levels){
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($levels), '?'));
        $sql = 'SELECT ID,Difficulty,Exp FROM quest_xp WHERE ID IN ('.$placeholders.')';
        try{
            $stmt = $this->world->prepare($sql);
        }catch(PDOException $e){
            if($this->isMissingTable($e, 'quest_xp')){
                return [];
            }
            throw $e;
        }
        foreach($levels as $idx => $level){
            $stmt->bindValue($idx+1, $level, PDO::PARAM_INT);
        }
        try{
            $stmt->execute();
        }catch(PDOException $e){
            if($this->isMissingTable($e, 'quest_xp')){
                return [];
            }
            throw $e;
        }
        $map = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            $level = isset($row['ID']) ? (int)$row['ID'] : 0;
            $difficulty = isset($row['Difficulty']) ? (int)$row['Difficulty'] : 0;
            $exp = isset($row['Exp']) ? (int)$row['Exp'] : 0;
            if($level <= 0){
                continue;
            }
            if(!isset($map[$level])){
                $map[$level] = [];
            }
            $map[$level][$difficulty] = $exp;
        }
        return $map;
    }

    private function loadItemSummaries(array $itemIds): array
    {
        if(!$itemIds){
            return [];
        }
        $itemIds = array_values(array_unique(array_map('intval',$itemIds)));
        if(!$itemIds){
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $sql = 'SELECT entry,name,quality FROM item_template WHERE entry IN ('.$placeholders.')';
        $stmt = $this->world->prepare($sql);
        foreach($itemIds as $idx => $itemId){
            $stmt->bindValue($idx+1, $itemId, PDO::PARAM_INT);
        }
        try{
            $stmt->execute();
        }catch(\Throwable $e){
            return [];
        }
        $map = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            $entry = (int)$row['entry'];
            $map[$entry] = [
                'name' => $row['name'] ?? ('#'.$entry),
                'quality' => isset($row['quality']) ? (int)$row['quality'] : null,
            ];
        }
        return $map;
    }

    private function loadQuestInfoLabels(array $infoIds): array
    {
        if(!$infoIds){
            return [];
        }
        $infoIds = array_values(array_unique(array_map('intval', $infoIds)));
        $options = $this->questInfoOptions();
        $map = [];
        foreach($infoIds as $id){
            if(isset($options[$id])){
                $map[$id] = $options[$id];
            }
        }
        return $map;
    }

    private function resolveQuestXp(int $questLevel, int $difficulty, array $xpTable): int
    {
        if($questLevel <= 0){
            return 0;
        }
        $entries = $xpTable[$questLevel] ?? null;
        if(!$entries){
            return 0;
        }
        if($difficulty < 0){
            $difficulty = 0;
        }
        if(isset($entries[$difficulty])){
            return (int)$entries[$difficulty];
        }
        if(isset($entries[0])){
            return (int)$entries[0];
        }

        return (int)max($entries);
    }

    private function formatMoney(int $copper): string
    {
        if($copper <= 0){
            return Lang::get('app.quest.common.na');
        }
        $gold = intdiv($copper, 10000);
        $remainder = $copper % 10000;
        $silver = intdiv($remainder, 100);
        $copperLeft = $remainder % 100;
        $parts = [];
        if($gold > 0){
            $parts[] = Lang::get('app.quest.repository.money.gold', ['value' => $gold]);
        }
        if($silver > 0){
            $parts[] = Lang::get('app.quest.repository.money.silver', ['value' => $silver]);
        }
        if($copperLeft > 0 || !$parts){
            $parts[] = Lang::get('app.quest.repository.money.copper', ['value' => $copperLeft]);
        }
        $separator = Lang::get('app.quest.repository.money.separator');
        return implode($separator, $parts);
    }

    private function buildRewardItems(array $row, array $itemsMeta): array
    {
        $fixed = [];
        for($i=1;$i<=4;$i++){
            $entry = isset($row['RewardItem'.$i]) ? (int)$row['RewardItem'.$i] : 0;
            $quantity = isset($row['RewardAmount'.$i]) ? (int)$row['RewardAmount'.$i] : 0;
            if($entry <= 0 || $quantity <= 0){
                continue;
            }
            $fixed[] = $this->describeItem($entry,$quantity,$itemsMeta);
        }

        $choice = [];
        for($i=1;$i<=6;$i++){
            $entry = isset($row['RewardChoiceItemID'.$i]) ? (int)$row['RewardChoiceItemID'.$i] : 0;
            $quantity = isset($row['RewardChoiceItemQuantity'.$i]) ? (int)$row['RewardChoiceItemQuantity'.$i] : 0;
            if($entry <= 0 || $quantity <= 0){
                continue;
            }
            $choice[] = $this->describeItem($entry,$quantity,$itemsMeta);
        }

        return ['fixed'=>$fixed,'choice'=>$choice];
    }

    private function describeItem(int $entry, int $quantity, array $meta): array
    {
        $info = $meta[$entry] ?? null;
        return [
            'id' => $entry,
            'name' => $info['name'] ?? ('#'.$entry),
            'quality' => $info['quality'] ?? null,
            'quantity' => $quantity,
        ];
    }

    private function isMissingTable(PDOException $e, string $table): bool
    {
        $sqlState = $e->getCode();
        $errorInfo = $e->errorInfo ?? [];
        $driverCode = isset($errorInfo[1]) ? (int)$errorInfo[1] : 0;
        if($sqlState === '42S02' || $driverCode === 1146){
            if($table === ''){
                return true;
            }
            $message = $errorInfo[2] ?? $e->getMessage();
            return stripos($message, $table) !== false;
        }
        return false;
    }

    public function find(int $id): ?array
    { if($id<=0) return null; $st=$this->world->prepare('SELECT * FROM quest_template WHERE ID=:id'); $st->execute([':id'=>$id]); $r=$st->fetch(PDO::FETCH_ASSOC); return $r?:null; }

    public function create(int $newId, ?int $copyId=null): array
    {
        if($newId<=0) return ['success'=>false,'message'=>$this->repoError('invalid_new_id')];
        $ex=$this->world->prepare('SELECT 1 FROM quest_template WHERE ID=:e'); $ex->execute([':e'=>$newId]); if($ex->fetch()) return ['success'=>false,'message'=>$this->repoError('id_exists')];
        if($copyId){
            $src=$this->world->prepare('SELECT * FROM quest_template WHERE ID=:c'); $src->execute([':c'=>$copyId]); $data=$src->fetch(PDO::FETCH_ASSOC); if(!$data) return ['success'=>false,'message'=>$this->repoError('copy_source_missing')];
            $data['ID']=$newId; $cols=array_keys($data); $ph=array_map(fn($c)=>':'.$c,$cols);
            $sql='INSERT INTO quest_template(`'.implode('`,`',$cols).'`) VALUES('.implode(',',$ph).')';
            $ins=$this->world->prepare($sql); foreach($data as $k=>$v){ $ins->bindValue(':'.$k,$v===null?null:$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
            $ok=$ins->execute(); if($ok){ Audit::log('quest','create',(string)$newId,['copy'=>$copyId]); $row=$this->find($newId); if($row){ $this->appendDeletedLog('CREATE',$newId,Snapshot::buildInsert('quest_template',$row)); } return ['success'=>true,'message'=>$this->repoMessage('copy_created'), 'new_id'=>$newId]; }
            return ['success'=>false,'message'=>$this->repoError('copy_failed')];
        }
        $sql='INSERT INTO quest_template(ID,LogTitle,LogDescription,QuestDescription,QuestLevel,MinLevel,QuestType) VALUES(:i,:t,:l,:qd,1,1,0)';
        $st=$this->world->prepare($sql); $ok=$st->execute([':i'=>$newId,':t'=>Lang::get('app.quest.repository.defaults.log_title',['id'=>$newId]),':l'=>'',':qd'=>'']);
        if($ok){ Audit::log('quest','create',(string)$newId,['blank'=>true]); $row=$this->find($newId); if($row){ $this->appendDeletedLog('CREATE',$newId,Snapshot::buildInsert('quest_template',$row)); } return ['success'=>true,'message'=>$this->repoMessage('created'), 'new_id'=>$newId]; }
        return ['success'=>false,'message'=>$this->repoError('create_failed')];
    }

    public function delete(int $id): array
    {
        if($id<=0) return ['success'=>false,'message'=>$this->repoError('invalid_id')];
        $row=$this->find($id);
        $st=$this->world->prepare('DELETE FROM quest_template WHERE ID=:id'); $st->execute([':id'=>$id]); $cnt=$st->rowCount(); Audit::log('quest','delete',(string)$id,['affected'=>$cnt]);
        if($row && $cnt){ $this->appendDeletedLog('DELETE',$id,Snapshot::buildInsert('quest_template',$row)); }
        return ['success'=>true,'message'=>$cnt?$this->repoMessage('delete_success',['id'=>$id]):$this->repoMessage('delete_none')];
    }

    public function updatePartial(int $id, array $changes): array
    {
        if($id<=0) return ['success'=>false,'message'=>$this->repoError('invalid_id')]; if(!$changes) return ['success'=>true,'message'=>$this->repoMessage('no_changes')];
        $valid=array_flip(self::validColumns());
        $wanted=[]; foreach($changes as $k=>$v){ if(isset($valid[$k]) && $k!=='ID') $wanted[$k]=true; }
        if(!$wanted) return ['success'=>true,'message'=>$this->repoMessage('no_valid_fields')];
        $cols=implode(',',array_map(fn($c)=>"`$c`", array_keys($wanted)));
        $old=[]; try{ $stOld=$this->world->prepare("SELECT $cols FROM quest_template WHERE ID=:id LIMIT 1"); $stOld->execute([':id'=>$id]); $old=$stOld->fetch(PDO::FETCH_ASSOC)?:[]; }catch(\Throwable $e){}
        $sets=[]; $params=[':id'=>$id]; $diff=[]; foreach($changes as $k=>$v){ if(!isset($valid[$k])||$k==='ID') continue; $ph=':c_'.preg_replace('/[^A-Za-z0-9_]/','',$k); $sets[]="`$k`=$ph"; $params[$ph]=($v===''||$v===null)?null:$v; $newVal=$params[$ph]; $oldVal=$old[$k]??null; if($oldVal!==$newVal){ $diff[$k]=['old'=>$oldVal,'new'=>$newVal]; } }
        if(!$sets) return ['success'=>true,'message'=>$this->repoMessage('no_valid_fields')]; if(!$diff) return ['success'=>true,'message'=>$this->repoMessage('no_values_changed')];
        $sql='UPDATE quest_template SET '.implode(',',$sets).' WHERE ID=:id'; $st=$this->world->prepare($sql); $ok=$st->execute($params);
        $trimmed=[]; $count=0; foreach($diff as $col=>$pair){ if($count>=40){ $trimmed['__more__']='truncated'; break; } $trimmed[$col]=['old'=>self::shortVal($pair['old']),'new'=>self::shortVal($pair['new'])]; $count++; }
        Audit::log('quest','update',(string)$id,['changed'=>$trimmed,'success'=>$ok,'server_id'=>$this->serverId]);
        return $ok?['success'=>true,'message'=>$this->repoMessage('update_done'),'changed'=>array_keys($diff)]:['success'=>false,'message'=>$this->repoError('update_failed')];
    }

    public function execLimitedSql(string $sql): array
    {
        $sql = trim($sql);
        if($sql === ''){
            return ['success' => false, 'message' => $this->repoError('sql_empty')];
        }
        if(preg_match('/;.+/s', $sql)){
            return ['success' => false, 'message' => $this->repoError('sql_multiple')];
        }
        $validMap=array_fill_keys(self::validColumns(),true); $pdo=$this->world; $type=''; $ok=false; $error=''; $affected=0; $norm=rtrim($sql,";\r\n\t ");
        try{
            if(preg_match('/^UPDATE\s+`?quest_template`?\s+SET\s+(.*?)\s+WHERE\s+/is',$norm,$m)){
                $type='UPDATE';
                $assigns=array_map('trim', explode(',', $m[1]));
                foreach($assigns as $as){
                    if(!preg_match('/^`?(\w+)`?\s*=/', $as,$cm)){
                        throw new \RuntimeException($this->repoError('sql_parse_column', ['column' => $as]));
                    }
                    $col=$cm[1];
                    if(!isset($validMap[$col])){
                        throw new \RuntimeException($this->repoError('sql_invalid_column', ['column' => $col]));
                    }
                }
                if(!preg_match('/WHERE\s+`?ID`?\s*=\s*(\d+)\s*(LIMIT\s+1)?\s*$/i',$norm)){
                    return ['success'=>false,'message'=>$this->repoError('sql_update_where')];
                }
                if(!preg_match('/LIMIT\s+1\s*$/i',$norm)){
                    $norm .= ' LIMIT 1';
                }
                $affected=$pdo->exec($norm); $ok=true;
            } elseif(preg_match('/^INSERT\s+INTO\s+`?quest_template`?\s*\((.*?)\)\s*VALUES\s*\((.*?)\)$/is',$norm,$m)){
                $type='INSERT';
                $cols=array_map('trim', explode(',', $m[1]));
                foreach($cols as $c){
                    $c=trim($c,'` ');
                    if(!isset($validMap[$c])){
                        throw new \RuntimeException($this->repoError('sql_invalid_column', ['column' => $c]));
                    }
                }
                $affected=$pdo->exec($norm); $ok=true;
            } else {
                return ['success'=>false,'message'=>$this->repoError('sql_only_update_insert')];
            }
        }catch(\Throwable $e){ $error=$e->getMessage(); }
    Audit::log('quest','exec_sql',$type?:'UNKNOWN',['sql'=>$norm,'success'=>$ok,'affected'=>$affected,'error'=>$error,'server_id'=>$this->serverId]);
        $this->appendSqlLog($type?:'UNKNOWN',$ok,$affected,$norm,$error);
        if(!$ok) return ['success'=>false,'message'=>$this->repoError('sql_exec_error', ['error'=>$error])];
        $after=null; if($type==='UPDATE' && preg_match('/WHERE\s+`?ID`?\s*=\s*(\d+)/i',$norm,$mm)){ $entry=(int)$mm[1]; $st=$pdo->prepare('SELECT * FROM quest_template WHERE ID=:e'); if($st->execute([':e'=>$entry])){ $r=$st->fetch(PDO::FETCH_ASSOC); if($r) $after=$r; } }
        $operationLabel = match($type){
            'INSERT' => Lang::get('app.quest.repository.sql.insert_label'),
            'UPDATE' => Lang::get('app.quest.repository.sql.update_label'),
            default => strtoupper($type ?: 'UNKNOWN'),
        };
        return ['success'=>true,'message'=>Lang::get('app.quest.repository.sql.affected',['operation'=>$operationLabel,'count'=>(int)$affected]),'type'=>$type,'affected'=>$affected,'after'=>$after];
    }

    private static function shortVal($v): string
    { if($v===null) return 'NULL'; $s=(string)$v; if(strlen($s)>120) $s=substr($s,0,117).'...'; return $s; }


    private function logsDir(): string
    { $dir=dirname(__DIR__,3).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs'; if(!is_dir($dir)) @mkdir($dir,0777,true); return $dir; }

    private function appendDeletedLog(string $action,int $id,string $sql): void
    { $file=$this->logsDir().DIRECTORY_SEPARATOR.'quest_deleted.log'; $user=$this->currentUser(); $line=sprintf('[%s]|%s|%s|%d|%s|%d',date('Y-m-d H:i:s'),$user,$action,$id,$sql,$this->serverId); @file_put_contents($file,$line.PHP_EOL,FILE_APPEND); }

    private function appendSqlLog(string $type,bool $ok,int $affected,string $sql,string $error): void
    { $file=$this->logsDir().DIRECTORY_SEPARATOR.'quest_sql.log'; $user=$this->currentUser(); $line=sprintf('[%s]|%s|%s|%s|%d|%s|%s|%d',date('Y-m-d H:i:s'),$user,$type,$ok?'OK':'FAIL',$affected,str_replace(["\r","\n"],' ',$sql),$ok?'':$error,$this->serverId); @file_put_contents($file,$line.PHP_EOL,FILE_APPEND); }

    private function currentUser(): string
    { return $_SESSION['admin_user'] ?? ($_SESSION['username'] ?? 'unknown'); }







    public function tailLog(string $type,int $limit=50): array
    {
        $limit = max(1,min(200,$limit));
    $map = [ 'sql'=>'quest_sql.log','deleted'=>'quest_deleted.log' ];
    if(!isset($map[$type])) return ['success'=>false,'message'=>$this->repoError('log_unknown_type')];
        $file = $this->logsDir().DIRECTORY_SEPARATOR.$map[$type];
        if(!is_file($file)) return ['success'=>true,'lines'=>[]];

        $size = filesize($file);
        $lines = [];
        if($size < 1024*1024){
            $raw = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $raw = array_slice($raw, -$limit);
            foreach($raw as $ln){ $lines[] = $this->parseLogLine($type,$ln); }
        } else {

            $fp = @fopen($file,'r'); if(!$fp) return ['success'=>false,'message'=>$this->repoError('log_open_failed')];
            $chunk=''; $pos = $size; $needed = $limit + 5;
            while($pos>0 && count($lines)<$needed){
                $read = min(8192,$pos); $pos -= $read; fseek($fp,$pos); $chunk = fread($fp,$read).$chunk; $parts = explode("\n", $chunk);

                if($pos>0){ $chunk = array_shift($parts); }
                else { $chunk=''; }

                $tmp=[]; foreach($parts as $p){ if($p==='') continue; $tmp[]=$p; }

                $tmp = array_reverse($tmp); foreach($tmp as $p){ $lines[]=$this->parseLogLine($type,$p); if(count($lines)>=$limit) break; }
            }
            fclose($fp);
            $lines = array_reverse(array_slice($lines,0,$limit));
        }
        return ['success'=>true,'lines'=>$lines];
    }

    private function parseLogLine(string $type,string $line): array
    {


        $parts = explode('|',$line);
        $ts=''; if(isset($parts[0]) && preg_match('/^\[(.*?)\]$/',$parts[0],$m)) $ts=$m[1];
        if($type==='sql'){
            return [
                'time'=>$ts,
                'user'=>$parts[1]??'',
                'op'=>$parts[2]??'',
                'status'=>$parts[3]??'',
                'affected'=>(int)($parts[4]??0),
                'sql'=>$parts[5]??'',
                'error'=>$parts[6]??'',
                'server'=>(int)($parts[7]??0)
            ];
        }
        return [
            'time'=>$ts,
            'user'=>$parts[1]??'',
            'action'=>$parts[2]??'',
            'id'=>(int)($parts[3]??0),
            'snapshot'=>$parts[4]??'',
            'server'=>(int)($parts[5]??0)
        ];
    }




    public function rowHash(array $row): string
    {
        $cols = self::validColumns();
        $parts=[]; foreach($cols as $c){ if(array_key_exists($c,$row)){ $v=$row[$c]; if($v===null) $v='NULL'; $parts[]=$c.'='.$v; } }
        return sha1(implode('|',$parts));
    }
}

