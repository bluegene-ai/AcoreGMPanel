<?php

declare(strict_types=1);

namespace Acme\Panel\Domain\Trivia;

/**
 * 题库模板解析/序列化：CSV(或 TSV) 与 JSON 双向。
 *
 * 模板列（表头可选，有表头就按名字认列，没有就按顺序）：
 *   题干, 选项A, 选项B, 选项C, 选项D, 答案, 标号, 奖励预设, 奖励物品, 金钱, 启用
 *
 * 答案支持：1-4 / A-D / 甲-丁 / 选项原文。
 * 以 # 开头的行与空行会被忽略，方便从 Excel 直接粘贴。
 */
final class QuestionTemplate
{
    public const COLUMNS = [
        'question', 'option1', 'option2', 'option3', 'option4',
        'answer', 'labels', 'reward_preset', 'reward_items', 'reward_money', 'enabled',
    ];

    /** 中文/英文表头别名 → 内部列名 */
    private const HEADER_ALIASES = [
        'question' => 'question', '题干' => 'question', '题目' => 'question', '问题' => 'question',
        'option1' => 'option1', '选项a' => 'option1', '选项1' => 'option1', 'a' => 'option1', '选项一' => 'option1',
        'option2' => 'option2', '选项b' => 'option2', '选项2' => 'option2', 'b' => 'option2', '选项二' => 'option2',
        'option3' => 'option3', '选项c' => 'option3', '选项3' => 'option3', 'c' => 'option3', '选项三' => 'option3',
        'option4' => 'option4', '选项d' => 'option4', '选项4' => 'option4', 'd' => 'option4', '选项四' => 'option4',
        'answer' => 'answer', '答案' => 'answer', '正确答案' => 'answer', '正确选项' => 'answer',
        'labels' => 'labels', '标号' => 'labels', '选项标号' => 'labels',
        'reward_preset' => 'reward_preset', '奖励预设' => 'reward_preset', '预设' => 'reward_preset',
        'reward_items' => 'reward_items', '奖励物品' => 'reward_items', '物品' => 'reward_items', '额外物品' => 'reward_items',
        'reward_money' => 'reward_money', '金钱' => 'reward_money', '奖励金钱' => 'reward_money', '金币' => 'reward_money',
        'enabled' => 'enabled', '启用' => 'enabled', '是否启用' => 'enabled', '状态' => 'enabled',
    ];

    /**
     * 解析模板文本。
     *
     * @return array{format:string,rows:array<int,array{line:int,data:array<string,string>}>,errors:array<int,array{line:int,message:string}>}
     */
    public static function parse(string $text): array
    {
        // Excel 另存 CSV 会带 UTF-8 BOM，不剥掉的话表头第一列会变成 "\uFEFF题干" 而认不出来
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        }

        // 非 UTF-8（中文 Windows 上 Excel 的 ANSI/GBK 另存、UTF-16「Unicode 文本」）先转成 UTF-8，
        // 否则中文会整片变成问号/替换字符 —— 面板侧 JS 已经按字节判编码，这里再兜一层，
        // 让直接 POST 原文的调用方（curl / 脚本 / 其他客户端）也能正常导入。
        $text = self::normalizeEncoding($text);

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $trimmed = ltrim($text);

        if ($trimmed !== '' && ($trimmed[0] === '[' || $trimmed[0] === '{')) {
            return self::parseJson($text);
        }

        return self::parseDelimited($text);
    }

    /**
     * 把常见的非 UTF-8 编码统一成 UTF-8。
     *
     * BOM 判断必须在 mb_check_encoding 之前：UTF-16 文本里 ASCII 字符带 NUL 字节，
     * 字节序列本身仍然"是合法 UTF-8"，只看合法性会把它当成 UTF-8 而解码成一堆 NUL。
     */
    private static function normalizeEncoding(string $text): string
    {
        if (str_starts_with($text, "\xFF\xFE")) {
            return self::convertToUtf8(substr($text, 2), 'UTF-16LE');
        }

        if (str_starts_with($text, "\xFE\xFF")) {
            return self::convertToUtf8(substr($text, 2), 'UTF-16BE');
        }

        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        // 中文 Windows 的 ANSI 代码页是 GBK，GB18030 是它的超集（也能吃 GBK 字节）
        return self::convertToUtf8($text, 'GB18030');
    }

    private static function convertToUtf8(string $text, string $from): string
    {
        $converted = @mb_convert_encoding($text, 'UTF-8', $from);

        return is_string($converted) && $converted !== '' ? $converted : $text;
    }

    /**
     * @return array{format:string,rows:array<int,array{line:int,data:array<string,string>}>,errors:array<int,array{line:int,message:string}>}
     */
    private static function parseJson(string $text): array
    {
        $rows = [];
        $errors = [];
        $decoded = json_decode($text, true);

        if (!is_array($decoded)) {
            return [
                'format' => 'json',
                'rows' => [],
                'errors' => [['line' => 0, 'message' => 'JSON 解析失败：' . json_last_error_msg()]],
            ];
        }

        // 允许 {"questions": [...]} 或直接 [...]
        if (isset($decoded['questions']) && is_array($decoded['questions'])) {
            $decoded = $decoded['questions'];
        }

        $index = 0;
        foreach ($decoded as $item) {
            $index++;
            if (!is_array($item)) {
                $errors[] = ['line' => $index, 'message' => 'JSON 第 ' . $index . ' 项不是对象'];
                continue;
            }

            $data = [];
            foreach ($item as $key => $value) {
                $normalizedKey = self::normalizeHeader((string) $key);

                // JSON 里可以直接给一个选项数组：options / 选项 = ["甲","乙","丙","丁"]
                if ($normalizedKey === 'options' || $normalizedKey === '选项') {
                    if (is_array($value)) {
                        $index = 0;
                        foreach ($value as $option) {
                            $index++;
                            if ($index > 4) {
                                break;
                            }
                            $data['option' . $index] = (string) $option;
                        }
                    }
                    continue;
                }

                $column = self::HEADER_ALIASES[$normalizedKey] ?? null;
                if ($column === null) {
                    continue;
                }
                if (is_array($value)) {
                    $value = implode(',', array_map('strval', $value));
                }
                $data[$column] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            }
            $rows[] = ['line' => $index, 'data' => $data];
        }

        return ['format' => 'json', 'rows' => $rows, 'errors' => $errors];
    }

    /**
     * @return array{format:string,rows:array<int,array{line:int,data:array<string,string>}>,errors:array<int,array{line:int,message:string}>}
     */
    private static function parseDelimited(string $text): array
    {
        $lines = explode("\n", $text);
        $delimiter = self::detectDelimiter($text);

        $rows = [];
        $errors = [];
        $columns = null;

        foreach ($lines as $lineNumber => $line) {
            $lineNo = $lineNumber + 1;
            $raw = rtrim($line, "\n");
            $trimmed = trim($raw);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $cells = str_getcsv($raw, $delimiter, '"', '\\');
            $cells = array_map(static fn($v): string => trim((string) $v), $cells);

            if ($columns === null) {
                $mapped = self::mapHeader($cells);
                if ($mapped !== null) {
                    $columns = $mapped;
                    continue; // 表头行不算数据
                }
                $columns = self::COLUMNS; // 没有表头：按固定顺序
            }

            // 全空行跳过（Excel 末尾常有）
            if (implode('', $cells) === '') {
                continue;
            }

            $data = [];
            if (count($cells) > count($columns)) {
                $errors[] = [
                    'line' => $lineNo,
                    'message' => '字段数（' . count($cells) . '）多于表头列数（' . count($columns)
                        . '）：含有逗号的字段（例如 标号"甲,乙,丙,丁"）必须用英文双引号包起来。',
                ];
                continue;
            }

            foreach ($columns as $index => $column) {
                if ($column === null) {
                    continue;
                }
                $data[$column] = $cells[$index] ?? '';
            }

            $rows[] = ['line' => $lineNo, 'data' => $data];
        }

        return ['format' => $delimiter === "\t" ? 'tsv' : 'csv', 'rows' => $rows, 'errors' => $errors];
    }

    /**
     * 表头行 → 列映射；认不出来返回 null（说明这是数据行）
     *
     * @param array<int,string> $cells
     * @return array<int,string|null>|null
     */
    private static function mapHeader(array $cells): ?array
    {
        $mapped = [];
        $recognized = 0;

        foreach ($cells as $cell) {
            $column = self::HEADER_ALIASES[self::normalizeHeader($cell)] ?? null;
            if ($column !== null) {
                $recognized++;
            }
            $mapped[] = $column;
        }

        // 至少要认出"题干"和"答案"这两列，才算表头行
        if ($recognized < 2 || !in_array('question', $mapped, true)) {
            return null;
        }

        return $mapped;
    }

    private static function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = str_replace([' ', '　', '_', '-', '（', '）', '(', ')', '：', ':'], '', $header);

        return $header;
    }

    private static function detectDelimiter(string $text): string
    {
        $firstLines = implode("\n", array_slice(explode("\n", $text), 0, 5));

        $tabs = substr_count($firstLines, "\t");
        $commas = substr_count($firstLines, ',');
        $semicolons = substr_count($firstLines, ';');

        if ($tabs > $commas && $tabs >= $semicolons) {
            return "\t";
        }
        if ($semicolons > $commas) {
            return ';';
        }

        return ',';
    }

    /**
     * 导出为 CSV（带 UTF-8 BOM，Excel 直接双击不乱码）。
     *
     * @param array<int,array<string,mixed>> $rows 题目行（repository 的 normalizeQuestionRow 输出）
     */
    public static function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['题干', '选项A', '选项B', '选项C', '选项D', '答案', '标号', '奖励预设', '奖励物品', '金钱', '启用'], ',', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($handle, [
                (string) ($row['question'] ?? ''),
                (string) ($row['option1'] ?? ''),
                (string) ($row['option2'] ?? ''),
                (string) ($row['option3'] ?? ''),
                (string) ($row['option4'] ?? ''),
                (string) ($row['answer_index'] ?? 1),
                (string) ($row['labels'] ?? ''),
                (string) ($row['reward_preset'] ?? ''),
                (string) ($row['reward_items'] ?? ''),
                (string) ($row['reward_money'] ?? 0),
                !empty($row['enabled']) ? '1' : '0',
            ], ',', '"', '\\');
        }

        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    /**
     * 空白模板（表头 + 2 行示例）。
     */
    public static function sample(): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['题干', '选项A', '选项B', '选项C', '选项D', '答案', '标号', '奖励预设', '奖励物品', '金钱', '启用'], ',', '"', '\\');
        fputcsv($handle, ['巫妖王的本名是谁？', '阿尔萨斯·米奈希尔', '耐奥祖', '克尔苏加德', '伊利丹·怒风', '1', '', 'cloth5', '', '0', '1'], ',', '"', '\\');
        fputcsv($handle, ['暗夜精灵的主城是？', '暴风城', '铁炉堡', '达纳苏斯', '埃索达', '达纳苏斯', '甲,乙,丙,丁', 'gold5', '33470:2', '50000', '1'], ',', '"', '\\');

        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }
}
