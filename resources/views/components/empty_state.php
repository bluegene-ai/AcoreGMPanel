<?php
/**
 * File: resources/views/components/empty_state.php
 * Purpose: 表格空态行的唯一实现（JS 侧对应 panel.js 的 Panel.emptyRow）。
 *
 * 用法：$colspan 必填，$label 可选（缺省用列表通用的"暂无数据"）。
 * 行上带 js-empty-row：字符/账号列表的客户端过滤器靠这个类判断"表本来是空的"。
 */
$__emptyColspan = max(1, (int) ($colspan ?? 1));
$__emptyLabel = trim((string) ($label ?? '')) !== ''
    ? (string) $label
    : __('app.common.empty.no_data');
$__emptyCellClass = trim((string) ($cell_class ?? '')) !== '' ? (string) $cell_class : 'text-center muted';
?>
<tr class="js-empty-row"><td colspan="<?= $__emptyColspan ?>" class="<?= htmlspecialchars($__emptyCellClass) ?>"><?= htmlspecialchars($__emptyLabel) ?></td></tr>
