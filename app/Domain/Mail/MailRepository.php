<?php
/**
 * File: app/Domain/Mail/MailRepository.php
 * Purpose: Defines class MailRepository for the app/Domain/Mail module.
 * Classes:
 *   - MailRepository
 * Functions:
 *   - __construct()
 *   - search()
 *   - getWithItems()
 *   - get()
 *   - getItems()
 *   - markRead()
 *   - markReadBulk()
 *   - delete()
 *   - deleteBulk()
 *   - stats()
 *   - tailLog()
 *   - countWith()
 *   - characterAccount()
 *   - isGmAccount()
 *   - resolveItemNames()
 *   - loadItemNameCache()
 *   - persistItemNameCache()
 *   - logsDir()
 *   - currentUser()
 *   - appendSqlLog()
 *   - appendDeletedLog()
 *   - readLogTail()
 *   - parseLogLine()
 *   - formatLogEntry()
 *   - parseServerId()
 */

namespace Acme\Panel\Domain\Mail;

use PDO;
use Acme\Panel\Support\Audit;
use Acme\Panel\Domain\Support\MultiServerRepository;
use Acme\Panel\Support\ServerContext;
use Acme\Panel\Core\Database;
use Acme\Panel\Core\Lang;

class MailRepository extends MultiServerRepository
{
    private PDO $chars;
    private ?PDO $auth;
    private ?PDO $world;
    private array $itemNameCache = [];
    private string $itemNameCacheFile;

    public function __construct()
    {
        parent::__construct();

    $this->serverId = ServerContext::currentId();

        $this->chars = $this->characters();

        try { $this->auth = ServerContext::db('auth') ? $this->auth() : null; } catch (\Throwable $e) { $this->auth = null; }
        try { $this->world = ServerContext::db('world') ? $this->world() : null; } catch (\Throwable $e) { $this->world = null; }

        $baseCacheDir = dirname(__DIR__,3).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'cache';
        if(!is_dir($baseCacheDir)) @mkdir($baseCacheDir,0777,true);
        $this->itemNameCacheFile = $baseCacheDir.DIRECTORY_SEPARATOR.'mail_item_names_s'.$this->serverId.'.json';
        $this->loadItemNameCache();
    }

    public function search(array $filters, int $limit, int $offset, string $sortCol, string $sortDir): array
    {
        $where = ' WHERE 1=1'; $params=[];
        if(($filters['sender'] ?? '') !== ''){ $where.=' AND cs.name LIKE :sender'; $params[':sender']='%'.trim($filters['sender']).'%'; }
        if(($filters['receiver'] ?? '') !== ''){ $where.=' AND cr.name LIKE :receiver'; $params[':receiver']='%'.trim($filters['receiver']).'%'; }
        if(($filters['subject'] ?? '') !== ''){ $where.=' AND m.subject LIKE :subject'; $params[':subject']='%'.trim($filters['subject']).'%'; }
        if(($filters['unread'] ?? '') === '1'){ $where.=' AND (m.checked=0 OR m.checked IS NULL)'; }
        if(($filters['has_items'] ?? '') === '1'){ $where.=' AND (m.has_items=1 OR EXISTS(SELECT 1 FROM mail_items mi WHERE mi.mail_id=m.id))'; }
        if(($filters['expiring'] ?? '') !== ''){ $d=(int)$filters['expiring']; if($d>0){ $where.=' AND m.expire_time>UNIX_TIMESTAMP() AND (m.expire_time-UNIX_TIMESTAMP()) <= :exp'; $params[':exp']=$d*86400; } }
        $sortAllowed=['id','expire_time','deliver_time','money']; if(!in_array($sortCol,$sortAllowed,true)) $sortCol='id'; $sortDir = strtoupper($sortDir)==='ASC'?'ASC':'DESC';
        $cnt=$this->chars->prepare('SELECT COUNT(*) FROM mail m LEFT JOIN characters cs ON cs.guid=m.sender LEFT JOIN characters cr ON cr.guid=m.receiver'.$where); foreach($params as $k=>$v){ $cnt->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);} $cnt->execute(); $total=(int)$cnt->fetchColumn();
        $sql='SELECT m.id,m.sender,m.receiver,m.subject,m.money,m.has_items,m.expire_time,m.deliver_time,m.checked,'.
            ' cs.name AS sender_name, cr.name AS receiver_name,(CASE WHEN m.expire_time<UNIX_TIMESTAMP() THEN 1 ELSE 0 END) AS is_expired '
            .'FROM mail m LEFT JOIN characters cs ON cs.guid=m.sender LEFT JOIN characters cr ON cr.guid=m.receiver'
            .$where.' ORDER BY m.`'.$sortCol.'` '.$sortDir.' LIMIT :lim OFFSET :off';
        $st=$this->chars->prepare($sql); foreach($params as $k=>$v){ $st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);} $st->bindValue(':lim',$limit,PDO::PARAM_INT); $st->bindValue(':off',$offset,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll(PDO::FETCH_ASSOC);
        return ['rows'=>$rows,'total'=>$total];
    }

    public function getWithItems(int $id): ?array
    { $row=$this->get($id); if(!$row) return null; $row['items']=$this->getItems($id); return $row; }

    public function get(int $id): ?array
    { if($id<=0) return null; $st=$this->chars->prepare('SELECT m.*, cs.name AS sender_name, cr.name AS receiver_name FROM mail m LEFT JOIN characters cs ON cs.guid=m.sender LEFT JOIN characters cr ON cr.guid=m.receiver WHERE m.id=:id'); $st->execute([':id'=>$id]); $r=$st->fetch(PDO::FETCH_ASSOC); return $r?:null; }

    public function getItems(int $mailId): array
    {
        $variants=[
            'SELECT mi.mail_id,mi.item_guid,mi.receiver,mi.item_template AS item_template FROM mail_items mi WHERE mi.mail_id=:mid',
            'SELECT mi.mail_id,mi.item_guid,mi.receiver,ii.itemEntry AS item_template FROM mail_items mi LEFT JOIN item_instance ii ON ii.guid=mi.item_guid WHERE mi.mail_id=:mid',
            'SELECT mi.mail_id,mi.item_guid,mi.receiver,ii.itemEntry AS item_template FROM mail_items mi LEFT JOIN item_instances ii ON ii.guid=mi.item_guid WHERE mi.mail_id=:mid'
        ];
        $rows=[]; foreach($variants as $sql){ try{ $st=$this->chars->prepare($sql); $st->bindValue(':mid',$mailId,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll(PDO::FETCH_ASSOC); if($rows!==false){ break; } }catch(\PDOException $e){ $m=$e->getMessage(); if(stripos($m,'Unknown column')===false && stripos($m,'doesn\'t exist')===false) throw $e; }}
        if(!$rows) return [];
        $ids=array_values(array_unique(array_map(fn($r)=>(int)($r['item_template']??0),$rows)));
        $resolved=$ids? $this->resolveItemNames($ids):[];

        $english=[]; $qualities=[];
        if($ids && $this->world){
            try{
                $in=implode(',',array_fill(0,count($ids),'?'));
                $sqlEn='SELECT i.entry,i.name,i.Quality FROM item_template i WHERE i.entry IN ('.$in.')';
                $stEn=$this->world->prepare($sqlEn);
                foreach($ids as $i=>$v){ $stEn->bindValue($i+1,$v,PDO::PARAM_INT);} $stEn->execute();
                while($r=$stEn->fetch(PDO::FETCH_ASSOC)){
                    $entry=(int)($r['entry']??0);
                    if($entry<=0) continue;
                    $nm=trim((string)($r['name']??'')); if($nm!==''){ $english[$entry]=$nm; }
                    if(isset($r['Quality'])){ $qualities[$entry]=(int)$r['Quality']; }
                }
            }catch(\Throwable $e){}
        }
        foreach($rows as &$r){
            $id=(int)($r['item_template']??0);
            $cn=$resolved[$id]??null; $en=$english[$id]??null;
            $r['item_name_cn']=$cn;

            $r['item_name_en']=$en;


            $r['item_name'] = $cn ?: $en;
            $quality = $qualities[$id] ?? null;
            if($quality !== null){
                $quality = (int)$quality;
                $r['item_quality']=$quality;
                $r['item_quality_class']='item-quality-q'.$quality;
            } else {
                $r['item_quality']=null;
                $r['item_quality_class']=null;
            }
        }
        return $rows;
    }

    public function markRead(int $id): bool
    { if($id<=0) return false; $st=$this->chars->prepare('UPDATE mail SET checked=1 WHERE id=:id AND (checked=0 OR checked IS NULL)'); $st->execute([':id'=>$id]); $ok=$st->rowCount()>0; if($ok){ $this->appendSqlLog('UPDATE',true,1,'UPDATE mail SET checked=1 WHERE id='.$id,''); } return $ok; }

    public function markReadBulk(array $ids): array
    { $ids=array_values(array_unique(array_filter(array_map('intval',$ids),fn($v)=>$v>0))); if(!$ids) return ['affected'=>0,'updated'=>[]]; $in=implode(',',array_fill(0,count($ids),'?')); $sel=$this->chars->prepare("SELECT id FROM mail WHERE (checked=0 OR checked IS NULL) AND id IN ($in)"); foreach($ids as $i=>$v){ $sel->bindValue($i+1,$v,PDO::PARAM_INT);} $sel->execute(); $targets=$sel->fetchAll(PDO::FETCH_COLUMN)?:[]; if(!$targets) return ['affected'=>0,'updated'=>[]]; $in2=implode(',',array_fill(0,count($targets),'?')); $upd=$this->chars->prepare("UPDATE mail SET checked=1 WHERE id IN ($in2)"); foreach($targets as $i=>$v){ $upd->bindValue($i+1,$v,PDO::PARAM_INT);} $upd->execute(); return ['affected'=>$upd->rowCount(),'updated'=>array_map('intval',$targets)]; }

    public function delete(int $id): bool
    { if($id<=0) return false; $st=$this->chars->prepare('SELECT sender FROM mail WHERE id=:id'); $st->execute([':id'=>$id]); $sender=$st->fetchColumn(); if($sender===false) return false; $sender=(int)$sender; if($sender===0){ $del=$this->chars->prepare('DELETE FROM mail WHERE id=:id AND sender=0'); $del->execute([':id'=>$id]); $ok=$del->rowCount()>0; if($ok){ $this->appendDeletedLog('DELETE',$id,'DELETE FROM mail WHERE id='.$id.' AND sender=0'); } return $ok; } if(!$this->auth) return false; $acc=$this->characterAccount($sender); if(!$acc) return false; if($this->isGmAccount($acc)){ $del=$this->chars->prepare('DELETE FROM mail WHERE id=:id AND sender=:s'); $del->execute([':id'=>$id,':s'=>$sender]); $ok=$del->rowCount()>0; if($ok){ $this->appendDeletedLog('DELETE',$id,'DELETE FROM mail WHERE id='.$id.' AND sender='.$sender); } return $ok; } return false; }

    public function deleteBulk(array $ids): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),fn($v)=>$v>0))); if(!$ids) return ['deleted'=>[], 'blocked'=>[]];
        $in=implode(',',array_fill(0,count($ids),'?')); $st=$this->chars->prepare("SELECT id,sender FROM mail WHERE id IN ($in)"); foreach($ids as $i=>$v){ $st->bindValue($i+1,$v,PDO::PARAM_INT);} $st->execute(); $rows=$st->fetchAll(PDO::FETCH_ASSOC)?:[]; if(!$rows) return ['deleted'=>[], 'blocked'=>[]];
        $system=[]; $gmCheck=[]; foreach($rows as $r){ $sid=(int)$r['sender']; $mid=(int)$r['id']; if($sid===0) $system[]=$mid; else $gmCheck[$mid]=$sid; }
        $gmAllowed=[]; if($gmCheck && $this->auth){
            $guidVals=array_values(array_unique(array_values($gmCheck))); $inC=implode(',',array_fill(0,count($guidVals),'?')); $map=$this->chars->prepare("SELECT guid,account FROM characters WHERE guid IN ($inC)"); foreach($guidVals as $i=>$g){ $map->bindValue($i+1,$g,PDO::PARAM_INT);} $map->execute(); $g2a=[]; while($r=$map->fetch(PDO::FETCH_ASSOC)){ $g2a[(int)$r['guid']]=(int)$r['account']; }
            if($g2a){ $accIds=array_values(array_unique(array_filter($g2a,fn($v)=>$v>0))); if($accIds){ $inA=implode(',',array_fill(0,count($accIds),'?')); $accSt=$this->auth->prepare("SELECT id,gmlevel FROM account_access WHERE RealmID=-1 AND id IN ($inA)"); foreach($accIds as $i=>$aid){ $accSt->bindValue($i+1,$aid,PDO::PARAM_INT);} $accSt->execute(); $gmAcc=[]; while($r=$accSt->fetch(PDO::FETCH_ASSOC)){ if((int)$r['gmlevel']>0) $gmAcc[(int)$r['id']]=true; } if($gmAcc){ foreach($gmCheck as $mid=>$guid){ $acc=$g2a[$guid]??0; if($acc && isset($gmAcc[$acc])) $gmAllowed[]=$mid; } } } }
        }
        $allow=array_values(array_unique(array_merge($system,$gmAllowed))); $blocked=array_values(array_diff(array_keys($gmCheck),$allow)); $deleted=[]; if($allow){ $inDel=implode(',',array_fill(0,count($allow),'?')); $del=$this->chars->prepare("DELETE FROM mail WHERE id IN ($inDel)"); foreach($allow as $i=>$mid){ $del->bindValue($i+1,$mid,PDO::PARAM_INT);} $del->execute(); $deleted=$allow; if($deleted){ $this->appendDeletedLog('BULK_DELETE',0,'DELETE mail ids='.implode(',',$deleted)); } }
        return ['deleted'=>$deleted,'blocked'=>$blocked];
    }

    public function stats(): array
    { $unread=$this->countWith(['unread'=>'1']); $exp7=$this->countWith(['expiring'=>'7']); return ['unread_estimate'=>$unread,'expiring_7d'=>$exp7]; }

    public function tailLog(string $type,int $limit=50): array
    {
        $limit = max(1, min(200, $limit));
        $map = ['sql'=>'mail_sql.log','deleted'=>'mail_deleted.log'];
    if(!isset($map[$type])) return ['success'=>false,'message'=>Lang::get('app.mail.tail_log.unknown_type')];
        $file = $this->logsDir().DIRECTORY_SEPARATOR.$map[$type];
        if(!is_file($file)){
            return ['success'=>true,'type'=>$type,'logs'=>[],'entries'=>[],'file'=>$map[$type],'limit'=>$limit];
        }
        $rawLines = $this->readLogTail($file,$limit);
        $entries = [];
        $formatted = [];
        foreach($rawLines as $line){
            $entry = $this->parseLogLine($type,$line);
            if($entry){
                $entries[] = $entry;
                $formatted[] = $this->formatLogEntry($type,$entry);
            } else {
                $formatted[] = $line;
            }
        }
        return ['success'=>true,'type'=>$type,'logs'=>$formatted,'entries'=>$entries,'file'=>$map[$type],'limit'=>$limit];
    }

    private function countWith(array $filters): int
    { $res=$this->search($filters,1,0,'id','DESC'); return $res['total']; }

    private function characterAccount(int $guid): ?int
    { $st=$this->chars->prepare('SELECT account FROM characters WHERE guid=:g LIMIT 1'); $st->execute([':g'=>$guid]); $acc=$st->fetchColumn(); return $acc===false?null:(int)$acc; }
    private function isGmAccount(int $acc): bool
    { if(!$this->auth) return false; $st=$this->auth->prepare('SELECT gmlevel FROM account_access WHERE id=:id AND RealmID=-1 LIMIT 1'); $st->execute([':id'=>$acc]); $gm=(int)($st->fetchColumn()?:0); return $gm>0; }

    private function resolveItemNames(array $ids): array
    {
        $out=[]; $remain=[]; foreach($ids as $id){ if(isset($this->itemNameCache[$id])) $out[$id]=$this->itemNameCache[$id]; else $remain[]=$id; }
        if($remain && $this->world){ try{ $in=implode(',',array_fill(0,count($remain),'?')); $sql='SELECT i.entry,COALESCE(li.name_loc4,li.name_loc8,li.name_loc6,li.name_loc5,i.name) AS name_cn FROM item_template i LEFT JOIN locales_item li ON li.entry=i.entry WHERE i.entry IN ('.$in.')'; $st=$this->world->prepare($sql); foreach($remain as $i=>$v){ $st->bindValue($i+1,$v,PDO::PARAM_INT);} $st->execute(); while($r=$st->fetch(PDO::FETCH_ASSOC)){ $nm=trim((string)$r['name_cn']); if($nm!==''){ $out[(int)$r['entry']]=$nm; $this->itemNameCache[(int)$r['entry']]=$nm; } } }catch(\Throwable $e){} }
        if($remain){ $this->persistItemNameCache(); }
        return $out;
    }

    private function loadItemNameCache(): void
    { if(!is_file($this->itemNameCacheFile)) return; $json=@file_get_contents($this->itemNameCacheFile); if(!$json) return; $data=json_decode($json,true); if(is_array($data)) $this->itemNameCache=$data; }
    private function persistItemNameCache(): void
    { if(!$this->itemNameCache) return; if(count($this->itemNameCache)>5000){ $this->itemNameCache=array_slice($this->itemNameCache,-4000,null,true); } $dir=dirname($this->itemNameCacheFile); if(!is_dir($dir)) @mkdir($dir,0777,true); @file_put_contents($this->itemNameCacheFile,json_encode($this->itemNameCache,JSON_UNESCAPED_UNICODE)); }


    private function logsDir(): string
    { $dir=dirname(__DIR__,3).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs'; if(!is_dir($dir)) @mkdir($dir,0777,true); return $dir; }
    private function currentUser(): string
    { return $_SESSION['admin_user'] ?? ($_SESSION['username'] ?? 'unknown'); }
    private function appendSqlLog(string $type,bool $ok,int $affected,string $sql,string $error): void
    { $file=$this->logsDir().DIRECTORY_SEPARATOR.'mail_sql.log'; $user=$this->currentUser(); $line=sprintf('[%s]|%s|%s|%s|%d|%s|%s|%d',date('Y-m-d H:i:s'),$user,$type,$ok?'OK':'FAIL',$affected,str_replace(["\r","\n"],' ',$sql),$ok?'':$error,$this->serverId); @file_put_contents($file,$line.PHP_EOL,FILE_APPEND); }
    private function appendDeletedLog(string $action,int $id,string $sql): void
    { $file=$this->logsDir().DIRECTORY_SEPARATOR.'mail_deleted.log'; $user=$this->currentUser(); $line=sprintf('[%s]|%s|%s|%d|%s|%d',date('Y-m-d H:i:s'),$user,$action,$id,$sql,$this->serverId); @file_put_contents($file,$line.PHP_EOL,FILE_APPEND); }

    private function readLogTail(string $file,int $limit): array
    {
        $size = @filesize($file);
        if($size === false) return [];
        if($size < 1048576){
            $raw = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            return array_slice($raw, -$limit);
        }
        $fp = @fopen($file,'r');
        if(!$fp) return [];
        $chunk=''; $pos=$size; $lines=[]; $need=$limit+5;
        while($pos>0 && count($lines)<$need){
            $read = min(8192,$pos); $pos -= $read; fseek($fp,$pos);
            $chunk = fread($fp,$read).$chunk;
            $parts = explode("\n", $chunk);
            if($pos>0){
                $chunk = array_shift($parts);
            } else {
                $chunk='';
            }
            $parts = array_reverse(array_filter($parts, fn($p)=>$p!==''));
            foreach($parts as $p){
                $lines[] = $p;
                if(count($lines) >= $limit) break 2;
            }
        }
        fclose($fp);
        return array_reverse($lines);
    }

    private function parseLogLine(string $type,string $line): ?array
    {
        $parts = explode('|',$line);
        if(!$parts) return null;
        $ts='';
        if(isset($parts[0]) && preg_match('/^\[(.*?)\]$/',$parts[0],$m)) $ts=$m[1];
        if($type==='sql'){
            return [
                'time'=>$ts,
                'user'=>$parts[1]??'',
                'op'=>$parts[2]??'',
                'status'=>$parts[3]??'',
                'affected'=>(int)($parts[4]??0),
                'sql'=>$parts[5]??'',
                'error'=>$parts[6]??'',
                'server'=>$this->parseServerId($parts[7]??'0')
            ];
        }
        return [
            'time'=>$ts,
            'user'=>$parts[1]??'',
            'action'=>$parts[2]??'',
            'id'=>(int)($parts[3]??0),
            'snapshot'=>$parts[4]??'',
            'server'=>$this->parseServerId($parts[5]??'0')
        ];
    }

    private function formatLogEntry(string $type,array $entry): string
    {
        $server = $entry['server'] ?? 0;
        $srvTag = $server ? ('S'.$server) : '-';
        if($type==='sql'){
            $err = trim((string)($entry['error'] ?? ''));
            $sql = $entry['sql'] ?? '';
            $base = Lang::get('app.mail.tail_log.sql_entry', [
                'time' => $entry['time'] ?? '-',
                'server' => $srvTag,
                'user' => $entry['user'] ?? '-',
                'operation' => strtoupper((string)($entry['op'] ?? 'UNKNOWN')),
                'status' => $entry['status'] ?? '',
                'affected' => (int)($entry['affected'] ?? 0),
            ]);
            if($sql !== ''){
                $base .= Lang::get('app.mail.tail_log.sql_suffix', ['sql' => $sql]);
            }
            if($err !== ''){
                $base .= Lang::get('app.mail.tail_log.sql_error_suffix', ['error' => $err]);
            }
            return $base;
        }
        $snap = $entry['snapshot'] ?? '';
        $base = Lang::get('app.mail.tail_log.action_entry', [
            'time' => $entry['time'] ?? '-',
            'server' => $srvTag,
            'user' => $entry['user'] ?? '-',
            'action' => $entry['action'] ?? 'ACTION',
            'id' => (int)($entry['id'] ?? 0),
        ]);
        if($snap !== ''){
            $base .= Lang::get('app.mail.tail_log.action_snapshot_suffix', ['snapshot' => $snap]);
        }
        return $base;
    }

    private function parseServerId(string $raw): int
    {
        if($raw === '') return 0;
        if($raw[0]==='s' || $raw[0]==='S') $raw = substr($raw,1);
        return (int)$raw;
    }
}

