<?php
/**
 * File: resources/views/partials/server_field.php
 * Purpose: 列表筛选表单的 server 隐藏字段。
 *
 * 面板用会话级 ServerContext 记住当前区，任一次表单提交都会改写它 —— 多开标签页时
 * 提交本页筛选会把另一个标签页的区一起切掉。把当前区随表单一起提交，区域选择就只影响
 * 这一次请求。creature / quest / auctionator / raf 的筛选表单早已如此，这里是共用的一份。
 */

$__serverFieldId = isset($current_server)
    ? (int) $current_server
    : \Acme\Panel\Support\ServerContext::currentId();
?>
<?php if ($__serverFieldId > 0): ?>
<input type="hidden" name="server" value="<?= $__serverFieldId ?>">
<?php endif; ?>
