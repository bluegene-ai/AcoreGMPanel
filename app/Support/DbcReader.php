<?php
/**
 * File: app/Support/DbcReader.php
 * Purpose: Minimal read-only reader for Blizzard WDBC files (used to resolve
 *          faction / skill / spell / achievement names offline).
 *
 * WDBC 布局：
 *   头部 20 字节：magic "WDBC" + records(uint32) + fields(uint32)
 *                 + recordSize(uint32) + stringBlockSize(uint32)
 *   记录区：records * recordSize，每条记录是 fields 个 uint32
 *   字符串块：recordSize 里取到的偏移量指向这里的以 \0 结尾的字符串
 *
 * 只做只读解析，不依赖任何扩展；文件不可用时返回 null，调用方回退显示原始 ID。
 */

declare(strict_types=1);

namespace Acme\Panel\Support;

final class DbcReader
{
    private string $data;
    private string $strings;

    private function __construct(
        private int $records,
        private int $fields,
        private int $recordSize,
        private int $stringSize,
        string $data,
        string $strings
    ) {
        $this->data = $data;
        $this->strings = $strings;
    }

    public static function open(string $path): ?self
    {
        $raw = @file_get_contents($path);
        if ($raw === false || strlen($raw) < 20) {
            return null;
        }

        // 注意：unpack 的字段名必须与后面读取的键一致（WDBC 头部第 4/5 个字段是
        // recordSize / stringSize），写错名字会静默得到 null
        $header = unpack('a4magic/Vrecords/Vfields/Vrecsize/Vstrsize', substr($raw, 0, 20));
        if ($header === false || ($header['magic'] ?? '') !== 'WDBC') {
            return null;
        }

        $records = (int) $header['records'];
        $fields = (int) $header['fields'];
        $recordSize = (int) $header['recsize'];
        $stringSize = (int) $header['strsize'];

        if ($records <= 0 || $fields <= 0 || $recordSize <= 0 || $stringSize <= 0) {
            return null;
        }

        // 记录的字段宽度必须能容下 fields 个 uint32
        if ($recordSize < $fields * 4) {
            return null;
        }

        $dataLength = $records * $recordSize;
        if (strlen($raw) < 20 + $dataLength + $stringSize) {
            return null;
        }

        return new self(
            $records,
            $fields,
            $recordSize,
            $stringSize,
            substr($raw, 20, $dataLength),
            substr($raw, 20 + $dataLength, $stringSize)
        );
    }

    public function recordCount(): int
    {
        return $this->records;
    }

    public function fieldCount(): int
    {
        return $this->fields;
    }

    /**
     * 按需读取记录，只解出 $fieldIndexes 指定的字段，避免为 5 万条记录生成完整数组。
     *
     * @param int[] $fieldIndexes
     * @return iterable<int, array<int, int>> 每条记录返回 fieldIndex => uint32 值
     */
    public function records(array $fieldIndexes): iterable
    {
        $wanted = [];
        foreach ($fieldIndexes as $index) {
            $index = (int) $index;
            if ($index >= 0 && $index < $this->fields) {
                $wanted[$index] = true;
            }
        }

        if ($wanted === []) {
            return;
        }

        for ($record = 0; $record < $this->records; $record++) {
            $base = $record * $this->recordSize;
            $out = [];
            foreach (array_keys($wanted) as $index) {
                $value = unpack('V', substr($this->data, $base + $index * 4, 4));
                $out[$index] = (int) ($value[1] ?? 0);
            }

            yield $record => $out;
        }    }

    /**
     * 从字符串块读取以 \0 结尾的字符串。
     */
    public function string(int $offset): ?string
    {
        if ($offset <= 0 || $offset >= $this->stringSize) {
            return null;
        }

        $end = strpos($this->strings, "\0", $offset);
        if ($end === false) {
            return null;
        }

        $value = trim(substr($this->strings, $offset, $end - $offset));

        return $value === '' ? null : $value;
    }
}
