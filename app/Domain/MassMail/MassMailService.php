<?php
/**
 * File: app/Domain/MassMail/MassMailService.php
 * Purpose: Defines class MassMailService for the app/Domain/MassMail module.
 * Classes:
 *   - MassMailService
 * Functions:
 *   - __construct()
 *   - resolveTargets()
 *   - sendAnnounce()
 *   - sendBulk()
 *   - boostCharacter()
 *   - recentLogs()
 *   - buildBoostItems()
 *   - resolveClassKeyByName()
 *   - boostClassLabel()
 *   - logBoost()
 *   - getOnline()
 *   - parseCustom()
 *   - ensureLogTable()
 *   - logAnnounce()
 *   - logBulk()
 *   - resolveItemName()
 *   - fetchExternalItemName()
 *   - loadItemCache()
 *   - persistItemCache()
 *   - appendActionLog()
 *   - migrateAddServerIdColumn()
 *   - validateItemExists()
 */

namespace Acme\Panel\Domain\MassMail;

use Acme\Panel\Core\Database; use Acme\Panel\Support\Audit; use Acme\Panel\Support\ServerContext; use Acme\Panel\Support\SoapExecutor; use PDO; use SoapFault; use Throwable;









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


    private const BOOST_ALLOWED_LEVELS = [60,70,80];
    private const BOOST_GOLD_COPPER = 500 * 10000;
    private const BOOST_EXTRA_ITEMS = [
        ['id'=>21841,'count'=>3],

        ['id'=>23720,'count'=>1],
    ];
    private const BOOST_CLASS_ITEMS = [
        'mage'    => [16912,16913,16914,16915,16916,16917,16918,16818],
        'warrior' => [16959,16960,16961,16962,16963,16964,16965,16966],
        'priest'  => [16919,16920,16921,16922,16923,16924,16925,16926],
        'shaman'  => [16943,16944,16945,16946,16947,16948,16949,16950],
        'druid'   => [16897,16898,16899,16900,16901,16902,16903,16904],
        'warlock' => [16927,16928,16929,16930,16931,16932,16933,16934],
        'paladin' => [16951,16952,16953,16954,16955,16956,16957,16958],
        'hunter'  => [16935,16936,16937,16938,16939,16940,16941,16942],
        'rogue'   => [16905,16906,16907,16908,16909,16910,16911,16832],
    ];
    private const BOOST_CLASS_LOOKUP = [
        1=>'warrior',
        2=>'paladin',
        3=>'hunter',
        4=>'rogue',
        5=>'priest',
        7=>'shaman',
        8=>'mage',
        9=>'warlock',
        11=>'druid'
    ];

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
        $this->ensureLogTable(); $this->migrateAddServerIdColumn();
    }


    public function resolveTargets(string $type, ?string $customList): array
    {
        if($type==='online') return $this->getOnline();
        if($type==='custom') return $this->parseCustom($customList??'');
        return [];
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

    public function sendBulk(string $action,string $subject,string $body,array $targets, ?int $itemId=null, ?int $qty=null, ?int $amount=null): array
    {
        $subject=trim($subject); if($subject==='') return ['success'=>false,'message'=>__('app.mass_mail.service.bulk.subject_required')];
        $body=trim((string)$body);
        $targets=array_values(array_unique(array_filter(array_map('trim',$targets),fn($v)=>$v!=='')));
        if(!$targets) return ['success'=>false,'message'=>__('app.mass_mail.service.bulk.no_targets')];
        $total=count($targets);
        if($total>$this->targetMax) return ['success'=>false,'message'=>__('app.mass_mail.service.bulk.target_limit',['max'=>$this->targetMax])];
        if($action==='send_item'){
            if(!$itemId || $itemId<=0 || !$qty || $qty<=0) return ['success'=>false,'message'=>__('app.mass_mail.service.bulk.item_invalid')];

            if(!$this->validateItemExists($itemId)) return ['success'=>false,'message'=>__('app.mass_mail.service.bulk.item_missing',['id'=>$itemId])];
        }
        if($action==='send_gold'){ if(!$amount || $amount<=0) return ['success'=>false,'message'=>__('app.mass_mail.service.bulk.gold_invalid')]; }


        $success=0; $fail=0; $errors=[]; $sentNames=[]; $failedNames=[];
        $batches = array_chunk($targets,$this->batchSize);
        $batchIndex=0; $batchTotal=count($batches);
        foreach($batches as $chunk){
            $batchIndex++;
            foreach($chunk as $name){
                try {
                    $cmd='';
                    if($action==='send_mail') $cmd=sprintf('.send mail %s "%s" "%s"',$name,$subject,$body);
                    elseif($action==='send_item') $cmd=sprintf('.send items %s "%s" "%s" %d:%d',$name,$subject,$body,$itemId,$qty);
                    elseif($action==='send_gold') $cmd=sprintf('.send money %s "%s" "%s" %d',$name,$subject,$body,$amount);
                    else return ['success'=>false,'message'=>__('app.mass_mail.service.bulk.unknown_action')];
                    $res = $this->soapExec->execute($cmd,[ 'server_id'=>$this->serverId, 'audit'=>true ]);
                    if($res['success']) { $success++; $sentNames[]=$name; }
                    else { $fail++; $failedNames[]=$name; $errors[]=$name.':'.($res['message'] ?? $res['code'] ?? 'fail'); }
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
    $this->logBulk($action,$subject,$itemId,$qty,$amount,$total,$success,$fail,$errors,$sentNames,$failedNames);
    Audit::log('massmail',$action,'bulk',[ 'targets'=>$total,'success_count'=>$success,'fail_count'=>$fail,'item_id'=>$itemId,'qty'=>$qty,'amount'=>$amount,'batches'=>$batchTotal,'batch_size'=>$this->batchSize,'sample_errors'=>array_slice($errors,0,3), 'server_id'=>$this->serverId ]);
        $this->appendActionLog($action,$success,$fail,$itemId??0,$amount??0,$subject);
        return ['success'=>$ok,'message'=>$msg,'success_count'=>$success,'fail_count'=>$fail,'batches'=>$batchTotal,'batch_size'=>$this->batchSize];
    }

    public function boostCharacter(string $name,int $level): array
    {
        $name=trim($name);
        if($name==='') return ['success'=>false,'message'=>__('app.mass_mail.service.boost.name_required')];
        if(!in_array($level,self::BOOST_ALLOWED_LEVELS,true)) return ['success'=>false,'message'=>__('app.mass_mail.service.boost.level_unsupported')];

        $classKey = $this->resolveClassKeyByName($name);
        if(!$classKey){
            return ['success'=>false,'message'=>__('app.mass_mail.service.boost.character_missing')];
        }

        $items=$this->buildBoostItems($classKey);
        if(!$items) return ['success'=>false,'message'=>__('app.mass_mail.service.boost.config_empty')];
        $itemString=implode(' ',array_map(fn($item)=>$item['id'].':'.$item['count'],$items));
        $subject=__('app.mass_mail.service.boost.mail.subject');
        $classLabel=$this->boostClassLabel($classKey);
        $bodyItems=__('app.mass_mail.service.boost.mail.body_items');
        $bodyGold=__('app.mass_mail.service.boost.mail.body_gold');

        $commands=[
            'items'=>[
                'label'=>__('app.mass_mail.service.boost.commands.items'),
                'command'=>sprintf('.send items %s "%s" "%s" %s',$name,$subject,$bodyItems,$itemString)
            ],
            'gold'=>[
                'label'=>__('app.mass_mail.service.boost.commands.gold'),
                'command'=>sprintf('.send money %s "%s" "%s" %d',$name,$subject,$bodyGold,self::BOOST_GOLD_COPPER)
            ],
            'level'=>[
                'label'=>__('app.mass_mail.service.boost.commands.level'),
                'command'=>sprintf('.character level %s %d',$name,$level)
            ]
        ];

        $results=[]; $errors=[];
        foreach($commands as $key=>$meta){
            $res=$this->soapExec->execute($meta['command'],['server_id'=>$this->serverId,'audit'=>true]);
            $results[$key]=$res;
            if(!$res['success']){
                $reason=$res['message'] ?? $res['code'] ?? __('app.mass_mail.service.boost.unknown_error');
                $errors[]=__('app.mass_mail.service.boost.command_failed',['label'=>$meta['label'],'reason'=>$reason]);
            }
        }

        $ok=empty($errors);
        $summaryItems=implode(',',array_map(fn($item)=>$item['id'].':'.$item['count'],$items));
        $message=$ok
            ? __('app.mass_mail.service.boost.success',['name'=>$name,'level'=>$level])
            : __('app.mass_mail.service.boost.partial',['errors'=>implode(' | ',array_slice($errors,0,3))]);

        $this->logBoost($name,$classKey,$level,$ok,$summaryItems,$errors);
        Audit::log('massmail','boost',$name,[
            'level'=>$level,
            'class'=>$classKey,
            'success'=>$ok,
            'errors'=>array_slice($errors,0,3),
            'items'=>$summaryItems,
            'server_id'=>$this->serverId
        ]);
        $this->appendActionLog('boost',$ok?1:0,count($errors),0,self::BOOST_GOLD_COPPER,$subject);

        return ['success'=>$ok,'message'=>$message,'errors'=>$errors,'results'=>$results,'class_label'=>$classLabel,'items'=>$summaryItems];
    }

    public function recentLogs(int $limit=30): array
    { $limit=max(1,min($limit,100)); $st=$this->chars->prepare("SELECT id,created_at,action,subject,item_id,item_name,quantity,amount,targets,success_count,fail_count,success,recipients,sample_errors FROM {$this->logTable} ORDER BY id DESC LIMIT :lim"); $st->bindValue(':lim',$limit,PDO::PARAM_INT); $st->execute(); return $st->fetchAll(PDO::FETCH_ASSOC)?:[]; }


    private function buildBoostItems(string $classKey): array
    {
        $items=[];
        foreach(self::BOOST_CLASS_ITEMS[$classKey] ?? [] as $id){ $items[]=['id'=>$id,'count'=>1]; }
        foreach(self::BOOST_EXTRA_ITEMS as $extra){ $items[]=$extra; }
        return $items;
    }

    private function resolveClassKeyByName(string $name): ?string
    {
        $st=$this->chars->prepare('SELECT class FROM characters WHERE name=:name LIMIT 1');
        $st->execute([':name'=>$name]);
        $cls=$st->fetchColumn();
        if($cls===false) return null;
        $clsId=(int)$cls;
        return self::BOOST_CLASS_LOOKUP[$clsId] ?? null;
    }

    private function boostClassLabel(string $classKey): string
    {
        return __('app.mass_mail.service.boost.class_labels.'.$classKey, [], $classKey);
    }

    private function logBoost(string $name,string $classKey,int $level,bool $ok,string $itemSummary,array $errors): void
    {
        $subject=__('app.mass_mail.service.boost.log_subject',['name'=>mb_substr($name,0,60)]);
        $classLabel=$this->boostClassLabel($classKey);
        $itemLabel=__('app.mass_mail.service.boost.log_item_label',['class'=>$classLabel]);
        $quantity=count(self::BOOST_CLASS_ITEMS[$classKey] ?? []);
        $recipients=$ok? $name : ($name.'!');
        $sample=$errors? implode(' | ',array_slice($errors,0,3)) : null;
        $st=$this->chars->prepare("INSERT INTO {$this->logTable} (server_id,action,subject,item_id,item_name,quantity,amount,targets,success_count,fail_count,success,recipients,sample_errors) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $st->execute([
            $this->serverId,
            'boost',
            mb_substr($subject,0,120),
            null,
            mb_substr($itemLabel.': '.$itemSummary,0,160),
            $quantity,
            self::BOOST_GOLD_COPPER,
            1,
            $ok?1:0,
            $ok?0:1,
            $ok?1:0,
            mb_substr($recipients,0,160),
            $sample
        ]);
    }

    private function getOnline(): array
    { $st=$this->chars->query('SELECT name FROM characters WHERE online=1'); return $st->fetchAll(PDO::FETCH_COLUMN)?:[]; }
    private function parseCustom(string $raw): array
    { $lines=preg_split('/\r\n|\r|\n/',$raw); $out=[]; foreach($lines as $ln){ $ln=trim($ln); if($ln!=='') $out[$ln]=true; } return array_keys($out); }



    private function ensureLogTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->logTable} (".
            " id INT AUTO_INCREMENT PRIMARY KEY,".
            " created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,".
            " action VARCHAR(16) NOT NULL,".
            " subject VARCHAR(120) NOT NULL,".
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
    { $st=$this->chars->prepare("INSERT INTO {$this->logTable} (server_id,action,subject,item_id,item_name,quantity,amount,targets,success_count,fail_count,success,recipients,sample_errors) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"); $sample=$errors?implode(' | ',array_slice($errors,0,3)):null; $st->execute([$this->serverId,'announce',mb_substr($content,0,120),null,null,null,null,0,$ok?1:0,$ok?0:1,$ok?1:0,null,$sample]); }

    private function logBulk(string $action,string $subject,?int $itemId,?int $qty,?int $amount,int $targets,int $success,int $fail,array $errors,array $sent,array $failed): void
    {
        $itemName=$this->resolveItemName($itemId);
    $st=$this->chars->prepare("INSERT INTO {$this->logTable} (server_id,action,subject,item_id,item_name,quantity,amount,targets,success_count,fail_count,success,recipients,sample_errors) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $sample=$errors?implode(' | ',array_slice($errors,0,3)):null; $recipients=null;
        if(in_array($action,['send_mail','send_item','send_gold'],true)){
            $markFailed=array_map(fn($n)=>$n.'!',$failed); $list=array_merge($sent,$markFailed); $list=array_values(array_unique($list)); $recipients=implode(',',$list); if(mb_strlen($recipients)>800){ $recipients=mb_substr($recipients,0,800); $pos=mb_strrpos($recipients,','); if($pos!==false) $recipients=mb_substr($recipients,0,$pos).',...'; }
        }
    $st->execute([$this->serverId,$action,mb_substr($subject,0,120),$itemId,$itemName,$qty,$amount,$targets,$success,$fail,$fail===0?1:0,$recipients,$sample]);
    }

    private function resolveItemName(?int $itemId): ?string
    { if(!$itemId || $itemId<=0) return null; if(isset($this->itemNameCache[$itemId])) return $this->itemNameCache[$itemId]; $name=$this->fetchExternalItemName($itemId); $this->itemNameCache[$itemId]=$name; $this->persistItemCache(); return $name; }

    private function fetchExternalItemName(int $itemId): ?string
    { $base='https://db.nfuwow.com/80/?item='; $ctx=stream_context_create(['http'=>['timeout'=>3,'ignore_errors'=>true,'header'=>"Accept-Language: zh-CN,zh;q=0.9,en;q=0.5\r\nUser-Agent: PanelMassMail/1.0"]]); $html=@file_get_contents($base.$itemId,false,$ctx); if($html && preg_match('/<title>(.*?)<\\/title>/i',$html,$m)){ $raw=html_entity_decode($m[1],ENT_QUOTES,'UTF-8'); $title=trim(preg_split('/\s+-\s+/',$raw)[0]??$raw); return $title!==''?$title:null; } return null; }

    private function loadItemCache(): void
    { if(is_file($this->itemCacheFile)){ $json=@file_get_contents($this->itemCacheFile); $data=json_decode($json,true); if(is_array($data)) $this->itemNameCache=$data; } }
    private function persistItemCache(): void
    { if(!$this->itemNameCache) return; if(count($this->itemNameCache)>3000){ $this->itemNameCache=array_slice($this->itemNameCache,-2500,null,true); } $dir=dirname($this->itemCacheFile); if(!is_dir($dir)) @mkdir($dir,0777,true); @file_put_contents($this->itemCacheFile,json_encode($this->itemNameCache,JSON_UNESCAPED_UNICODE)); }

    private function appendActionLog(string $action,int $successOrCount,int $fail,int $itemId,int $amount,string $subject): void
    { $dir=dirname($this->actionLogFile); if(!is_dir($dir)) @mkdir($dir,0777,true); $user=$_SESSION['admin_user'] ?? ($_SESSION['username'] ?? 'unknown'); $line=sprintf('[%s]|srv:%d|%s|%s|succ:%d|fail:%d|item:%d|amount:%d|%s',date('Y-m-d H:i:s'),$this->serverId,$user,$action,$successOrCount,$fail,$itemId,$amount,mb_substr(str_replace(["\r","\n"],' ',$subject),0,80)); @file_put_contents($this->actionLogFile,$line.PHP_EOL,FILE_APPEND); }

    private function migrateAddServerIdColumn(): void
    { try { $chk=$this->chars->query("SHOW COLUMNS FROM {$this->logTable} LIKE 'server_id'"); if(!$chk->fetch()){ $this->chars->exec("ALTER TABLE {$this->logTable} ADD server_id INT NOT NULL DEFAULT 0 AFTER id, ADD KEY idx_server(server_id)"); } }catch(\Throwable $e){} }

    private function validateItemExists(int $itemId): bool
    { if($itemId<=0) return false; if(!$this->world) return true; try{ $st=$this->world->prepare('SELECT 1 FROM item_template WHERE entry=:i LIMIT 1'); $st->execute([':i'=>$itemId]); return (bool)$st->fetchColumn(); }catch(\Throwable $e){ return true; } }
}

?>
