<?php
/**
 * File: app/Domain/MassMail/MassMailService.php
 * Purpose: Defines class MassMailService for the app/Domain/MassMail module.
 * Classes:
 *   - MassMailService
 * Functions:
 *   - __construct()
 *   - resolveTargets()
 *   - previewTargets()
 *   - sendAnnounce()
 *   - sendBulk()
 *   - recentLogs()
 *   - getOnline()
 *   - parseCustom()
 *   - ensureLogTable()
 *   - logAnnounce()
 *   - logBulk()
 *   - resolveItemNames()
 *   - resolveItemName()
 *   - loadItemCache()
 *   - persistItemCache()
 *   - appendActionLog()
 *   - migrateAddServerIdColumn()
 *   - validateItemExists()
 */

namespace Acme\Panel\Domain\MassMail;

use Acme\Panel\Core\Database; use Acme\Panel\Core\Lang; use Acme\Panel\Support\Audit; use Acme\Panel\Support\GameNameResolver; use Acme\Panel\Support\ServerContext; use Acme\Panel\Support\SoapExecutor; use PDO; use SoapFault; use Throwable;









class MassMailService
{
    private PDO $chars; private int $serverId;
    private ?PDO $world = null;
    private array $soapConf;
    private SoapExecutor $soapExec;
    private string $logTable = 'panel_massmail_log';
    private string $actionLogFile;
    private string $itemCacheFile;
    private array $itemNameCache = [];
    private int $targetMax = 2000;
    private int $batchSize = 200;


    public function __construct(array $soapConf, ?int $serverId=null)
    {
    $this->serverId = $serverId ?? ServerContext::currentId();
    $this->chars = Database::forServer($this->serverId,'characters');
    try { $this->world = Database::forServer($this->serverId,'world'); } catch(\Throwable $e){ $this->world = null; }
        $this->chars->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->soapConf = $soapConf;
    $this->soapExec = new SoapExecutor();
        $baseStorage = dirname(__DIR__,3).DIRECTORY_SEPARATOR.'storage';
        $this->actionLogFile = $baseStorage.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'massmail_actions.log';
        $this->itemCacheFile  = $baseStorage.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'massmail_item_names.json';
        $this->loadItemCache();
        $this->ensureLogTable();
        $this->migrateAddServerIdColumn();
        $this->migrateAddItemsColumn();
    }


    public function resolveTargets(string $type, ?string $customList): array
    {
        if($type==='online') return $this->getOnline();
        if($type==='custom') return $this->parseCustom($customList??'');
        return [];
    }

    /**
     * 目标预览：给界面用的计数 + 少量样本，避免把几千个名字全丢回浏览器。
     *
     * @return array{type:string,count:int,limit:int,truncated:bool,sample:string[]}
     */
    public function previewTargets(string $type, ?string $customList, int $sampleSize = 20): array
    {
        $targets = $this->resolveTargets($type, $customList);
        $count = count($targets);
        $sampleSize = max(1, min($sampleSize, 50));

        return [
            'type' => $type,
            'count' => $count,
            'limit' => $this->targetMax,
            'truncated' => $count > $this->targetMax,
            'sample' => array_slice($targets, 0, $sampleSize),
        ];
    }

    public function sendAnnounce(string $message): array
    {
        $message = trim($message); if($message==='') return ['success'=>false,'message'=>__('app.mass_mail.service.announce.message_required')];
        $errors=[]; $sent=[];
        foreach(['announce'=>'.announce','notify'=>'.notify'] as $label=>$cmd){
            $res = $this->soapExec->execute($cmd.' '.$message,[ 'server_id'=>$this->serverId, 'audit'=>true ]);
            if($res['success']) $sent[]=$label; else $errors[]=$label.':'.($res['message'] ?? $res['code'] ?? 'fail');
        }
        $ok = empty($errors);
    $this->logAnnounce($message,$ok,$errors);
    Audit::log('massmail','announce','0',[ 'success'=>$ok,'errors'=>$errors?array_slice($errors,0,3):[], 'server_id'=>$this->serverId ]);
        $this->appendActionLog('announce',$ok?1:0,0,0,0,$message);
        return ['success'=>$ok,'message'=>$ok?__('app.mass_mail.service.announce.success'):__('app.mass_mail.service.announce.partial'),'types'=>$sent,'errors'=>$errors];
    }

    public function sendBulk(string $action,string $subject,string $body,array $targets, string $itemsRaw = '', ?int $amount=null): array
    {
        $subject=trim($subject); if($subject==='') return ['success'=>false,'message'=>__('app.mass_mail.service.bulk.subject_required')];
        $body=trim((string)$body);
        $targets=array_values(array_unique(array_filter(array_map('trim',$targets),fn($v)=>$v!=='')));
        if(!$targets) return ['success'=>false,'message'=>__('app.mass_mail.service.bulk.no_targets')];
        $total=count($targets);
        if($total>$this->targetMax) return ['success'=>false,'message'=>__('app.mass_mail.service.bulk.target_limit',['max'=>$this->targetMax])];

        $items = [];
        if($action==='send_item' || $action==='send_item_gold'){
            $items = $this->parseItems($itemsRaw);
            if(!$items) return ['success'=>false,'message'=>__('app.mass_mail.service.bulk.items_invalid')];
            foreach($items as $it){
                if(!$this->validateItemExists($it['id'])){
                    return ['success'=>false,'message'=>__('app.mass_mail.service.bulk.item_missing',['id'=>$it['id']])];
                }
            }
        }
        if($action==='send_gold' || $action==='send_item_gold'){
            if(!$amount || $amount<=0) return ['success'=>false,'message'=>__('app.mass_mail.service.bulk.gold_invalid')];
        }

        $itemString = $items ? implode(' ', array_map(fn($it)=>$it['id'].':'.$it['qty'], $items)) : '';


        $success=0; $fail=0; $errors=[]; $sentNames=[]; $failedNames=[];
        $batches = array_chunk($targets,$this->batchSize);
        $batchIndex=0; $batchTotal=count($batches);
        foreach($batches as $chunk){
            $batchIndex++;
            foreach($chunk as $name){
                try {
                    if($action==='send_mail'){
                        $beforeMailId = $this->latestMailId();
                        $cmd=sprintf('.send mail %s "%s" "%s"',$name,$subject,$body);
                        $res = $this->soapExec->execute($cmd,[ 'server_id'=>$this->serverId, 'audit'=>true ]);
                        if($res['success']) {
                            $deliveryError = $this->confirmMailDelivery($name, $subject, $beforeMailId, null);
                            if($deliveryError === null) { $success++; $sentNames[]=$name; }
                            else { $fail++; $failedNames[]=$name; $errors[]=$name.':'.$deliveryError; }
                        }
                        else { $fail++; $failedNames[]=$name; $errors[]=$name.':'.($res['message'] ?? $res['code'] ?? 'fail'); }
                    }
                    elseif($action==='send_item'){
                        $beforeMailId = $this->latestMailId();
                        $cmd=sprintf('.send items %s "%s" "%s" %s',$name,$subject,$body,$itemString);
                        $res = $this->soapExec->execute($cmd,[ 'server_id'=>$this->serverId, 'audit'=>true ]);
                        if($res['success']) {
                            $deliveryError = $this->confirmMailDelivery($name, $subject, $beforeMailId, null);
                            if($deliveryError === null) { $success++; $sentNames[]=$name; }
                            else { $fail++; $failedNames[]=$name; $errors[]=$name.':'.$deliveryError; }
                        }
                        else { $fail++; $failedNames[]=$name; $errors[]=$name.':'.($res['message'] ?? $res['code'] ?? 'fail'); }
                    }
                    elseif($action==='send_gold'){
                        $beforeMailId = $this->latestMailId();
                        $cmd=sprintf('.send money %s "%s" "%s" %d',$name,$subject,$body,$amount);
                        $res = $this->soapExec->execute($cmd,[ 'server_id'=>$this->serverId, 'audit'=>true ]);
                        if($res['success']) {
                            $deliveryError = $this->confirmMailDelivery($name, $subject, $beforeMailId, $amount);
                            if($deliveryError === null) { $success++; $sentNames[]=$name; }
                            else { $fail++; $failedNames[]=$name; $errors[]=$name.':'.$deliveryError; }
                        }
                        else { $fail++; $failedNames[]=$name; $errors[]=$name.':'.($res['message'] ?? $res['code'] ?? 'fail'); }
                    }
                    elseif($action==='send_item_gold'){
                        $beforeItemsMailId = $this->latestMailId();
                        $cmdItems=sprintf('.send items %s "%s" "%s" %s',$name,$subject,$body,$itemString);
                        $resItems = $this->soapExec->execute($cmdItems,[ 'server_id'=>$this->serverId, 'audit'=>true ]);
                        $beforeGoldMailId = $this->latestMailId();
                        $cmdGold=sprintf('.send money %s "%s" "%s" %d',$name,$subject,$body,$amount);
                        $resGold = $this->soapExec->execute($cmdGold,[ 'server_id'=>$this->serverId, 'audit'=>true ]);
                        $itemsError = null;
                        if($resItems['success'] ?? false){
                            $itemsError = $this->confirmMailDelivery($name, $subject, $beforeItemsMailId, null);
                        }
                        $goldError = null;
                        if($resGold['success'] ?? false){
                            $goldError = $this->confirmMailDelivery($name, $subject, $beforeGoldMailId, $amount);
                        }
                        $ok = ($resItems['success'] ?? false) && ($resGold['success'] ?? false) && $itemsError === null && $goldError === null;
                        if($ok){
                            $success++; $sentNames[]=$name;
                        } else {
                            $fail++; $failedNames[]=$name;
                            if(!($resItems['success'] ?? false)) $errors[]=$name.'[items]:'.($resItems['message'] ?? $resItems['code'] ?? 'fail');
                            elseif($itemsError !== null) $errors[]=$name.'[items]:'.$itemsError;
                            if(!($resGold['success'] ?? false)) $errors[]=$name.'[gold]:'.($resGold['message'] ?? $resGold['code'] ?? 'fail');
                            elseif($goldError !== null) $errors[]=$name.'[gold]:'.$goldError;
                        }
                    }
                    else {
                        return ['success'=>false,'message'=>__('app.mass_mail.service.bulk.unknown_action')];
                    }
                } catch(Throwable $e){ $fail++; $failedNames[]=$name; $errors[]=$name.':'.$e->getMessage(); }
            }

        }
        $actionLabel=__('app.mass_mail.service.bulk.action_labels.'.$action, [], $action);
        $msg=__('app.mass_mail.service.bulk.summary',[
            'action'=>$actionLabel,
            'batches'=>$batchTotal,
            'targets'=>$total,
            'success'=>$success,
            'fail'=>$fail,
        ]);
        if($fail>0) $msg.=' '.__(
            'app.mass_mail.service.bulk.sample_errors',
            ['errors'=>implode(' | ',array_slice($errors,0,3))]
        );
        $ok = $fail===0;
        $itemsSummary = $items ? implode(' ', array_map(fn($it)=>$it['id'].':'.$it['qty'], $items)) : null;
        $this->logBulk($action,$subject,$itemsSummary,$amount,$total,$success,$fail,$errors,$sentNames,$failedNames);
        Audit::log('massmail',$action,'bulk',[ 'targets'=>$total,'success_count'=>$success,'fail_count'=>$fail,'items'=>$itemsSummary,'amount'=>$amount,'batches'=>$batchTotal,'batch_size'=>$this->batchSize,'sample_errors'=>array_slice($errors,0,3), 'server_id'=>$this->serverId ]);
        $this->appendActionLog($action,$success,$fail,0,$amount??0,$subject);
        return ['success'=>$ok,'message'=>$msg,'success_count'=>$success,'fail_count'=>$fail,'batches'=>$batchTotal,'batch_size'=>$this->batchSize];
    }

    public function recentLogs(int $limit=30): array
    { $limit=max(1,min($limit,100)); $st=$this->chars->prepare("SELECT id,created_at,action,subject,items,item_id,item_name,quantity,amount,targets,success_count,fail_count,success,recipients,sample_errors FROM {$this->logTable} ORDER BY id DESC LIMIT :lim"); $st->bindValue(':lim',$limit,PDO::PARAM_INT); $st->execute(); return $st->fetchAll(PDO::FETCH_ASSOC)?:[]; }


    private function getOnline(): array
    { $st=$this->chars->query('SELECT name FROM characters WHERE online=1'); return $st->fetchAll(PDO::FETCH_COLUMN)?:[]; }
    private function parseCustom(string $raw): array
    { $lines=preg_split('/\r\n|\r|\n/',$raw); $out=[]; foreach($lines as $ln){ $ln=trim($ln); if($ln!=='') $out[$ln]=true; } return array_keys($out); }

    private function characterGuidByName(string $name): ?int
    {
        $name = trim($name);
        if ($name === '')
            return null;

        $st = $this->chars->prepare('SELECT guid FROM characters WHERE name=:name LIMIT 1');
        $st->execute([':name' => $name]);
        $guid = $st->fetchColumn();

        return $guid === false ? null : (int) $guid;
    }

    private function latestMailId(): int
    {
        $st = $this->chars->query('SELECT COALESCE(MAX(id), 0) FROM mail');
        return (int) ($st->fetchColumn() ?: 0);
    }

    private function configuredCharactersDatabase(): string
    {
        try {
            $name = $this->chars->query('SELECT DATABASE()')->fetchColumn();
            if (is_string($name) && $name !== '')
                return $name;
        } catch (\Throwable $e) {
        }

        return 'unknown';
    }

    private function mailExistsAfter(int $receiverGuid, string $subject, int $afterId, ?int $money = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM mail WHERE receiver=:receiver AND id>:after_id AND subject=:subject';
        $params = [
            ':receiver' => $receiverGuid,
            ':after_id' => $afterId,
            ':subject' => $subject,
        ];

        if ($money !== null) {
            $sql .= ' AND money=:money';
            $params[':money'] = $money;
        }

        $st = $this->chars->prepare($sql);
        $st->execute($params);
        return (int) ($st->fetchColumn() ?: 0) > 0;
    }

    private function confirmMailDelivery(string $targetName, string $subject, int $afterId, ?int $money = null): ?string
    {
        $guid = $this->characterGuidByName($targetName);
        if ($guid === null) {
            return Lang::get('app.mass_mail.service.bulk.delivery_target_missing', ['target' => $targetName]);
        }

        for ($attempt = 0; $attempt < 4; $attempt++) {
            if ($this->mailExistsAfter($guid, $subject, $afterId, $money))
                return null;

            if ($attempt < 3)
                usleep(150000);
        }

        return Lang::get('app.mass_mail.service.bulk.delivery_not_confirmed', [
            'target' => $targetName,
            'database' => $this->configuredCharactersDatabase(),
        ]);
    }



    private function ensureLogTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->logTable} (".
            " id INT AUTO_INCREMENT PRIMARY KEY,".
            " created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,".
            " action VARCHAR(16) NOT NULL,".
            " subject VARCHAR(120) NOT NULL,".
            " items TEXT NULL,".
            " item_id INT NULL,".
            " item_name VARCHAR(160) NULL,".
            " quantity INT NULL,".
            " amount BIGINT NULL,".
            " targets INT NOT NULL,".
            " success_count INT NOT NULL,".
            " fail_count INT NOT NULL,".
            " success TINYINT(1) NOT NULL,".
            " recipients TEXT NULL,".
            " sample_errors TEXT NULL,".
            " KEY idx_created (created_at),".
            " KEY idx_action (action)".
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->chars->exec($sql);

    }

    private function logAnnounce(string $content,bool $ok,array $errors): void
    { $st=$this->chars->prepare("INSERT INTO {$this->logTable} (server_id,action,subject,items,item_id,item_name,quantity,amount,targets,success_count,fail_count,success,recipients,sample_errors) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"); $sample=$errors?implode(' | ',array_slice($errors,0,3)):null; $st->execute([$this->serverId,'announce',mb_substr($content,0,120),null,null,null,null,null,0,$ok?1:0,$ok?0:1,$ok?1:0,null,$sample]); }

    private function logBulk(string $action,string $subject,?string $items,?int $amount,int $targets,int $success,int $fail,array $errors,array $sent,array $failed): void
    {
        $itemId = null;
        $qty = null;
        $itemName = null;
        $itemsDisplay = $items ? mb_substr($items, 0, 800) : null;

        // 解析全部物品名，并把带名称的摘要写回 items 字段：
        // 日志列表里直接显示"霜之哀伤 ×1"，不再是一行裸 ID
        if($items){
            $parsed = $this->parseItems($items);
            if($parsed !== []){
                $names = $this->resolveItemNames(array_column($parsed, 'id'));
                $described = [];
                foreach($parsed as $entry){
                    $id = (int)$entry['id'];
                    $count = (int)$entry['qty'];
                    $name = $names[$id] ?? null;
                    $described[] = ($name !== null ? $name : ('#'.$id)).' ×'.$count;
                }
                $itemsDisplay = mb_substr(implode('、', $described), 0, 800);

                // 兼容历史"单物品"列
                $itemId = (int)$parsed[0]['id'];
                $qty = (int)$parsed[0]['qty'];
                $itemName = $names[$itemId] ?? null;
            }
        }
    $st=$this->chars->prepare("INSERT INTO {$this->logTable} (server_id,action,subject,items,item_id,item_name,quantity,amount,targets,success_count,fail_count,success,recipients,sample_errors) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $sample=$errors?implode(' | ',array_slice($errors,0,3)):null; $recipients=null;
        if(in_array($action,['send_mail','send_item','send_gold','send_item_gold'],true)){
            $markFailed=array_map(fn($n)=>$n.'!',$failed); $list=array_merge($sent,$markFailed); $list=array_values(array_unique($list)); $recipients=implode(',',$list); if(mb_strlen($recipients)>800){ $recipients=mb_substr($recipients,0,800); $pos=mb_strrpos($recipients,','); if($pos!==false) $recipients=mb_substr($recipients,0,$pos).',...'; }
        }
    $st->execute([$this->serverId,$action,mb_substr($subject,0,120),$itemsDisplay,$itemId,$itemName,$qty,$amount,$targets,$success,$fail,$fail===0?1:0,$recipients,$sample]);
    }

    /**
     * 批量解析物品名（world 库 item_template / locales_item，缺失时回退客户端 DBC）。
     *
     * 早期的实现是去 db.nfuwow.com 抓 <title>，该站在服务器网络下不可达，
     * 结果日志里的 item_name 一直是 NULL；这里改为完全本地解析。
     *
     * @param int[] $itemIds
     * @return array<int, string> id => 名称
     */
    public function resolveItemNames(array $itemIds): array
    {
        $wanted = [];
        foreach ($itemIds as $itemId) {
            $itemId = (int) $itemId;
            if ($itemId > 0) {
                $wanted[$itemId] = $itemId;
            }
        }
        if ($wanted === []) {
            return [];
        }

        // 1) 先看本地缓存文件里已经解析过的
        $resolved = [];
        $missing = [];
        foreach ($wanted as $itemId) {
            $cached = $this->itemNameCache[$itemId] ?? null;
            if (is_string($cached) && $cached !== '') {
                $resolved[$itemId] = $cached;
            } else {
                $missing[] = $itemId;
            }
        }

        if ($missing === []) {
            return $resolved;
        }

        // 2) 未命中再走 GameNameResolver（内部有按 realm+语言的磁盘缓存）
        try {
            $names = GameNameResolver::resolveMany('item', $missing);
        } catch (Throwable $exception) {
            $names = [];
        }

        if ($names !== []) {
            foreach ($names as $itemId => $name) {
                $this->itemNameCache[(int) $itemId] = $name;
            }
            $this->persistItemCache();
        }

        return $resolved + $names;
    }

    private function resolveItemName(?int $itemId): ?string
    {
        if (!$itemId || $itemId <= 0) {
            return null;
        }

        $names = $this->resolveItemNames([$itemId]);

        return $names[$itemId] ?? null;
    }

    private function loadItemCache(): void
    { if(is_file($this->itemCacheFile)){ $json=@file_get_contents($this->itemCacheFile); $data=json_decode($json,true); if(is_array($data)) $this->itemNameCache=$data; } }
    private function persistItemCache(): void
    { if(!$this->itemNameCache) return; if(count($this->itemNameCache)>8000){ $this->itemNameCache=array_slice($this->itemNameCache,-6500,null,true); } $dir=dirname($this->itemCacheFile); if(!is_dir($dir)) @mkdir($dir,0777,true); @file_put_contents($this->itemCacheFile,json_encode($this->itemNameCache,JSON_UNESCAPED_UNICODE)); }

    private function appendActionLog(string $action,int $successOrCount,int $fail,int $itemId,int $amount,string $subject): void
    { $user=$_SESSION['admin_user'] ?? ($_SESSION['username'] ?? 'unknown'); $line=sprintf('[%s]|srv:%d|%s|%s|succ:%d|fail:%d|item:%d|amount:%d|%s',date('Y-m-d H:i:s'),$this->serverId,$user,$action,$successOrCount,$fail,$itemId,$amount,mb_substr(str_replace(["\r","\n"],' ',$subject),0,80)); \Acme\Panel\Support\LogPath::appendTo($this->actionLogFile, $line, true, 0777); }

    private function migrateAddServerIdColumn(): void
    { try { $chk=$this->chars->query("SHOW COLUMNS FROM {$this->logTable} LIKE 'server_id'"); if(!$chk->fetch()){ $this->chars->exec("ALTER TABLE {$this->logTable} ADD server_id INT NOT NULL DEFAULT 0 AFTER id, ADD KEY idx_server(server_id)"); } }catch(\Throwable $e){} }

    private function migrateAddItemsColumn(): void
    {
        try {
            $chk=$this->chars->query("SHOW COLUMNS FROM {$this->logTable} LIKE 'items'");
            if(!$chk->fetch()){
                $this->chars->exec("ALTER TABLE {$this->logTable} ADD items TEXT NULL AFTER subject");
            }
        } catch(\Throwable $e){}
    }

    private function parseItems(string $raw): array
    {
        $raw = trim($raw);
        if($raw==='') return [];
        $tokens = preg_split('/[\s,;]+|\r\n|\r|\n/', $raw);
        $items = [];
        foreach($tokens as $tok){
            $tok = trim((string)$tok);
            if($tok==='') continue;
            if(!preg_match('/^(\d+)\s*:\s*(\d+)$/', $tok, $m)){
                return [];
            }
            $id = (int)$m[1];
            $qty = (int)$m[2];
            if($id<=0 || $qty<=0) return [];
            $items[] = ['id'=>$id,'qty'=>$qty];
        }
        return $items;
    }

    private function validateItemExists(int $itemId): bool
    { if($itemId<=0) return false; if(!$this->world) return true; try{ $st=$this->world->prepare('SELECT 1 FROM item_template WHERE entry=:i LIMIT 1'); $st->execute([':i'=>$itemId]); return (bool)$st->fetchColumn(); }catch(\Throwable $e){ return true; } }
}

?>
