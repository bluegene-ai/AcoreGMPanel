<?php
/**
 * File: app/Domain/Quest/QuestAggregateService.php
 * Purpose: Defines class QuestAggregateService for the app/Domain/Quest module.
 * Classes:
 *   - QuestAggregateService
 * Functions:
 *   - load()
 *   - save()
 *   - preview()
 *   - updateTemplate()
 *   - upsertRow()
 *   - deleteRow()
 *   - saveNarrative()
 *   - syncObjectives()
 *   - saveRewards()
 *   - saveRelations()
 *   - saveLocales()
 *   - savePoi()
 *   - fetchRow()
 *   - fetchRows()
 *   - fetchRelation()
 *   - hashAggregate()
 *   - buildLookups()
 *   - resolveLocaleTable()
 *   - insertRow()
 *   - bindValue()
 *   - tableExists()
 *   - validatePayload()
 *   - getQuestConfig()
 *   - getFieldsConfig()
 *   - getMetaConfig()
 *   - buildTemplateSql()
 *   - buildAddonSql()
 *   - buildNarrativeSql()
 *   - buildObjectivesSql()
 *   - buildRewardsSql()
 *   - buildRelationsSql()
 *   - buildLocalesSql()
 *   - buildPoiSql()
 *   - logAggregateSave()
 *   - logAggregateFailure()
 *   - summarizeStats()
 *   - appendAggregateLog()
 *   - logsDir()
 *   - currentUser()
 *   - buildInsertStatement()
 *   - buildUpdateStatement()
 *   - buildDeleteStatement()
 *   - diffAssoc()
 *   - valuesEqual()
 *   - sqlValue()
 */

namespace Acme\Panel\Domain\Quest;

use Acme\Panel\Core\Lang;
use PDO;
use PDOException;
use Acme\Panel\Domain\Support\MultiServerRepository;
use Acme\Panel\Support\Audit;
use Acme\Panel\Support\ConfigLocalization;
use RuntimeException;








class QuestAggregateService extends MultiServerRepository
{
    private array $tableExistsCache = [];
    private static ?array $questConfigCache = null;
    private static ?array $fieldsConfigCache = null;
    private static ?array $metaConfigCache = null;






    public function load(int $questId): array
    {
        if($questId <= 0){
            return ['success' => false, 'message' => Lang::get('app.quest.aggregate.errors.invalid_id')];
        }
        $pdo = $this->world();
        $template = $this->fetchRow($pdo, 'quest_template', 'ID', $questId);
        if(!$template){
            return ['success' => false, 'message' => Lang::get('app.quest.messages.not_found')];
        }

        $quest = [
            'template'   => $template,
            'addon'      => $this->fetchRow($pdo, 'quest_template_addon', 'ID', $questId),
            'narrative'  => [
                'details' => $this->fetchRow($pdo, 'quest_details', 'ID', $questId),
                'request' => $this->fetchRow($pdo, 'quest_request_items', 'ID', $questId),
                'offer'   => $this->fetchRow($pdo, 'quest_offer_reward', 'ID', $questId),
            ],
            'objectives' => $this->fetchRows($pdo, 'quest_objectives', 'QuestID', $questId, 'ID'),
            'rewards'    => [
                'choice_items' => $this->fetchRows($pdo, 'quest_reward_choice_item', 'ID', $questId),
                'items'        => $this->fetchRows($pdo, 'quest_reward_item', 'ID', $questId),
                'currencies'   => $this->fetchRows($pdo, 'quest_reward_currency', 'ID', $questId),
                'factions'     => $this->fetchRows($pdo, 'quest_reward_faction', 'ID', $questId),
            ],
            'relations'  => [
                'starters' => [
                    'creatures'   => $this->fetchRelation($pdo, 'creature_queststarter', 'quest', $questId, 'id'),
                    'gameobjects' => $this->fetchRelation($pdo, 'gameobject_queststarter', 'quest', $questId, 'id'),
                ],
                'enders'   => [
                    'creatures'   => $this->fetchRelation($pdo, 'creature_questender', 'quest', $questId, 'id'),
                    'gameobjects' => $this->fetchRelation($pdo, 'gameobject_questender', 'quest', $questId, 'id'),
                ],
            ],
            'locales'    => [
                'template' => $this->fetchRows($pdo, 'quest_template_locale', 'ID', $questId),
                'details'  => $this->fetchRows($pdo, 'quest_details_locale', 'ID', $questId),
                'request'  => $this->fetchRows($pdo, 'quest_request_items_locale', 'ID', $questId),
                'offer'    => $this->fetchRows($pdo, 'quest_offer_reward_locale', 'ID', $questId),
                'objectives' => $this->fetchRows($pdo, 'quest_objectives_locale', 'ID', $questId),
            ],
            'poi' => [
                'headers' => $this->fetchRows($pdo, 'quest_poi', 'QuestID', $questId),
                'points'  => $this->fetchRows($pdo, 'quest_poi_points', 'QuestID', $questId),
            ],
        ];

        $hash = $this->hashAggregate($quest);

        return [
            'success' => true,
            'quest'   => $quest,
            'hash'    => $hash,
            'lookups' => $this->buildLookups(),
        ];
    }








    public function save(int $questId, array $payload, ?string $expectedHash=null): array
    {
        if($questId <= 0){
            return ['success' => false, 'message' => Lang::get('app.quest.aggregate.errors.invalid_id')];
        }
        $pdo = $this->world();
        $current = $this->load($questId);
        if(!$current['success']){
            return $current;
        }
        $currentHash = $current['hash'];
        if($expectedHash && $expectedHash !== $currentHash){
            return ['success' => false, 'message' => Lang::get('app.quest.aggregate.errors.hash_mismatch'), 'code' => 'hash_mismatch', 'hash' => $currentHash];
        }

        $hasAddonKey = array_key_exists('addon', $payload);
        $validation = $this->validatePayload($questId, $payload);
        if($validation){
            return ['success' => false, 'message' => Lang::get('app.quest.aggregate.errors.validation_failed'), 'code' => 'validation_failed', 'errors' => $validation];
        }
        $templateData = $payload['template'] ?? null;
        $addonData = $hasAddonKey ? $payload['addon'] : null;
        $narrativeData = $payload['narrative'] ?? null;
        $objectivesData = $payload['objectives'] ?? null;
        $rewardsData = $payload['rewards'] ?? null;
        $relationsData = $payload['relations'] ?? null;
        $localesData = $payload['locales'] ?? null;
        $poiData = $payload['poi'] ?? null;

        if(!$templateData && !$hasAddonKey && !$narrativeData && !$objectivesData && !$rewardsData && !$relationsData && !$localesData && !$poiData){
            return ['success' => false, 'message' => Lang::get('app.quest.aggregate.errors.no_changes_payload')];
        }

        $pdo->beginTransaction();
        try {
            $stats = [];
            if($templateData){
                $stats['template'] = $this->updateTemplate($pdo, $questId, $templateData);
            }
            if($hasAddonKey){
                if($addonData === null){
                    $stats['addon'] = $this->deleteRow($pdo, 'quest_template_addon', 'ID', $questId);
                } else {
                    $stats['addon'] = $this->upsertRow($pdo, 'quest_template_addon', 'ID', $questId, (array)$addonData);
                }
            }
            if(is_array($narrativeData)){
                $stats['narrative'] = $this->saveNarrative($pdo, $questId, $narrativeData);
            }
            if(is_array($objectivesData)){
                $stats['objectives'] = $this->syncObjectives($pdo, $questId, $objectivesData);
            }
            if(is_array($rewardsData)){
                $stats['rewards'] = $this->saveRewards($pdo, $questId, $rewardsData);
            }
            if(is_array($relationsData)){
                $stats['relations'] = $this->saveRelations($pdo, $questId, $relationsData);
            }
            if(is_array($localesData)){
                $stats['locales'] = $this->saveLocales($pdo, $questId, $localesData);
            }
            if(is_array($poiData)){
                $stats['poi'] = $this->savePoi($pdo, $questId, $poiData);
            }
            $pdo->commit();
            $this->logAggregateSave($questId, $stats, $payload);
        } catch(\Throwable $e){
            $pdo->rollBack();
            $this->logAggregateFailure($questId, $payload, $e->getMessage());
            return ['success' => false, 'message' => Lang::get('app.quest.aggregate.errors.save_failed', ['reason' => $e->getMessage()])];
        }

        $fresh = $this->load($questId);
        if(!$fresh['success']){

            throw new RuntimeException('Quest disappeared after save');
        }
        $fresh['stats'] = $stats ?? [];
        return $fresh;
    }




    public function preview(int $questId, array $payload): array
    {
        if($questId <= 0){
            return ['success' => false, 'message' => Lang::get('app.quest.aggregate.errors.invalid_id')];
        }
        $validation = $this->validatePayload($questId, $payload);
        if($validation){
            return ['success' => false, 'message' => Lang::get('app.quest.aggregate.errors.validation_failed'), 'code' => 'validation_failed', 'errors' => $validation];
        }
        $current = $this->load($questId);
        if(!$current['success']){
            return $current;
        }
        $currentQuest = $current['quest'] ?? [];
        $script = [];
        $warnings = [];

        if(isset($payload['template']) && is_array($payload['template'])){
            $stmt = $this->buildTemplateSql($questId, $currentQuest['template'] ?? [], $payload['template']);
            if($stmt){ $script[] = $stmt; }
        }
        if(array_key_exists('addon', $payload)){
            $addonStmts = $this->buildAddonSql($questId, $currentQuest['addon'] ?? null, $payload['addon']);
            $script = array_merge($script, $addonStmts);
        }
        if(isset($payload['narrative']) && is_array($payload['narrative'])){
            $script = array_merge($script, $this->buildNarrativeSql($questId, $currentQuest['narrative'] ?? [], $payload['narrative']));
        }
        if(isset($payload['objectives']) && is_array($payload['objectives'])){
            $script = array_merge($script, $this->buildObjectivesSql($questId, $currentQuest['objectives'] ?? [], $payload['objectives']));
        }
        if(isset($payload['rewards']) && is_array($payload['rewards'])){
            $script = array_merge($script, $this->buildRewardsSql($questId, $payload['rewards']));
        }
        if(isset($payload['relations']) && is_array($payload['relations'])){
            $script = array_merge($script, $this->buildRelationsSql($questId, $payload['relations']));
        }
        if(isset($payload['locales']) && is_array($payload['locales'])){
            $script = array_merge($script, $this->buildLocalesSql($questId, $payload['locales']));
        }
        if(isset($payload['poi']) && is_array($payload['poi'])){
            $script = array_merge($script, $this->buildPoiSql($questId, $payload['poi']));
        }

        if(!$script){
            $warnings[] = Lang::get('app.quest.aggregate.warnings.no_changes');
        }

        return [
            'success' => true,
            'hash' => $current['hash'],
            'sql' => $script,
            'warnings' => $warnings,
        ];
    }

    private function updateTemplate(PDO $pdo, int $questId, array $data): array
    {
        $validCols = array_flip(QuestRepository::validColumns());
        unset($data['ID']);
        $filtered = [];
        foreach($data as $col => $val){
            if(isset($validCols[$col])){
                $filtered[$col] = $val;
            }
        }
        if(!$filtered){
            return ['changed' => 0];
        }
        $sets = [];
        foreach($filtered as $col => $value){
            $sets[] = "`$col`=:".$col;
        }
        $sql = 'UPDATE `quest_template` SET '.implode(',', $sets).' WHERE `ID`=:id LIMIT 1';
        $stmt = $pdo->prepare($sql);
        foreach($filtered as $col => $value){
            $this->bindValue($stmt, ':'.$col, $value === '' ? null : $value);
        }
        $stmt->bindValue(':id', $questId, PDO::PARAM_INT);
        $stmt->execute();
        return ['changed' => $stmt->rowCount()];
    }

    private function upsertRow(PDO $pdo, string $table, string $pk, int $pkValue, array $data): array
    {
        $row = $this->fetchRow($pdo, $table, $pk, $pkValue);
        if(!$this->tableExists($pdo, $table)){
            return ['skipped' => true];
        }
        $data[$pk] = $pkValue;
        if($row){
            unset($data[$pk]);
            if(!$data){
                return ['changed' => 0];
            }
            $sets = [];
            foreach($data as $col => $value){
                $sets[] = "`$col`=:".$col;
            }
            $sql = "UPDATE `$table` SET ".implode(',', $sets)." WHERE `$pk`=:pk LIMIT 1";
            $stmt = $pdo->prepare($sql);
            foreach($data as $col => $value){
                $this->bindValue($stmt, ':'.$col, $value === '' ? null : $value);
            }
            $stmt->bindValue(':pk', $pkValue, PDO::PARAM_INT);
            $stmt->execute();
            return ['changed' => $stmt->rowCount(), 'status' => 'updated'];
        }
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':'.$c, $cols);
        $sql = 'INSERT INTO `'.$table.'`(`'.implode('`,`', $cols).'`) VALUES('.implode(',', $placeholders).')';
        $stmt = $pdo->prepare($sql);
        foreach($data as $col => $value){
            $this->bindValue($stmt, ':'.$col, $value === '' ? null : $value);
        }
        $stmt->execute();
        return ['changed' => 1, 'status' => 'inserted'];
    }

    private function deleteRow(PDO $pdo, string $table, string $pk, int $pkValue): array
    {
        if(!$this->tableExists($pdo, $table)){
            return ['skipped' => true];
        }
        $stmt = $pdo->prepare('DELETE FROM `'.$table.'` WHERE `'.$pk.'`=:id LIMIT 1');
        $stmt->bindValue(':id', $pkValue, PDO::PARAM_INT);
        $stmt->execute();
        return ['deleted' => $stmt->rowCount()];
    }

    private function saveNarrative(PDO $pdo, int $questId, array $narrative): array
    {
        $map = [
            'details' => 'quest_details',
            'request' => 'quest_request_items',
            'offer'   => 'quest_offer_reward',
        ];
        $stats = [];
        foreach($map as $key => $table){
            if(!array_key_exists($key, $narrative)) continue;
            if(!$this->tableExists($pdo, $table)){
                $stats[$key] = ['skipped' => true];
                continue;
            }
            $payload = $narrative[$key];
            if($payload === null){
                $stats[$key] = $this->deleteRow($pdo, $table, 'ID', $questId);
            } else {
                $stats[$key] = $this->upsertRow($pdo, $table, 'ID', $questId, (array)$payload);
            }
        }
        return $stats;
    }

    private function syncObjectives(PDO $pdo, int $questId, array $rows): array
    {
        if(!$this->tableExists($pdo, 'quest_objectives')){
            return ['skipped' => true];
        }
        $existing = $this->fetchRows($pdo, 'quest_objectives', 'QuestID', $questId, 'ID');
        $existingById = [];
        foreach($existing as $row){
            $id = isset($row['ID']) ? (int)$row['ID'] : 0;
            if($id > 0){
                $existingById[$id] = $row;
            }
        }
        $kept = [];
        $created = 0; $updated = 0; $deleted = 0;

        foreach($rows as $entry){
            if(!is_array($entry)) continue;
            $entry['QuestID'] = $questId;
            $rowId = isset($entry['ID']) && (int)$entry['ID'] > 0 ? (int)$entry['ID'] : 0;
            if($rowId > 0 && isset($existingById[$rowId])){
                $payload = $entry;
                unset($payload['ID']);
                $result = $this->upsertRow($pdo, 'quest_objectives', 'ID', $rowId, $payload);
                if(!empty($result['changed'])){
                    $updated += (int)$result['changed'];
                }
                $kept[] = $rowId;
            } else {
                $data = $entry;
                if(isset($data['ID'])){
                    if((int)$data['ID'] <= 0){
                        unset($data['ID']);
                    }
                }
                $this->insertRow($pdo, 'quest_objectives', $data);
                $created++;
            }
        }

        foreach($existingById as $id => $_row){
            if(!in_array($id, $kept, true)){
                $stmt = $pdo->prepare('DELETE FROM `quest_objectives` WHERE `ID`=:id LIMIT 1');
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $deleted += $stmt->rowCount();
            }
        }

        return ['created' => $created, 'updated' => $updated, 'deleted' => $deleted];
    }

    private function saveRewards(PDO $pdo, int $questId, array $rewards): array
    {
        $map = [
            'choice_items' => 'quest_reward_choice_item',
            'items'        => 'quest_reward_item',
            'currencies'   => 'quest_reward_currency',
            'factions'     => 'quest_reward_faction',
        ];
        $stats = [];
        foreach($map as $key => $table){
            if(!array_key_exists($key, $rewards)) continue;
            if(!$this->tableExists($pdo, $table)){
                $stats[$key] = ['skipped' => true];
                continue;
            }
            $rows = is_array($rewards[$key]) ? $rewards[$key] : [];
            $del = $pdo->prepare('DELETE FROM `'.$table.'` WHERE `ID`=:id');
            $del->bindValue(':id', $questId, PDO::PARAM_INT);
            $del->execute();
            $inserted = 0;
            foreach($rows as $row){
                if(!is_array($row)) continue;
                $row['ID'] = $questId;
                $this->insertRow($pdo, $table, $row);
                $inserted++;
            }
            $stats[$key] = ['inserted' => $inserted];
        }
        return $stats;
    }

    private function saveRelations(PDO $pdo, int $questId, array $relations): array
    {
        $stats = [];
        $map = [
            'starters' => [
                'creatures' => ['table' => 'creature_queststarter', 'entity' => 'id', 'quest' => 'quest'],
                'gameobjects' => ['table' => 'gameobject_queststarter', 'entity' => 'id', 'quest' => 'quest'],
            ],
            'enders' => [
                'creatures' => ['table' => 'creature_questender', 'entity' => 'id', 'quest' => 'quest'],
                'gameobjects' => ['table' => 'gameobject_questender', 'entity' => 'id', 'quest' => 'quest'],
            ],
        ];
        foreach($map as $role => $types){
            if(!isset($relations[$role]) || !is_array($relations[$role])) continue;
            foreach($types as $type => $cfg){
                $values = $relations[$role][$type] ?? null;
                if($values === null) continue;
                if(!$this->tableExists($pdo, $cfg['table'])){
                    $stats[$role][$type] = ['skipped' => true];
                    continue;
                }
                $del = $pdo->prepare('DELETE FROM `'.$cfg['table'].'` WHERE `'.$cfg['quest'].'`=:quest');
                $del->bindValue(':quest', $questId, PDO::PARAM_INT);
                $del->execute();
                $insert = $pdo->prepare('INSERT INTO `'.$cfg['table'].'`(`'.$cfg['entity'].'`,`'.$cfg['quest'].'`) VALUES(:entity,:quest)');
                $count = 0;
                if(is_array($values)){
                    foreach($values as $value){
                        $insert->bindValue(':entity', (int)$value, PDO::PARAM_INT);
                        $insert->bindValue(':quest', $questId, PDO::PARAM_INT);
                        $insert->execute();
                        $count++;
                    }
                }
                $stats[$role][$type] = ['inserted' => $count];
            }
        }
        return $stats;
    }

    private function saveLocales(PDO $pdo, int $questId, array $locales): array
    {
        $stats = [];
        foreach($locales as $key => $rows){
            $table = $this->resolveLocaleTable($key);
            if(!$table || !$this->tableExists($pdo, $table)){
                $stats[$key] = ['skipped' => true];
                continue;
            }
            $del = $pdo->prepare('DELETE FROM `'.$table.'` WHERE `ID`=:id');
            $del->bindValue(':id', $questId, PDO::PARAM_INT);
            $del->execute();
            $inserted = 0;
            if(is_array($rows)){
                foreach($rows as $row){
                    if(!is_array($row)) continue;
                    $row['ID'] = $questId;
                    $this->insertRow($pdo, $table, $row);
                    $inserted++;
                }
            }
            $stats[$key] = ['inserted' => $inserted];
        }
        return $stats;
    }

    private function savePoi(PDO $pdo, int $questId, array $poi): array
    {
        $stats = [];
        $headers = $poi['headers'] ?? [];
        $points = $poi['points'] ?? [];
        if($this->tableExists($pdo, 'quest_poi')){
            $del = $pdo->prepare('DELETE FROM `quest_poi` WHERE `QuestID`=:id');
            $del->bindValue(':id', $questId, PDO::PARAM_INT);
            $del->execute();
            $inserted = 0;
            foreach(is_array($headers)?$headers:[] as $row){
                if(!is_array($row)) continue;
                $row['QuestID'] = $questId;
                $this->insertRow($pdo, 'quest_poi', $row);
                $inserted++;
            }
            $stats['headers'] = ['inserted' => $inserted];
        } else {
            $stats['headers'] = ['skipped' => true];
        }
        if($this->tableExists($pdo, 'quest_poi_points')){
            $del = $pdo->prepare('DELETE FROM `quest_poi_points` WHERE `QuestID`=:id');
            $del->bindValue(':id', $questId, PDO::PARAM_INT);
            $del->execute();
            $inserted = 0;
            foreach(is_array($points)?$points:[] as $row){
                if(!is_array($row)) continue;
                $row['QuestID'] = $questId;
                $this->insertRow($pdo, 'quest_poi_points', $row);
                $inserted++;
            }
            $stats['points'] = ['inserted' => $inserted];
        } else {
            $stats['points'] = ['skipped' => true];
        }
        return $stats;
    }

    private function fetchRow(PDO $pdo, string $table, string $pk, int $id): ?array
    {
        if(!$this->tableExists($pdo, $table)){
            return null;
        }
        $stmt = $pdo->prepare('SELECT * FROM `'.$table.'` WHERE `'.$pk.'`=:id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    private function fetchRows(PDO $pdo, string $table, string $fk, int $id, ?string $orderBy=null): array
    {
        if(!$this->tableExists($pdo, $table)){
            return [];
        }
        $sql = 'SELECT * FROM `'.$table.'` WHERE `'.$fk.'`=:id';
        if($orderBy){
            $sql .= ' ORDER BY `'.$orderBy.'`';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function fetchRelation(PDO $pdo, string $table, string $fk, int $id, string $col): array
    {
        if(!$this->tableExists($pdo, $table)){
            return [];
        }
        $stmt = $pdo->prepare('SELECT `'.$col.'` FROM `'.$table.'` WHERE `'.$fk.'`=:id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn($row) => $row[$col], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function hashAggregate(array $aggregate): string
    {
        return sha1(json_encode($aggregate, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function buildLookups(): array
    {
        $base = dirname(__DIR__, 3);
        $fieldsCfg = $this->getFieldsConfig();
        $metaCfg = $this->getMetaConfig();
        return [
            'enums'           => $fieldsCfg['enums'] ?? [],
            'bitmasks'        => $fieldsCfg['bitmasks'] ?? [],
            'template_groups' => $metaCfg['template_groups'] ?? [],
            'addon_fields'    => $metaCfg['addon_fields'] ?? [],
            'narrative_tables'=> $metaCfg['narrative_tables'] ?? [],
            'objective_schema'=> $metaCfg['objective_schema'] ?? [],
            'reward_tables'   => $metaCfg['reward_tables'] ?? [],
            'relation_sets'   => $metaCfg['relation_sets'] ?? [],
            'locale_tables'   => $metaCfg['locale_tables'] ?? [],
            'poi_tables'      => $metaCfg['poi_tables'] ?? [],
        ];
    }

    private function resolveLocaleTable(string $key): ?string
    {
        static $cache = null;
        if($cache === null){
            $meta = $this->getMetaConfig();
            $cache = $meta['locale_tables'] ?? [];
        }
        return $cache[$key]['table'] ?? null;
    }

    private function insertRow(PDO $pdo, string $table, array $data): void
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':'.$c, $cols);
        $sql = 'INSERT INTO `'.$table.'`(`'.implode('`,`', $cols).'`) VALUES('.implode(',', $placeholders).')';
        $stmt = $pdo->prepare($sql);
        foreach($data as $col => $value){
            $this->bindValue($stmt, ':'.$col, $value === '' ? null : $value);
        }
        $stmt->execute();
    }

    private function bindValue(\PDOStatement $stmt, string $param, $value): void
    {
        if($value === null){
            $stmt->bindValue($param, null, PDO::PARAM_NULL);
        } elseif(is_int($value)){
            $stmt->bindValue($param, $value, PDO::PARAM_INT);
        } elseif(is_bool($value)){
            $stmt->bindValue($param, $value ? 1 : 0, PDO::PARAM_INT);
        } elseif(is_numeric($value) && ((string)(int)$value === (string)$value)){
            $stmt->bindValue($param, (int)$value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($param, $value);
        }
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        if(array_key_exists($table, $this->tableExistsCache)){
            return $this->tableExistsCache[$table];
        }
        try {
            $pdo->query('SELECT 1 FROM `'.$table.'` LIMIT 1');
            return $this->tableExistsCache[$table] = true;
        } catch(PDOException $e){
            return $this->tableExistsCache[$table] = false;
        }
    }

    private function validatePayload(int $questId, array $payload): array
    {
        $errors = [];
        if(isset($payload['template']) && is_array($payload['template'])){
            $template = $payload['template'];
            if(isset($template['ID']) && (int)$template['ID'] !== $questId){
                $errors[] = ['path' => 'template.ID', 'code' => 'id_mismatch', 'message' => Lang::get('app.quest.aggregate.errors.template_id_mismatch')];
            }
            $required = ['LogTitle','QuestDescription'];
            foreach($required as $field){
                if(array_key_exists($field, $template) && trim((string)$template[$field]) === ''){
                    $errors[] = ['path' => 'template.'.$field, 'code' => 'required', 'message' => Lang::get('app.quest.aggregate.errors.field_required', ['field' => $field])];
                }
            }
            foreach(['QuestLevel','MinLevel'] as $numeric){
                if(isset($template[$numeric]) && !is_numeric($template[$numeric])){
                    $errors[] = ['path' => 'template.'.$numeric, 'code' => 'numeric', 'message' => Lang::get('app.quest.aggregate.errors.field_numeric', ['field' => $numeric])];
                }
            }
        }
        if(isset($payload['objectives']) && is_array($payload['objectives'])){
            $indexes = [];
            foreach($payload['objectives'] as $i => $row){
                if(!is_array($row)) continue;
                $index = isset($row['Index']) ? (int)$row['Index'] : null;
                if($index === null){
                    $errors[] = ['path' => 'objectives['.$i.'].Index', 'code' => 'required', 'message' => Lang::get('app.quest.aggregate.errors.index_missing')];
                } else {
                    if($index < 0 || $index > 3){
                        $errors[] = ['path' => 'objectives['.$i.'].Index', 'code' => 'range', 'message' => Lang::get('app.quest.aggregate.errors.index_range', ['min' => 0, 'max' => 3])];
                    }
                    if(isset($indexes[$index])){
                        $errors[] = ['path' => 'objectives['.$i.'].Index', 'code' => 'duplicate', 'message' => Lang::get('app.quest.aggregate.errors.index_duplicate', ['value' => $index])];
                    }
                    $indexes[$index] = true;
                }
                if(isset($row['Type']) && !is_numeric($row['Type'])){
                    $errors[] = ['path' => 'objectives['.$i.'].Type', 'code' => 'numeric', 'message' => Lang::get('app.quest.aggregate.errors.field_numeric', ['field' => 'Type'])];
                }
                if(isset($row['Amount']) && !is_numeric($row['Amount'])){
                    $errors[] = ['path' => 'objectives['.$i.'].Amount', 'code' => 'numeric', 'message' => Lang::get('app.quest.aggregate.errors.field_numeric', ['field' => 'Amount'])];
                }
            }
        }
        if(isset($payload['rewards']['choice_items']) && is_array($payload['rewards']['choice_items'])){
            $count = count($payload['rewards']['choice_items']);
            if($count > 6){
                $errors[] = ['path' => 'rewards.choice_items', 'code' => 'limit_exceeded', 'message' => Lang::get('app.quest.aggregate.errors.choice_limit', ['limit' => 6])];
            }
            foreach($payload['rewards']['choice_items'] as $i => $row){
                if(!is_array($row)) continue;
                if(empty($row['ItemID']) || !is_numeric($row['ItemID'])){
                    $errors[] = ['path' => 'rewards.choice_items['.$i.'].ItemID', 'code' => 'numeric', 'message' => Lang::get('app.quest.aggregate.errors.field_numeric', ['field' => 'ItemID'])];
                }
            }
        }
        if(isset($payload['rewards']['items']) && is_array($payload['rewards']['items'])){
            foreach($payload['rewards']['items'] as $i => $row){
                if(!is_array($row)) continue;
                if(empty($row['ItemID']) || !is_numeric($row['ItemID'])){
                    $errors[] = ['path' => 'rewards.items['.$i.'].ItemID', 'code' => 'numeric', 'message' => Lang::get('app.quest.aggregate.errors.field_numeric', ['field' => 'ItemID'])];
                }
            }
        }
        if(isset($payload['relations']) && is_array($payload['relations'])){
            foreach(['starters','enders'] as $role){
                if(!isset($payload['relations'][$role]) || !is_array($payload['relations'][$role])) continue;
                foreach($payload['relations'][$role] as $type => $ids){
                    if($ids === null) continue;
                    if(!is_array($ids)){
                        $errors[] = ['path' => 'relations.'.$role.'.'.$type, 'code' => 'type', 'message' => Lang::get('app.quest.aggregate.errors.array_required')];
                        continue;
                    }
                    $seen = [];
                    foreach($ids as $idx => $id){
                        if(!is_numeric($id) || (int)$id <= 0){
                            $errors[] = ['path' => 'relations.'.$role.'.'.$type.'['.$idx.']', 'code' => 'numeric', 'message' => Lang::get('app.quest.aggregate.errors.positive_integer')];
                        }
                        if(isset($seen[$id])){
                            $errors[] = ['path' => 'relations.'.$role.'.'.$type.'['.$idx.']', 'code' => 'duplicate', 'message' => Lang::get('app.quest.aggregate.errors.duplicate_id', ['id' => $id])];
                        }
                        $seen[$id] = true;
                    }
                }
            }
        }
        if(isset($payload['poi']) && is_array($payload['poi'])){
            foreach(['headers','points'] as $key){
                if(!isset($payload['poi'][$key])) continue;
                if($payload['poi'][$key] !== null && !is_array($payload['poi'][$key])){
                    $errors[] = ['path' => 'poi.'.$key, 'code' => 'type', 'message' => Lang::get('app.quest.aggregate.errors.array_required')];
                }
            }
        }
        return $errors;
    }

    private function getQuestConfig(): array
    {
        if(self::$questConfigCache !== null){
            return self::$questConfigCache;
        }
        $file = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'quest.php';
        $config = is_file($file) ? include $file : [];
        if(!is_array($config)){
            $config = [];
        }
        $config = ConfigLocalization::localizeArray($config);
        self::$questConfigCache = $config;
        return self::$questConfigCache;
    }

    private function getFieldsConfig(): array
    {
        if(self::$fieldsConfigCache !== null){
            return self::$fieldsConfigCache;
        }
        $config = $this->getQuestConfig();
        self::$fieldsConfigCache = $config['fields'] ?? [];
        return self::$fieldsConfigCache;
    }

    private function getMetaConfig(): array
    {
        if(self::$metaConfigCache !== null){
            return self::$metaConfigCache;
        }
        $config = $this->getQuestConfig();
        self::$metaConfigCache = $config['metadata'] ?? [];
        return self::$metaConfigCache;
    }

    private function buildTemplateSql(int $questId, array $current, array $incoming): ?string
    {
        $validCols = array_flip(QuestRepository::validColumns());
        $changes = [];
        foreach($incoming as $col => $value){
            if($col === 'ID' || !isset($validCols[$col])) continue;
            $curr = $current[$col] ?? null;
            if(!$this->valuesEqual($curr, $value)){
                $changes[$col] = $value;
            }
        }
        if(!$changes){
            return null;
        }
        $sets = [];
        foreach($changes as $col => $value){
            $sets[] = '`'.$col.'`='.$this->sqlValue($value === '' ? null : $value);
        }
        return 'UPDATE `quest_template` SET '.implode(', ', $sets).' WHERE `ID`='.$questId.' LIMIT 1;';
    }

    private function buildAddonSql(int $questId, ?array $current, $incoming): array
    {
        $stmts = [];
        $table = 'quest_template_addon';
        if($incoming === null){
            if($current){
                $stmts[] = $this->buildDeleteStatement($table, ['ID' => $questId], true);
            }
            return $stmts;
        }
        $data = (array)$incoming;
        $data['ID'] = $questId;
        if($current){
            $changes = $this->diffAssoc($current, $data, ['ID']);
            if($changes){
                $stmts[] = $this->buildUpdateStatement($table, $changes, ['ID' => $questId]);
            }
        } else {
            $stmts[] = $this->buildInsertStatement($table, $data);
        }
        return $stmts;
    }

    private function buildNarrativeSql(int $questId, array $current, array $incoming): array
    {
        $map = [
            'details' => 'quest_details',
            'request' => 'quest_request_items',
            'offer'   => 'quest_offer_reward',
        ];
        $stmts = [];
        foreach($map as $key => $table){
            if(!array_key_exists($key, $incoming)) continue;
            if(!$this->tableExists($this->world(), $table)) continue;
            $currRow = $current[$key] ?? null;
            $value = $incoming[$key];
            if($value === null){
                if($currRow){
                    $stmts[] = $this->buildDeleteStatement($table, ['ID' => $questId], true);
                }
                continue;
            }
            $row = (array)$value;
            $row['ID'] = $questId;
            if($currRow){
                $changes = $this->diffAssoc($currRow, $row, ['ID']);
                if($changes){
                    $stmts[] = $this->buildUpdateStatement($table, $changes, ['ID' => $questId]);
                }
            } else {
                $stmts[] = $this->buildInsertStatement($table, $row);
            }
        }
        return $stmts;
    }

    private function buildObjectivesSql(int $questId, array $currentList, array $incomingList): array
    {
        if(!$this->tableExists($this->world(), 'quest_objectives')){
            return [];
        }
        $stmts = [];
        $existing = [];
        foreach($currentList as $row){
            $id = isset($row['ID']) ? (int)$row['ID'] : 0;
            if($id > 0){
                $existing[$id] = $row;
            }
        }
        $seen = [];
        foreach($incomingList as $row){
            if(!is_array($row)) continue;
            $rowId = isset($row['ID']) ? (int)$row['ID'] : 0;
            $row['QuestID'] = $questId;
            if($rowId > 0 && isset($existing[$rowId])){
                $changes = $this->diffAssoc($existing[$rowId], $row, ['ID','QuestID']);
                if($changes){
                    $stmts[] = $this->buildUpdateStatement('quest_objectives', $changes, ['ID' => $rowId]);
                }
                $seen[$rowId] = true;
            } else {
                if($rowId <= 0){ unset($row['ID']); }
                $stmts[] = $this->buildInsertStatement('quest_objectives', $row);
            }
        }
        foreach($existing as $id => $_row){
            if(!isset($seen[$id])){
                $stmts[] = $this->buildDeleteStatement('quest_objectives', ['ID' => $id], true);
            }
        }
        return $stmts;
    }

    private function buildRewardsSql(int $questId, array $rewards): array
    {
        $map = [
            'choice_items' => 'quest_reward_choice_item',
            'items'        => 'quest_reward_item',
            'currencies'   => 'quest_reward_currency',
            'factions'     => 'quest_reward_faction',
        ];
        $stmts = [];
        foreach($map as $key => $table){
            if(!array_key_exists($key, $rewards)) continue;
            if(!$this->tableExists($this->world(), $table)) continue;
            $rows = is_array($rewards[$key]) ? $rewards[$key] : [];
            $stmts[] = $this->buildDeleteStatement($table, ['ID' => $questId], false);
            foreach($rows as $row){
                if(!is_array($row)) continue;
                $row['ID'] = $questId;
                $stmts[] = $this->buildInsertStatement($table, $row);
            }
        }
        return $stmts;
    }

    private function buildRelationsSql(int $questId, array $relations): array
    {
        $map = [
            'starters' => [
                'creatures' => ['table' => 'creature_queststarter', 'entity' => 'id', 'quest' => 'quest'],
                'gameobjects' => ['table' => 'gameobject_queststarter', 'entity' => 'id', 'quest' => 'quest'],
            ],
            'enders' => [
                'creatures' => ['table' => 'creature_questender', 'entity' => 'id', 'quest' => 'quest'],
                'gameobjects' => ['table' => 'gameobject_questender', 'entity' => 'id', 'quest' => 'quest'],
            ],
        ];
        $stmts = [];
        foreach($map as $role => $types){
            if(!isset($relations[$role]) || !is_array($relations[$role])) continue;
            foreach($types as $type => $cfg){
                if(!array_key_exists($type, $relations[$role])) continue;
                if(!$this->tableExists($this->world(), $cfg['table'])) continue;
                $ids = $relations[$role][$type];
                $stmts[] = 'DELETE FROM `'.$cfg['table'].'` WHERE `'.$cfg['quest'].'`='.$questId.';';
                if(is_array($ids)){
                    foreach($ids as $id){
                        if(!is_numeric($id)) continue;
                        $stmts[] = 'INSERT INTO `'.$cfg['table'].'`(`'.$cfg['entity'].'`,`'.$cfg['quest'].'`) VALUES('.(int)$id.', '.$questId.');';
                    }
                }
            }
        }
        return $stmts;
    }

    private function buildLocalesSql(int $questId, array $locales): array
    {
        $stmts = [];
        foreach($locales as $key => $rows){
            $table = $this->resolveLocaleTable($key);
            if(!$table || !$this->tableExists($this->world(), $table)) continue;
            $stmts[] = $this->buildDeleteStatement($table, ['ID' => $questId], false);
            if(is_array($rows)){
                foreach($rows as $row){
                    if(!is_array($row)) continue;
                    $row['ID'] = $questId;
                    $stmts[] = $this->buildInsertStatement($table, $row);
                }
            }
        }
        return $stmts;
    }

    private function buildPoiSql(int $questId, array $poi): array
    {
        $stmts = [];
        if(array_key_exists('headers', $poi) && $this->tableExists($this->world(), 'quest_poi')){
            $stmts[] = $this->buildDeleteStatement('quest_poi', ['QuestID' => $questId], false);
            foreach(is_array($poi['headers']) ? $poi['headers'] : [] as $row){
                if(!is_array($row)) continue;
                $row['QuestID'] = $questId;
                $stmts[] = $this->buildInsertStatement('quest_poi', $row);
            }
        }
        if(array_key_exists('points', $poi) && $this->tableExists($this->world(), 'quest_poi_points')){
            $stmts[] = $this->buildDeleteStatement('quest_poi_points', ['QuestID' => $questId], false);
            foreach(is_array($poi['points']) ? $poi['points'] : [] as $row){
                if(!is_array($row)) continue;
                $row['QuestID'] = $questId;
                $stmts[] = $this->buildInsertStatement('quest_poi_points', $row);
            }
        }
        return $stmts;
    }

    private function logAggregateSave(int $questId, array $stats, array $payload): void
    {
        Audit::log('quest', 'aggregate_save', (string)$questId, [
            'stats' => $stats,
            'server_id' => $this->serverId,
        ]);
        $summary = $this->summarizeStats($stats);
        $this->appendAggregateLog('AGG_SAVE', true, $summary, '');
    }

    private function logAggregateFailure(int $questId, array $payload, string $error): void
    {
        Audit::log('quest', 'aggregate_save_fail', (string)$questId, [
            'error' => $error,
            'server_id' => $this->serverId,
        ]);
        $this->appendAggregateLog('AGG_SAVE', false, 'error', $error);
    }

    private function summarizeStats(array $stats): string
    {
        $parts = [];
        foreach($stats as $section => $info){
            if(!is_array($info)) continue;
            $sub = [];
            foreach($info as $k => $v){
                if(is_array($v)){
                    $sub[] = $k.'='.json_encode($v, JSON_UNESCAPED_UNICODE);
                } else {
                    $sub[] = $k.'='.$v;
                }
            }
            $parts[] = $section.':'.implode(',', $sub);
        }
        return implode('|', $parts);
    }

    private function appendAggregateLog(string $type, bool $ok, string $summary, string $error): void
    {
        $file = $this->logsDir().DIRECTORY_SEPARATOR.'quest_sql.log';
        $user = $this->currentUser();
        $line = sprintf('[%s]|%s|%s|%s|%d|%s|%s|%d',
            date('Y-m-d H:i:s'),
            $user,
            $type,
            $ok ? 'OK' : 'FAIL',
            0,
            substr($summary, 0, 4000),
            $ok ? '' : $error,
            $this->serverId
        );
        \Acme\Panel\Support\LogPath::appendTo($file, $line, true, 0777);
    }

    private function logsDir(): string
    {
        $dir = \Acme\Panel\Support\LogPath::logsDir(true, 0777);
        if(!is_dir($dir)){
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private function currentUser(): string
    {
        return $_SESSION['admin_user'] ?? ($_SESSION['username'] ?? 'unknown');
    }

    private function buildInsertStatement(string $table, array $data): string
    {
        $cols = array_keys($data);
        $values = array_map(fn($col) => $this->sqlValue($data[$col]), $cols);
        return 'INSERT INTO `'.$table.'`(`'.implode('`,`', $cols).'`) VALUES('.implode(', ', $values).');';
    }

    private function buildUpdateStatement(string $table, array $changes, array $conditions): string
    {
        $sets = [];
        foreach($changes as $col => $value){
            $sets[] = '`'.$col.'`='.$this->sqlValue($value);
        }
        $where = [];
        foreach($conditions as $col => $value){
            $where[] = '`'.$col.'`='.$this->sqlValue($value);
        }
        return 'UPDATE `'.$table.'` SET '.implode(', ', $sets).' WHERE '.implode(' AND ', $where).' LIMIT 1;';
    }

    private function buildDeleteStatement(string $table, array $conditions, bool $limit): string
    {
        $where = [];
        foreach($conditions as $col => $value){
            $where[] = '`'.$col.'`='.$this->sqlValue($value);
        }
        return 'DELETE FROM `'.$table.'` WHERE '.implode(' AND ', $where).($limit ? ' LIMIT 1' : '').';';
    }

    private function diffAssoc(array $current, array $incoming, array $ignore = []): array
    {
        $ignoreFlip = array_flip($ignore);
        $changes = [];
        foreach($incoming as $col => $value){
            if(isset($ignoreFlip[$col])) continue;
            $curr = $current[$col] ?? null;
            if(!$this->valuesEqual($curr, $value)){
                $changes[$col] = $value;
            }
        }
        return $changes;
    }

    private function valuesEqual($a, $b): bool
    {
        if($a === $b) return true;
        if($a === null || $b === null) return false;
        if(is_numeric($a) && is_numeric($b)){
            return (string)(+$a) === (string)(+$b);
        }
        return (string)$a === (string)$b;
    }

    private function sqlValue($value): string
    {
        if($value === null || $value === ''){
            return 'NULL';
        }
        if(is_bool($value)){
            return $value ? '1' : '0';
        }
        if(is_numeric($value) && preg_match('/^-?\d+(\.\d+)?$/', (string)$value)){
            return (string)(+$value);
        }
        $str = (string)$value;
        $str = str_replace(['\\', "'"], ['\\\\', "''"], $str);
        return "'".$str."'";
    }
}

