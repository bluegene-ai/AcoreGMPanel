<?php
/**
 * File: app/Domain/Boss/SkillPrescreenStore.php
 * Purpose: 技能预筛结果的落盘与读取（storage/cache/boss_skill_prescreen.json）。
 *
 * 结果里带 `checked_at` / `file_sha256` / `rule_version`：页面据此说明
 * "这份结论对应哪份文件、哪个规则版本、什么时候算的"。
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\Boss;

use Acme\Panel\Core\Config;
use Throwable;

class SkillPrescreenStore
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $configured = (string) Config::get('boss_prescreen.store_path', 'storage/cache/boss_skill_prescreen.json');
        $resolved = $path ?? $configured;
        if ($resolved === '') {
            $resolved = 'storage/cache/boss_skill_prescreen.json';
        }

        $this->path = $this->isAbsolute($resolved)
            ? $resolved
            : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $resolved);
    }

    public function path(): string
    {
        return $this->path;
    }

    /** @return array<string,mixed>|null */
    public function load(): ?array
    {
        if (!is_file($this->path)) {
            return null;
        }

        $raw = @file_get_contents($this->path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /** 落盘（先写临时文件再改名，避免并发读到半截 JSON）。 */
    public function save(array $payload): bool
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($encoded === false) {
            return false;
        }

        $tmp = $this->path . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $encoded) === false) {
            return false;
        }

        return @rename($tmp, $this->path);
    }

    /**
     * 跑一遍并落盘；解析/IO 失败也落盘（页面要显示失败原因，而不是"0 个问题"）。
     *
     * @return array<string,mixed>
     */
    public function runAndSave(?string $bossLuaPath = null): array
    {
        $prescreen = new SkillPrescreen();
        $payload = $prescreen->run($bossLuaPath);
        $this->save($payload);

        return $payload;
    }

    /**
     * 读取缓存；缓存缺失或规则版本已变时返回 null（页面会提示需要重算）。
     *
     * @return array<string,mixed>|null
     */
    public function loadFresh(): ?array
    {
        $cached = $this->load();
        if ($cached === null) {
            return null;
        }

        try {
            $prescreen = new SkillPrescreen();
            $version = $prescreen->ruleVersion();
        } catch (Throwable $exception) {
            return $cached;
        }

        if ((string) ($cached['rule_version'] ?? '') !== $version) {
            return null;
        }

        return $cached;
    }

    private function isAbsolute(string $path): bool
    {
        return (bool) preg_match('#^([A-Za-z]:[\\\\/]|/|\\\\)#', $path);
    }
}
