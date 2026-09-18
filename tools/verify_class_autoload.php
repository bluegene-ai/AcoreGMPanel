<?php

declare(strict_types=1);

/**
 * 全库扫描：找出"类名与文件名不一致"的类声明。
 *
 * 这是上一轮修掉的前台兑换码缺陷的同类问题 —— 当一个类（尤其是异常类）
 * 被内联写在别的文件末尾时，PSR-4 自动加载器按类名找文件会失败，
 * 于是：
 *   - catch (SomeException $e) 会在异常抛出的瞬间抛 Error（类型无法解析），
 *     把业务错误降级成笼统的 500；
 *   - 或直接 "Class not found" 致命错误。
 *
 * 用法：php tools/verify_class_autoload.php
 * 退出码 1 表示存在内联类。
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$appDir = $root . '/app';
$namespacePrefix = 'Acme\\Panel\\';
$namespaceRoot = $appDir;

$violations = [];
$scanned = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($appDir, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $scanned++;

    $source = (string) file_get_contents($path);

    // 提取命名空间
    if (!preg_match('/^\s*namespace\s+([^;]+);/m', $source, $nsMatch)) {
        continue;
    }
    $namespace = trim($nsMatch[1]);

    // 该命名空间对应的目录
    $relativeDir = str_replace('\\', '/', substr($namespace, strlen($namespacePrefix)));
    $expectedDir = $namespaceRoot . ($relativeDir !== '' ? '/' . $relativeDir : '');
    $expectedDir = str_replace('\\', '/', $expectedDir);
    $actualDir = str_replace('\\', '/', dirname($path));

    $fileName = $file->getBasename('.php');

    // 找出所有顶级类/接口/trait/枚举声明
    preg_match_all(
        '/^(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s+(\w+)/m',
        $source,
        $classMatches
    );

    foreach ($classMatches[1] as $className) {
        $isExpectedFile = ($className === $fileName);
        $isExpectedDir = (strcasecmp($expectedDir, $actualDir) === 0);

        if (!$isExpectedFile || !$isExpectedDir) {
            $violations[] = [
                'class' => $namespace . '\\' . $className,
                'file' => str_replace($root . DIRECTORY_SEPARATOR, '', $path),
                'expected_file' => ($isExpectedDir ? dirname(str_replace($root . DIRECTORY_SEPARATOR, '', $path)) . '/' : '') . $className . '.php',
                'reason' => !$isExpectedFile ? 'class_in_other_file' : 'namespace_dir_mismatch',
            ];
        }
    }
}

echo json_encode([
    'success' => $violations === [],
    'scanned_files' => $scanned,
    'violations' => $violations,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($violations === [] ? 0 : 1);
