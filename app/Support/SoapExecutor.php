<?php
/**
 * File: app/Support/SoapExecutor.php
 * Purpose: Defines class SoapExecutor for the app/Support module.
 * Classes:
 *   - SoapExecutor
 * Functions:
 *   - __construct()
 *   - execute()
 *   - classifyException()
 *   - mapFaultMessage()
 *   - result()
 *   - audit()
 */

namespace Acme\Panel\Support;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Support\Audit;
use Acme\Panel\Support\ServerContext;
use SoapClient;
use SoapFault;
use Throwable;










class SoapExecutor
{
    public const DEFAULT_CONNECT_TIMEOUT = 3;
    public const DEFAULT_TOTAL_TIMEOUT   = 8;


    private array $whitelistPrefixes = [];
    private bool $whitelistEnforced = false;





    public function __construct(array $whitelistPrefixes = [], bool $enforce = false)
    { foreach($whitelistPrefixes as $p){ $p = strtolower(trim($p)); if($p!=='') $this->whitelistPrefixes[$p]=true; }
      $this->whitelistEnforced = $enforce; }





    public function execute(string $command, array $opts = []): array
    {
        $command = trim($command);




        $serverId = $opts['server_id'] ?? null;
        $retries = max(0, (int)($opts['retries'] ?? 1));
        $connectTimeout = (float)($opts['timeout_connect'] ?? self::DEFAULT_CONNECT_TIMEOUT);
        $totalTimeout   = (float)($opts['timeout_total']   ?? self::DEFAULT_TOTAL_TIMEOUT);
        $doAudit = $opts['audit'] ?? true;
        $start = microtime(true);

        if($command===''){
            return $this->result(false,$command,$serverId,$start,'input.empty',Lang::get('app.support.soap_executor.errors.empty_command')); }


        if($this->whitelistEnforced){
            $first = strtolower(strtok($command,' '));
            if(!$first || !isset($this->whitelistPrefixes[$first])){
                return $this->result(false,$command,$serverId,$start,'cmd.disallowed',Lang::get('app.support.soap_executor.errors.not_whitelisted'));
            }
        }



    $serverSoap = null;
    if($serverId!==null){ $serverSoap = ServerContext::server($serverId)['soap'] ?? null; }
    if(!$serverSoap){ $serverSoap = ServerContext::soap(); }
    $cfg = $serverSoap ?: Config::get('soap');
    $host=$cfg['host']??'127.0.0.1'; $port=(int)($cfg['port']??7878); $user=$cfg['username']??''; $pass=$cfg['password']??''; $uri=$cfg['uri']??'urn:AC';

        $attempt=0; $lastError=null; $faultMsg=null; $output='';
        while($attempt <= $retries){
            $attempt++;
            try {
                if(class_exists(SoapClient::class)){
                    $url=sprintf('http://%s:%s@%s:%d/',rawurlencode($user),rawurlencode($pass),$host,$port);
                    $cli = new SoapClient(null,[
                        'location'=>$url,
                        'uri'=>$uri,
                        'style'=>SOAP_RPC,
                        'login'=>$user,
                        'password'=>$pass,
                        'exceptions'=>true,
                        'connection_timeout'=>$connectTimeout,
                    ]);

                    $resp = $cli->__soapCall('executeCommand',[ new \SoapParam($command,'command') ]);
                    $output = is_string($resp)? $resp : var_export($resp,true);
                    $res = $this->result(true,$command,$serverId,$start,'ok',null,['output'=>$output,'retried'=>$attempt-1]);
                    if($doAudit){ $this->audit($res); }
                    return $res;
                } else {

                    $xml = '<?xml version="1.0" encoding="utf-8"?>'
                        .'<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="'.$uri.'">'
                        .'<SOAP-ENV:Body><ns1:executeCommand><command>'.htmlspecialchars($command,ENT_XML1|ENT_QUOTES,'UTF-8').'</command>'
                        .'</ns1:executeCommand></SOAP-ENV:Body></SOAP-ENV:Envelope>';
                    $ctx = stream_context_create([
                        'http'=>[
                            'method'=>'POST','header'=>[
                                'Content-Type: text/xml; charset=utf-8',
                                'SOAPAction: executeCommand',
                                'Authorization: Basic '.base64_encode($user.':'.$pass),
                            ],'content'=>$xml,'timeout'=>$totalTimeout
                        ]
                    ]);
                    $resp = @file_get_contents("http://{$host}:{$port}/", false, $ctx);
                    if($resp===false){ $lastError='net.unreachable'; throw new \RuntimeException(Lang::get('app.support.soap_executor.errors.request_failed')); }
                    if(preg_match('#<return>(<!\[CDATA\[)?(.*?)(\]\]>)?</return>#s',$resp,$m)){ $output=trim($m[2]); }
                    else { $output=trim(strip_tags($resp)); }
                    $res=$this->result(true,$command,$serverId,$start,'ok',null,['output'=>$output,'retried'=>$attempt-1]);
                    if($doAudit){ $this->audit($res); }
                    return $res;
                }
            } catch(SoapFault $sf){
                $faultMsg = $sf->getMessage();
                $code = $this->mapFaultMessage($faultMsg);
                $res = $this->result(false,$command,$serverId,$start,$code,$faultMsg,['fault'=>$faultMsg,'retried'=>$attempt-1]);
                if($doAudit){ $this->audit($res); }
                return $res;
            } catch(Throwable $e){
                $lastError = $this->classifyException($e);
                if($attempt > $retries){
                    $res = $this->result(false,$command,$serverId,$start,$lastError,$e->getMessage(),['retried'=>$attempt-1]);
                    if($doAudit){ $this->audit($res); }
                    return $res;
                }

            }
        }

    $res = $this->result(false,$command,$serverId,$start,$lastError?:'internal.error',Lang::get('app.support.soap_executor.errors.unknown'));
        if($doAudit){ $this->audit($res); }
        return $res;
    }

    private function classifyException(Throwable $e): string
    { $msg=strtolower($e->getMessage());
      if(str_contains($msg,'could not connect')||str_contains($msg,'timed out')||str_contains($msg,'failed to connect')) return 'net.timeout';
      if(str_contains($msg,'unauthorized')||str_contains($msg,'forbidden')) return 'auth.failed';
      return 'internal.exception'; }

    private function mapFaultMessage(string $m): string
    { $lm = strtolower($m);
      if(str_contains($lm,'security level')) return 'auth.permission';
      if(str_contains($lm,'command') && str_contains($lm,'not found')) return 'cmd.not_found';
      return 'soap.fault'; }


    private function result(bool $ok,string $cmd,?int $serverId,float $start,string $code,?string $msg,array $extra=[]): array
    { $base=[ 'success'=>$ok,'command'=>$cmd,'server_id'=>$serverId,'time_ms'=>(int)round((microtime(true)-$start)*1000) ];
      if($ok){ $base += ['output'=>$extra['output']??'','retried'=>$extra['retried']??0]; }
      else { $base += ['code'=>$code,'message'=>$msg??$code,'retried'=>$extra['retried']??0]; if(isset($extra['fault'])) $base['fault']=$extra['fault']; }
      return $base; }

    private function audit(array $res): void
    { try { Audit::log('soap_exec', [
            'cmd'=>mb_substr($res['command'],0,80),
            'success'=>$res['success'],
            'code'=>$res['success']?'ok':($res['code']??'unknown'),
            'ms'=>$res['time_ms'],
            'server'=>$res['server_id'],
            'retried'=>$res['retried']??0
        ]); } catch(\Throwable $e) {  } }
}

