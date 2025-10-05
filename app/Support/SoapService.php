<?php
/**
 * File: app/Support/SoapService.php
 * Purpose: Defines class SoapService for the app/Support module.
 * Classes:
 *   - SoapService
 * Functions:
 *   - __construct()
 *   - execute()
 */

namespace Acme\Panel\Support;

use Acme\Panel\Core\{Config,Lang};







class SoapService
{
    private string $host; private int $port; private string $user; private string $pass; private string $uri;

    public function __construct()
    {
        $cfg = Config::get('soap');
        $this->host = $cfg['host'] ?? '127.0.0.1';
        $this->port = (int)($cfg['port'] ?? 7878);
        $this->user = $cfg['username'] ?? '';
        $this->pass = $cfg['password'] ?? '';
        $this->uri  = $cfg['uri'] ?? 'urn:AC';
    }

    public function execute(string $command): array
    {
        $endpoint = "http://{$this->host}:{$this->port}/";
        $xml = '<?xml version="1.0" encoding="utf-8"?>'
            .'<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="'.$this->uri.'">'
            .'<SOAP-ENV:Body><ns1:executeCommand><command>'.htmlspecialchars($command,ENT_XML1|ENT_QUOTES,'UTF-8').'</command>'
            .'</ns1:executeCommand></SOAP-ENV:Body></SOAP-ENV:Envelope>';

        $ch = curl_init($endpoint);
        curl_setopt_array($ch,[
            CURLOPT_POST=>true,
            CURLOPT_HTTPHEADER=>[
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: executeCommand'
            ],
            CURLOPT_USERPWD=> $this->user.':'.$this->pass,
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_POSTFIELDS=>$xml,
            CURLOPT_TIMEOUT=>8,
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if($resp === false){
            $fallback = $err !== '' ? $err : Lang::get('app.soap.legacy.errors.curl_error_unknown');
            return [
                'success'=>false,
                'code'=>$code,
                'error'=>$fallback,
                'message'=>Lang::get('app.soap.legacy.errors.curl_failed'),
            ];
        }

        if(preg_match('#<return>(<!\[CDATA\[)?(.*?)(\]\]>)?</return>#s',$resp,$m)){
            $out=trim($m[2]);
        } else {
            $out=trim(strip_tags($resp));
        }
        $ok = ($code>=200 && $code<300);
        $result = ['success'=>$ok,'code'=>$code,'output'=>$out];
        if(!$ok){
            $result['message'] = Lang::get('app.soap.legacy.errors.http_error',['code'=>$code]);
        }
        return $result;
    }
}

