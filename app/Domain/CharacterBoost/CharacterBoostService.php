<?php

namespace Acme\Panel\Domain\CharacterBoost;

use Acme\Panel\Core\Config;
use Acme\Panel\Domain\Character\CharacterRepository;
use Acme\Panel\Support\SoapService;

class CharacterBoostService
{
    private int $serverId;
    private CharacterRepository $characters;
    private BoostTemplateRepository $templates;

    public function __construct(?int $serverId = null)
    {
        $this->serverId = $serverId ?? \Acme\Panel\Support\ServerContext::currentId();
        $this->characters = new CharacterRepository($this->serverId);
        $this->templates = new BoostTemplateRepository($this->serverId);
    }

    public function rebind(int $serverId): void
    {
        $this->serverId = $serverId;
        $this->characters->rebind($serverId);
        $this->templates->rebind($serverId);
    }

    /**
     * @return array{character:array<string,mixed>,commands:array<int,array<string,mixed>>}
     */
    public function boostByGuid(int $realmId, int $guid, ?int $templateId, ?int $targetLevel, array $operator): array
    {
        $summary = $this->characters->findSummary($guid);
        if (!$summary) {
            throw new CharacterBoostNotFoundException('未找到指定角色。');
        }

        return $this->boostBySummary($realmId, $summary, $templateId, $targetLevel, $operator);
    }

    /**
     * @param array<string,mixed> $summary
     * @return array{character:array<string,mixed>,commands:array<int,array<string,mixed>>}
     */
    public function boostBySummary(int $realmId, array $summary, ?int $templateId, ?int $targetLevel, array $operator): array
    {
        if (empty($summary['guid'])) {
            throw new CharacterBoostNotFoundException('未找到指定角色。');
        }

        $name = (string) ($summary['name'] ?? '');
        $classId = (int) ($summary['class'] ?? 0);
        $previousLevel = (int) ($summary['level'] ?? 0);

        $template = null;
        if ($templateId !== null && $templateId > 0) {
            $template = $this->templates->findForRealm($realmId, $templateId);
            if (!$template) {
                throw new CharacterBoostGuardException('模板无效或不属于当前服务器。');
            }
        }

        $resolvedTargetLevel = $template ? (int) $template['target_level'] : (int) ($targetLevel ?? 0);
        if ($resolvedTargetLevel < 1 || $resolvedTargetLevel > 255) {
            throw new CharacterBoostGuardException('目标等级无效（范围 1-255）。');
        }

        if ($template && $previousLevel >= $resolvedTargetLevel) {
            throw new CharacterBoostGuardException(sprintf(
                '角色当前等级已达到或超过模板目标等级（当前 %d 级，目标 %d 级），无需直升。',
                $previousLevel,
                $resolvedTargetLevel
            ));
        }

        $accountHighestLevel = null;
        if ($template && (int) ($template['require_account_level_match'] ?? 0) === 1) {
            $accountId = (int) ($summary['account'] ?? 0);
            if ($accountId <= 0) {
                throw new CharacterBoostGuardException('该角色所属账号缺失，无法校验等级要求。');
            }

            $accountHighestLevel = $this->characters->accountHighestLevel($accountId);
            $highest = (int) ($accountHighestLevel ?? 0);
            $need = (int) ($template['target_level'] ?? 0);

            if ($highest < $need) {
                throw new CharacterBoostGuardException(sprintf('该账号最高等级仅为 %d 级，未满足模板要求的 %d 级。', $highest, $need));
            }
        }

        $soap = new SoapService($this->serverId);
        $responses = [];

        $levelCmd = sprintf('.character level %s %d', $name, $resolvedTargetLevel);
        $responses[] = [
            'command' => $levelCmd,
            'response' => $soap->execute($levelCmd),
        ];

        if (!$responses[0]['response']['success']) {
            $r0 = is_array($responses[0]['response'] ?? null) ? $responses[0]['response'] : [];
            $detail = $r0['error'] ?? ($r0['message'] ?? null);
            $suffix = $detail ? ('：' . (string) $detail) : '';
            throw new CharacterBoostSoapException('角色等级调整命令执行失败' . $suffix . '。');
        }

        if ($template) {
            $responses = array_merge($responses, $this->dispatchTemplateRewards($soap, $name, $classId, $template));
        }

        return [
            'character' => [
                'guid' => (int) $summary['guid'],
                'name' => $name,
                'class' => $classId,
                'previous_level' => $previousLevel,
                'target_level' => $resolvedTargetLevel,
                'account_highest_level_before' => $accountHighestLevel,
            ],
            'commands' => $responses,
        ];
    }

    private function escapeCommandString(string $value): string
    {
        return addcslashes($value, "\\\"\\");
    }

    private function classGroups(): array
    {
        return (array) Config::get('character_boost.class_groups', []);
    }

    private function tokenSets(): array
    {
        return (array) Config::get('character_boost.token_sets', []);
    }

    private function t2Sets(): array
    {
        return (array) Config::get('character_boost.t2_sets', []);
    }

    private function defaultRewards(): array
    {
        return (array) Config::get('character_boost.default_rewards', []);
    }

    private function resolveBoostTokenGroup(int $classId): string
    {
        $groups = $this->classGroups();
        $group = $groups[$classId] ?? null;

        if (!$group) {
            throw new CharacterBoostSoapException('暂不支持该职业的直升奖励发放，请联系管理员。');
        }

        return (string) $group;
    }

    private function compileAttachmentStrings(array $items): array
    {
        $bucket = [];
        foreach ($items as $item) {
            $entry = (int) ($item['entry'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($entry <= 0 || $quantity <= 0) {
                continue;
            }
            $bucket[$entry] = ($bucket[$entry] ?? 0) + $quantity;
        }

        $out = [];
        foreach ($bucket as $entry => $qty) {
            $out[] = $entry . ':' . $qty;
        }
        return $out;
    }

    private function dispatchDefaultBoostRewards(SoapService $soap, string $characterName, int $targetLevel, string $tokenGroup): array
    {
        $tokenSets = $this->tokenSets();
        $tokens = [];
        foreach ($tokenSets as $set) {
            if (!is_array($set)) {
                continue;
            }
            $t = $set[$tokenGroup] ?? null;
            if ($t) {
                $tokens[] = (int) $t;
            }
        }

        $attachments = array_map(static fn (int $id): string => (string) $id, array_values(array_filter($tokens)));

        $defaults = $this->defaultRewards();
        $bagItem = (int) ($defaults['bag_item'] ?? 21841);
        $bagCount = (int) ($defaults['bag_count'] ?? 4);
        $goldCopper = (int) ($defaults['gold_copper'] ?? 5000000);

        $attachments[] = $bagItem . ':' . $bagCount;

        $subject = sprintf('直升奖励 %d级', $targetLevel);
        $body = sprintf('恭喜 %s 达到 %d 级，已附上 T4 套装兑换物品与补给。', $characterName, $targetLevel);

        $itemCmd = sprintf(
            '.send items %s "%s" "%s" %s',
            $characterName,
            $this->escapeCommandString($subject),
            $this->escapeCommandString($body),
            implode(' ', $attachments)
        );

        $moneyCmd = sprintf(
            '.send money %s "%s" "%s" %d',
            $characterName,
            $this->escapeCommandString($subject),
            $this->escapeCommandString('直升金币奖励'),
            $goldCopper
        );

        return [
            ['command' => $itemCmd, 'response' => $soap->execute($itemCmd)],
            ['command' => $moneyCmd, 'response' => $soap->execute($moneyCmd)],
        ];
    }

    private function dispatchTemplateRewards(SoapService $soap, string $characterName, int $classId, array $template): array
    {
        $responses = [];

        $templateName = (string) ($template['name'] ?? '');
        $targetLevel = (int) ($template['target_level'] ?? 0);

        $subject = sprintf('%s 直升奖励', $templateName);
        $itemBody = sprintf('恭喜 %s 达到 %d 级，已发放模板 %s 奖励物品。', $characterName, $targetLevel, $templateName);
        $moneyBody = sprintf('模板 %s 配置的金币奖励。', $templateName);

        $attachmentItems = [];

        foreach (($template['items'] ?? []) as $it) {
            $entry = (int) ($it['item_entry'] ?? 0);
            $qty = (int) ($it['quantity'] ?? 0);
            if ($entry > 0 && $qty > 0) {
                $attachmentItems[] = ['entry' => $entry, 'quantity' => $qty];
            }
        }

        foreach (($template['class_rewards'] ?? []) as $rw) {
            $tier = strtolower(trim((string) ($rw['tier'] ?? '')));
            $attachmentItems = array_merge($attachmentItems, $this->resolveClassRewardAttachments($classId, $tier));
        }

        $attachments = $this->compileAttachmentStrings($attachmentItems);

        if ($attachments) {
            $itemCmd = sprintf(
                '.send items %s "%s" "%s" %s',
                $characterName,
                $this->escapeCommandString($subject),
                $this->escapeCommandString($itemBody),
                implode(' ', $attachments)
            );
            $responses[] = ['command' => $itemCmd, 'response' => $soap->execute($itemCmd)];
        }

        $moneyCopper = (int) ($template['money_gold'] ?? 0) * 10000;
        if ($moneyCopper > 0) {
            $moneyCmd = sprintf(
                '.send money %s "%s" "%s" %d',
                $characterName,
                $this->escapeCommandString($subject),
                $this->escapeCommandString($moneyBody),
                $moneyCopper
            );
            $responses[] = ['command' => $moneyCmd, 'response' => $soap->execute($moneyCmd)];
        }

        return $responses;
    }

    private function resolveClassRewardAttachments(int $classId, string $tier): array
    {
        return match ($tier) {
            't4' => $this->resolveT4TokenAttachments($classId),
            't2' => $this->resolveT2SetAttachments($classId),
            default => [],
        };
    }

    private function resolveT4TokenAttachments(int $classId): array
    {
        $group = null;
        try {
            $group = $this->resolveBoostTokenGroup($classId);
        } catch (CharacterBoostSoapException) {
            return [];
        }

        $attachments = [];
        foreach ($this->tokenSets() as $set) {
            if (!is_array($set)) {
                continue;
            }
            $entry = $set[$group] ?? null;
            if ($entry) {
                $attachments[] = ['entry' => (int) $entry, 'quantity' => 1];
            }
        }

        return $attachments;
    }

    private function resolveT2SetAttachments(int $classId): array
    {
        $sets = $this->t2Sets();
        $entries = $sets[$classId] ?? [];
        if (!is_array($entries)) {
            return [];
        }

        $attachments = [];
        foreach ($entries as $entry) {
            $v = (int) $entry;
            if ($v > 0) {
                $attachments[] = ['entry' => $v, 'quantity' => 1];
            }
        }

        return $attachments;
    }
}

class CharacterBoostGuardException extends \RuntimeException {}
class CharacterBoostNotFoundException extends \RuntimeException {}
class CharacterBoostSoapException extends \RuntimeException {}
