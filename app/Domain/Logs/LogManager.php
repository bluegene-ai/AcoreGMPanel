<?php
/**
 * File: app/Domain/Logs/LogManager.php
 * Purpose: Defines class LogManager for the app/Domain/Logs module.
 * Classes:
 *   - LogManager
 * Functions:
 *   - __construct()
 *   - defaults()
 *   - modules()
 *   - getModule()
 *   - getType()
 *   - sanitizeLimit()
 *   - tail()
 *   - loadConfig()
 *   - resolvePath()
 *   - readTail()
 *   - parseLine()
 *   - parseJsonLine()
 *   - parsePipeSql()
 *   - parsePipeDeleted()
 *   - parseMassmail()
 *   - parseItemSql()
 *   - parsePlain()
 *   - summariseArray()
 *   - truncate()
 *   - normalizeServer()
 */

namespace Acme\Panel\Domain\Logs;

use Acme\Panel\Support\ConfigLocalization;
use Acme\Panel\Core\Lang;
use Acme\Panel\Support\LogPath;
use InvalidArgumentException;

class LogManager
{
    private array $modules = [];
    private array $defaults = [
        'module' => 'item',
        'type' => 'sql',
        'limit' => 200,
        'max_limit' => 500,
    ];
    private string $logDir;

    public function __construct(?array $config = null)
    {
        $config = $config ?? $this->loadConfig();
        $this->modules = $config['modules'] ?? [];
        $this->defaults = $config['defaults'] ?? $this->defaults;
        $this->defaults['limit'] = $this->sanitizeLimit((int)($this->defaults['limit'] ?? 200));
        $maxLimit = (int)($this->defaults['max_limit'] ?? 500);
        $this->defaults['max_limit'] = $maxLimit > 0 ? $maxLimit : 500;
        $this->logDir = LogPath::logsDir(true, 0777);
    }

    public function defaults(): array
    {
        return $this->defaults;
    }

    public function modules(): array
    {
        return $this->modules;
    }

    public function getModule(string $id): ?array
    {
        return $this->modules[$id] ?? null;
    }

    public function getType(string $moduleId, string $typeId): ?array
    {
        $module = $this->getModule($moduleId);
        if(!$module){
            return null;
        }
        $type = $module['types'][$typeId] ?? null;
        if(!$type){
            return null;
        }
        return $type + [
            'module_id' => $moduleId,
            'module_label' => $module['label'] ?? $moduleId,
            'type_id' => $typeId,
        ];
    }

    public function sanitizeLimit(int $limit): int
    {
        $limit = $limit > 0 ? $limit : 1;
        $max = (int)($this->defaults['max_limit'] ?? 500);
        if($max <= 0){
            $max = 500;
        }
        return $limit > $max ? $max : $limit;
    }

    public function tail(string $moduleId, string $typeId, int $limit): array
    {
        $type = $this->getType($moduleId, $typeId);
        if(!$type){
            throw new InvalidArgumentException('Unknown module or type');
        }
        $limit = $this->sanitizeLimit($limit);
        $file = $type['file'] ?? '';
        $path = $file !== '' ? $this->logDir.DIRECTORY_SEPARATOR.$file : '';
        $lines = $path && is_file($path) ? $this->readTail($path, $limit) : [];
        $format = $type['format'] ?? 'plain';
        $entries = [];
        foreach($lines as $line){
            $parsed = $this->parseLine($format, $line);
            $entry = ['raw' => $line];
            if($parsed){
                $entry = array_merge($entry, $parsed);
            }
            $entries[] = $entry;
        }
        return [
            'module' => $moduleId,
            'module_label' => $type['module_label'],
            'type' => $typeId,
            'type_label' => $type['label'] ?? $typeId,
            'file' => $file,
            'limit' => $limit,
            'lines' => $lines,
            'entries' => $entries,
        ];
    }

    private function loadConfig(): array
    {
        $file = $this->resolvePath('config/logs.php');
        if(is_file($file)){
            $data = require $file;
            if(is_array($data)){
                return ConfigLocalization::localizeArray($data);
            }
        }
        return [];
    }

    private function resolvePath(string $relative): string
    {
        $base = dirname(__DIR__, 3);
        return $base.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    }

    private function readTail(string $file, int $limit): array
    {
        $size = @filesize($file);
        if($size === false){
            return [];
        }
        if($size <= 1048576){
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if(!$lines){
                return [];
            }
            return array_slice($lines, -$limit);
        }
        $fp = @fopen($file, 'r');
        if(!$fp){
            return [];
        }
        $buffer = '';
        $lines = [];
        $pos = $size;
        while($pos > 0 && count($lines) < $limit){
            $chunk = min(8192, $pos);
            $pos -= $chunk;
            fseek($fp, $pos);
            $buffer = fread($fp, $chunk).$buffer;
            $parts = explode("\n", $buffer);
            if($pos > 0){
                $buffer = array_shift($parts);
            } else {
                $buffer = '';
            }
            for($i = count($parts) - 1; $i >= 0; $i--){
                $line = trim($parts[$i]);
                if($line === ''){
                    continue;
                }
                array_unshift($lines, $line);
                if(count($lines) >= $limit){
                    break 2;
                }
            }
        }
        fclose($fp);
        return $lines;
    }

    private function parseLine(string $format, string $line): ?array
    {
        return match($format){
            'json_line' => $this->parseJsonLine($line),
            'pipe_sql' => $this->parsePipeSql($line),
            'pipe_deleted' => $this->parsePipeDeleted($line),
            'massmail' => $this->parseMassmail($line),
            'item_sql' => $this->parseItemSql($line),
            default => $this->parsePlain($line),
        };
    }

    private function parseJsonLine(string $line): ?array
    {
        if(!preg_match('/^\\[(?P<time>[^\\]]+)\\]\\s*(?P<tag>[^\\s]+)\\s+(?P<payload>\{.*)$/u', $line, $m)){
            return null;
        }
        $data = json_decode($m['payload'], true);
        if(!is_array($data)){
            return null;
        }
        $summary = $this->summariseArray($data, ['message','result','error','sql','action','stage']);
        if($summary === ''){
            $summary = $m['tag'];
        }
        return [
            'time' => $m['time'],
            'tag' => $m['tag'],
            'actor' => $data['admin'] ?? ($data['user'] ?? null),
            'server' => $this->normalizeServer($data['server'] ?? ($data['srv'] ?? null)),
            'summary' => $summary,
            'data' => $data,
        ];
    }

    private function parsePipeSql(string $line): ?array
    {
        if(!preg_match('/^\\[(?P<time>[^\\]]+)\\]\\|(?P<user>[^|]*)\\|(?P<type>[^|]*)\\|(?P<status>[^|]*)\\|(?P<affected>[^|]*)\\|(?P<sql>[^|]*)\\|(?P<error>[^|]*)\\|?(?P<server>.*)$/u', $line, $m)){
            return null;
        }
        $status = strtoupper(trim($m['status'] ?? ''));
        $type = strtoupper(trim($m['type'] ?? ''));
        $affected = (int)($m['affected'] ?? 0);
        $typeLabel = $type !== '' ? $type : 'UNKNOWN';
        $statusLabel = $status !== '' ? $status : 'UNKNOWN';
        $summary = Lang::get('app.logs.manager.pipe_sql.summary', [
            'type' => $typeLabel,
            'status' => $statusLabel,
            'affected' => $affected,
        ]);
        $sql = trim($m['sql'] ?? '');
        if($sql !== ''){
            $summary .= Lang::get('app.logs.manager.pipe_sql.sql_suffix', [
                'sql' => $this->truncate($sql),
            ]);
        }
        $error = trim($m['error'] ?? '');
        if($error !== ''){
            $summary .= Lang::get('app.logs.manager.pipe_sql.error_suffix', [
                'error' => $error,
            ]);
        }
        return [
            'time' => $m['time'],
            'actor' => $m['user'] !== '' ? $m['user'] : null,
            'server' => $this->normalizeServer($m['server'] ?? null),
            'summary' => $summary,
            'data' => [
                'type' => $type,
                'status' => $status,
                'affected' => $affected,
                'sql' => $sql,
                'error' => $error,
            ],
        ];
    }

    private function parsePipeDeleted(string $line): ?array
    {
        if(!preg_match('/^\\[(?P<time>[^\\]]+)\\]\\|(?P<user>[^|]*)\\|(?P<action>[^|]*)\\|(?P<id>[^|]*)\\|(?P<sql>[^|]*)\\|?(?P<server>.*)$/u', $line, $m)){
            return null;
        }
        $action = trim($m['action'] ?? '');
        $summary = ($action !== '' ? $action : 'DELETE').' ID:'.trim($m['id'] ?? '');
        $sql = trim($m['sql'] ?? '');
        if($sql !== ''){
            $summary .= ' | '.$this->truncate($sql);
        }
        return [
            'time' => $m['time'],
            'actor' => $m['user'] !== '' ? $m['user'] : null,
            'server' => $this->normalizeServer($m['server'] ?? null),
            'summary' => $summary,
            'data' => [
                'action' => $action,
                'id' => (int)($m['id'] ?? 0),
                'sql' => $sql,
            ],
        ];
    }

    private function parseMassmail(string $line): ?array
    {
        if(!preg_match('/^\\[(?P<time>[^\\]]+)\\]\\|(?P<body>.*)$/u', $line, $m)){
            return null;
        }
        $parts = explode('|', $m['body']);
        $data = [];
        $notes = [];
        foreach($parts as $part){
            $part = trim($part);
            if($part === ''){
                continue;
            }
            if(strpos($part, ':') !== false){
                [$key, $value] = explode(':', $part, 2);
                $data[$key] = trim($value);
            } else {
                $notes[] = $part;
            }
        }
        $action = $data['action'] ?? ($notes[0] ?? 'action');
        $summaryParts = [];
        $summaryParts[] = $action;
        if(isset($data['succ']) || isset($data['fail'])){
            $summaryParts[] = sprintf('succ:%s fail:%s', $data['succ'] ?? '0', $data['fail'] ?? '0');
        }
        if(isset($data['item']) && $data['item'] !== '0'){
            $summaryParts[] = 'item:'.$data['item'];
        }
        if(isset($data['amount']) && $data['amount'] !== '0'){
            $summaryParts[] = 'amount:'.$data['amount'];
        }
        if($notes){
            $summaryParts[] = implode(' | ', $notes);
        }
        $summary = implode(' | ', array_filter($summaryParts));
        return [
            'time' => $m['time'],
            'actor' => $data['user'] ?? ($data['admin'] ?? null),
            'server' => $this->normalizeServer($data['srv'] ?? null),
            'summary' => $summary,
            'data' => $data + ['notes' => $notes],
        ];
    }

    private function parseItemSql(string $line): ?array
    {
        $jsonParsed = $this->parseJsonLine($line);
        if($jsonParsed){
            return $jsonParsed;
        }
        return $this->parsePipeSql($line);
    }

    private function parsePlain(string $line): ?array
    {
        if(preg_match('/^\\[(?P<time>[^\\]]+)\\]\\s*(?P<message>.*)$/u', $line, $m)){
            return [
                'time' => $m['time'],
                'summary' => trim($m['message']),
                'data' => ['message' => trim($m['message'])],
            ];
        }
        return ['summary' => $line];
    }

    private function summariseArray(array $data, array $preferredKeys): string
    {
        foreach($preferredKeys as $key){
            if(!isset($data[$key])){
                continue;
            }
            $value = $data[$key];
            if(is_scalar($value) && $value !== ''){
                return $this->truncate((string)$value);
            }
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json ? $this->truncate($json) : '';
    }

    private function truncate(string $value, int $max = 160): string
    {
        $value = trim($value);
        if(mb_strlen($value) <= $max){
            return $value;
        }
        return mb_substr($value, 0, $max - 3).'...';
    }

    private function normalizeServer(mixed $server): ?int
    {
        if($server === null || $server === ''){
            return null;
        }
        if(is_numeric($server)){
            return (int)$server;
        }
        if(is_string($server)){
            $server = trim($server);
            if($server === ''){
                return null;
            }
            if($server[0] === 's' || $server[0] === 'S'){
                $server = substr($server, 1);
            }
            if(is_numeric($server)){
                return (int)$server;
            }
        }
        return null;
    }
}

