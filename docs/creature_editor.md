# Creature Editor 说明

本文件描述生物（creature_template）编辑器的结构、字段配置、差异更新机制与安全策略。

## 架构概览
- 后端控制器：`app/Http/Controllers/Creature/CreatureController.php`
- 数据访问：`app/Domain/Creature/CreatureRepository.php`（多服继承自 `MultiServerRepository`）
- 视图：`resources/views/creature/index.php`, `resources/views/creature/edit.php`
- 前端脚本：`public/assets/js/modules/creature.js`
- 配置汇总：`config/creature.php`

## 字段配置 (`config/creature.php` → `groups`)
采用与物品编辑器相似分组：
- base (基础信息)
- combat (战斗参数)
- vitals (生命/法力/抗性倍率)
- drops (掉落/金币)
- ai (AI 与脚本)
- flags (位字段)

每个分组:
```php
'group_key' => [
  'label' => '显示名称',
  'fields' => [
     ['name'=>'列名','label'=>'标签','type'=>'number|text|textarea','bitmask'=>true?]
  ]
]
```
位字段通过 `bitmask` => true 打标，前端会注入位选择器按钮。

## 位标志与阵营 (`config/creature.php` → `flags` / `factions`)
结构：`字段名 => [ 位 => 描述 ]`。只列出常用常见位，可按需要增补。前端使用 `window.CREATURE_FLAG_CONFIG` 注入页面。

## 差异更新机制
1. 初始渲染时为每个输入元素写入 `data-orig` 属性（原始值）。
2. 前端监听 `input/change`，比较当前值与 `data-orig`，高亮变更字段并收集差异对象。
3. 生成 UPDATE SQL：仅包含发生变更的列；空字符串映射为 `NULL`；附加 `WHERE entry = ? LIMIT 1`。
4. 调用 `/creature/api/save` 时直接发送差异 JSON，后端 `updatePartial()`：
   - 过滤非法列（基于 `CreatureRepository::validColumns()`）
   - 仅更新发生真实数值变化的字段
   - 审计日志记录 `changed` 列表，截断过长值

## 受限 SQL 执行 (`/creature/api/exec-sql`)
- 仅允许单条 `UPDATE creature_template SET ... WHERE entry = <数字>` 或 `INSERT INTO creature_template(...) VALUES(...)`。
- 所有列必须在白名单 `validColumns()` 内。
- UPDATE 额外要求 WHERE 子句为单一等号条件。
- 执行后若为 UPDATE，会再次查询该行返回 `after` 快照用于前端展示。

## 多服务器支持
- 通过 `server` 查询参数及 `ServerContext` 在请求开始时选择对应世界数据库连接。
- 列表与编辑页均在链接中保留 `server` 参数，切换服务器后 `CreatureRepository` 重新实例化。

## 位标志选择器交互
- 点击数字输入后追加的“位”按钮或双击输入触发弹窗。
- 弹窗显示：搜索框 + 全选/清空 + 位列表（位编号 + 描述）。
- 勾选即时重算数值并写回输入，同时触发差异检测。

## 安全策略概述
| 风险面 | 防护措施 |
|--------|----------|
| 非法列更新 | 白名单 `validColumns()` 过滤 |
| 多语句注入 | 正则禁止分号与多语句执行 |
| WHERE 条件绕过 | 强制 `WHERE entry = <数字>` 格式 |
| 大量无效更新 | 前端 diff + 后端二次 diff 验证，无实际变更不执行 UPDATE |
| 操作追踪 | Audit + 独立 SQL / 删除日志文件 (storage/logs/*.log) |

## 日志文件
- `creature_sql.log`：受限 SQL 执行结果（时间|用户|TYPE|OK/FAIL|影响|SQL|错误|server）。
- `creature_deleted.log`：删除或创建时的 Snapshot INSERT 形式记录。

## 可扩展点
- 增加更多 `creature_template` 列到配置文件即可自动出现在 UI。
- 添加子表（addon / equipment / loot）可参照现有模型列表结构复用组件。
- 位描述表可在配置中继续扩充，前端无需改动。
- 可添加只读字段：配置增加 `readonly'=>true` 并在渲染时禁用输入。

## 已知限制 / 待改进
- 字段类型暂未做更丰富的校验（范围、数值依赖关系）。
- 目前没有批量导入 / 批量编辑功能。
- 位标志搜索大小写不敏感，但不支持多关键字匹配。

## 调试技巧
- 如果位面板不显示，检查 `window.CREATURE_FLAG_CONFIG` 是否成功注入。
- 确认服务器切换后 `server` 参数仍存在于返回/分页链接中。
- 遇到保存无效：查看存储日志里是否记录 diff，或检查是否真实值未改变。

---
最后更新：<?= date('Y-m-d H:i:s') ?>
