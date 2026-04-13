<?php

return [
  'not_installed_redirect' => '系统尚未完成安装，正在跳转到安装向导……',
  'bootstrap' => [
    'auto_detect_base_path' => '已自动检测 base_path = :base，将在安装完成后写入配置。',
    'base_path_mismatch' => '当前访问前缀“:detected”与配置 base_path“:configured”不一致，请检查部署或更新配置。',
    'normalized_path' => '已规范化访问路径，请直接使用 :target 作为面板入口。',
    'auto_write_base_path' => '已自动写入 base_path = :base 到 config/generated/app.php。',
  ],
];