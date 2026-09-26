# Quest Editor Gap Analysis

对照 Keira3 的多表任务编辑能力，记录当前面板已覆盖的范围与仍缺的部分。

## 现状

- **数据范围**：`QuestAggregateService` 在一个事务里读写 `quest_template`、`quest_template_addon`、
  `quest_details` / `quest_request_items` / `quest_offer_reward`、`quest_objectives`、
  `quest_reward_choice_item` / `quest_reward_item` / `quest_reward_currency` / `quest_reward_faction`、
  `creature_queststarter` / `creature_questender` / `gameobject_queststarter` / `gameobject_questender`
  与各 `*_locale` 表；表不存在时按表跳过而不是报错。
- **编辑器字段**：由 `config/quest.php` 的 `fields` / `groups` / `metadata` 白名单驱动。
- **保存**：`/quest/api/editor/preview` 校验并返回差异，`/quest/api/editor/save` 事务化保存，
  用复合 hash 做乐观锁（`expectedHash` 不匹配即拒绝）；旧的单表路径 `/quest/api/save`（`UPDATE quest_template ... LIMIT 1`）仍在。
- **UI**：客户端差异跟踪 + SQL 预览/执行助手 + 有限的位置掩码编辑器。

## Keira3 的能力面（对照目标）

1. **Quest Template (core)** – `quest_template` 全列：XP/金钱缩放、奖励选择包、POI、声望覆盖、计时器、事件标记等。
2. **Quest Template Addon** – 阵营要求、脚本 ID、前置标记、每周重置数据。
3. **Quest Details / Request Items / Offer Reward** – 接取/完成文本、进度文本、emote、镜头/装备展示。
4. **Quest Objectives** – `quest_objectives` 按目标记录（type / asset / amount / description，含特殊法术与物件数据）。
5. **Quest Starter / Ender bindings** – `creature_queststarter`、`creature_questender`、`gameobject_queststarter`、`gameobject_questender` 以及物品起始关系。
6. **Quest Rewards** – 多选奖励包、声望、货币、法术触发（`quest_reward_choice_item`、`quest_reward_item`、`quest_reward_currency` 等）。
7. **Locales** – 以上各表的本地化字符串（`quest_template_locale`、`quest_request_items_locale` …）。
8. **SmartScripts / Conditions 挂钩** – 任务的 `smart_scripts`、`conditions`、`waypoints` 快捷入口或内嵌编辑器。
9. **校验与易用性** – 自动生成 ID、重复检测、必填提示、服务端差异合并、撤销栈。

## 仍缺的部分

| 领域 | 现状 |
| --- | --- |
| 物品起始/结束关系 | `QuestAggregateService::saveRelations()` 只映射 creature/gameobject，`item_queststarter` / `item_questender` 未覆盖 |
| 模板列覆盖 | 只暴露 `config/quest.php` 白名单内的列，未覆盖 `quest_template` 全列（XP 差、honor、POI、失败条件等） |
| 关联系统 | `smart_scripts` / `conditions` / `waypoints` 没有入口，仍需外部手工编辑 |
| 查询端点 | 设计文档中的 `POST /quest/api/editor/delete-objective` 与 `GET /quest/api/lookup/{type}` 未在 `routes/web.php` 注册 |
| 校验与 UX | 位置掩码之外缺少 ID 解析器、搜索弹窗与撤销/重做 |
