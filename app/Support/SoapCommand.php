<?php
/**
 * File: app/Support/SoapCommand.php
 * Purpose: SOAP 命令参数的统一转义与校验入口。
 */

declare(strict_types=1);

namespace Acme\Panel\Support;

/**
 * 面板把玩家可控值拼进 SOAP 命令（.kick / .send mail / .send money / .announce）。
 *
 * 核心按空格分词（COMMAND_DELIMITER = ' '），所以拼错引号不会注入出第二条命令，
 * 但会让参数错位——例如借引号闭合给邮件多塞一件附赠物品。所有拼接点都必须经过这里，
 * 不要再手写 sprintf 拼引号。
 */
final class SoapCommand
{
    /**
     * 双引号参数。
     *
     * 先去掉 CR/LF/NUL：换行虽不能分隔命令，但会污染消息体、审计日志与后续解析；
     * 再转义反斜杠与双引号，避免提前闭合引号。
     */
    public static function quoted(string $value): string
    {
        $value = str_replace(["\r", "\n", "\0"], ' ', $value);

        return addcslashes($value, '"\\');
    }

    /**
     * 裸参数（角色名）。
     *
     * 这里刻意**不做转义**而做白名单校验：转义后的名字核心会当成不存在的角色，
     * 静默失败比报错更难排查。返回 null 表示不能进命令，调用方必须拒绝该请求。
     */
    public static function characterName(string $name): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return preg_match('/^\p{L}{2,16}$/u', $name) === 1 ? $name : null;
    }

    /** 广播/公告正文：核心把整行当消息，只去控制字符，其余原样保留。 */
    public static function text(string $value): string
    {
        return str_replace(["\r", "\n", "\0"], ' ', $value);
    }
}
