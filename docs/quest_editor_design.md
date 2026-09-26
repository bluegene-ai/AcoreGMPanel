# Quest Editor Design

覆盖多表任务编辑（`quest_template` + 子表），带一致性 UX 与事务化保存。

## Functional Scope (MVP)

1. **Quest core (`quest_template`)** – 全部列，按 general / progression / flags / rewards / internal 分组。
2. **Quest addon (`quest_template_addon`)** – 按 `ID` 关联的增删改。
3. **Narrative blocks** – `quest_details`、`quest_request_items`、`quest_offer_reward`（文本、emote、cinematic ID）。
4. **Objectives** – `quest_objectives` 多行 CRUD（type/asset/amount/special data），保证编号连续。
5. **Rewards** – `quest_reward_choice_item`、`quest_reward_item`、`quest_reward_currency` 与声望奖励，按 Keira3 分组（choice / fixed / currency·honor·talents）。
6. **Starters / Enders** – creature/gameobject/item 的 starter & ender 关联表，提供搜索/挂接 UI。
7. **Locales** – 先支持 zhCN + enUS，其余 locale 复用同一组件。
8. **Validation aides** – 必填缺失、类型不匹配、跨表一致性（例如奖励物品必须存在）的行内警告。
9. **Logging & audit** – 复用各表已有的 SQL 日志记录器，附加任务上下文。

## Backend Architecture

### Layering

- **`QuestAggregateService`**
  - 协调各表 repository；在 world 库上开事务。
  - `load(int $id): QuestAggregateDTO`
  - `save(QuestAggregateDTO $payload, string $expectedHash): SaveResult`

- **Repositories**
  - `QuestTemplateRepository` – `quest_template` CRUD + 搜索（`QuestRepository` 拆出）。
  - `QuestAddonRepository` – `quest_template_addon` 单行访问。
  - `QuestNarrativeRepository` – details/request/offer 三表。
  - `QuestObjectiveRepository` – 按行操作，自增 `ID`（guid）与 `QuestID`。
  - `QuestRewardRepository` – choice/fixed/currency/reputation 集合。
  - `QuestRelationRepository` – creature/gameobject/item 的 starter/ender 表。
  - `QuestLocaleRepository` – locale 表（按 locale 可选）。

每个 repository 暴露 `fetch(int $questId): array`、`persist(int $questId, array $payload, PDO $tx)` 以复用事务。

### DTO Shape

```json
{
  "template": { "ID": 1, "LogTitle": "...", ... },
  "addon": { "ID": 1, "MaxLevel": 80, ... } | null,
  "narrative": {
    "details": { "Emote1": 1, "EmoteDelay1": 0, ... },
    "request": { "EmoteonComplete": 0, ... },
    "offer": { "EmoteonAccept": 0, ... }
  },
  "objectives": [
    { "ID": 1000, "QuestID": 1, "Type": 0, "Index": 0, "ItemID": 0, ... }
  ],
  "rewards": {
    "choice_items": [...],
    "items": [...],
    "currencies": [...],
    "reputations": [...],
    "monetary": { "Honor": 0, "Arena": 0, "Money": 0 }
  },
  "relations": {
    "starters": {"creatures": [123], "gameobjects": [...], "items": [...]},
    "enders": { ... }
  },
  "locales": { "zhCN": {...}, "enUS": {...} }
}
```

Hashing：对所有读取的表算一个复合 hash，用于乐观锁（`expectedHash`）。

### API Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/quest/api/editor/load` (`id` query) | Load aggregate DTO |
| `POST` | `/quest/api/editor/preview` | Validate payload, return diff summary without saving |
| `POST` | `/quest/api/editor/save` | Persist payload transactionally (requires CSRF token) |
| `POST` | `/quest/api/editor/delete-objective` | Optional targeted deletions if not handled via bulk save |
| `GET` | `/quest/api/lookup/{type}` | Reuse for creature/item search (wrap existing search endpoints or provide thin proxies) |

索引列表/搜索仍走原有 `/quest` 端点。

### Transactions & Logging

- 在选定的 world 库上开事务。
- 按固定顺序落库：template → addon → narrative（三表）→ objectives → rewards → relations → locales。
- 出错回滚并返回结构化校验信息。
- 审计记录变更表与条数。
- 每表追加 SQL 快照到 `quest_sql.log`（非 template 表带 JSON payload）。

### Validation Rules (non-exhaustive)

- 同一任务内 objective `Index` 唯一（0–4）。
- 奖励 choice 数量 ≤ 6。
- 关系 ID 必须指向已存在的条目（可通过 lookup 端点做软校验 + 警告）。
- locale 字符串字段必须与基础表列对齐。

## Frontend Architecture

### Overall Layout

- 用**五步向导**取代现有的自由 Tab：General（template core）→ Rewards（template + 奖励表）→
  Objectives（`quest_objectives` 列表）→ Narrative（details/request/offer 文本与 emote）→ Relations & Locales。
- 保留侧边差异摘要，扩展到标出表/分区。
- 顶部状态条显示校验错误。

### State Management

- `QuestEditorCore` 支持嵌套实体：
  - `state.original` / `state.current` 结构化对象。
  - 差异按表 + 按行记录（`dirtyMap` 分层：`{template:{field:{old,new}}, objectives:{id:{field...}}, ...}`）。
  - `Core.setField(path, value)`，`path` 为点号路径（如 `template.LogTitle`、`objectives[2].ItemId`）。
  - `Core.addRow('objectives', data)` / `Core.deleteRow`。
  - SQL 预览由各表的专用 builder 生成，输出多语句脚本。

### UI Components

- **General tab**：按扩展元数据（`config/quest.php`）渲染动态表单。
- **Rewards tab**：choice/fixed/currency/reputation 表格编辑器 + 增删行 + 快速物品查询弹窗。
- **Objectives tab**：数据网格 + 行表单，支持排序（拖拽或 index 字段）。
- **Narrative tab**：textarea + 预览开关，emote 用下拉枚举。
- **Relations tab**：creature/gameobject/item 搜索选择器（AJAX 复用已有模块），可移除。
- **Locales tab**（可与 relations 合并）：按 locale 折叠面板。

### Reuse & Utilities

- 复用 `Panel.api`。
- 新增 `QuestLookupModal` JS 模块做物品/生物搜索（包装 item/creature 模块的列表端点）。
- 新增 `QuestValidator` 模块做保存前客户端校验。

### Save Workflow

1. 点击 **Save**。
2. 客户端校验通过后带 `expectedHash` POST 到 `/quest/api/editor/save`。
3. 成功后后端返回刷新后的 DTO + 新 hash，`Core.rebaseline` 更新状态。
4. SQL 预览区加注释 `-- Saved @ timestamp`。
5. hash 不匹配（冲突）时弹出服务端 vs 本地差异对比。

### SQL Preview

- 按表拼多语句脚本（按需 INSERT/UPDATE/DELETE）。
- 用注释标出分区（如 `-- quest_template`、`-- quest_objectives`）。
- 支持整段或按分区复制。

## Data Contracts

### Load Response

```json
{
  "success": true,
  "hash": "c044...",
  "quest": { /* QuestAggregateDTO as described */ },
  "lookups": {
    "enums": {...},
    "bitmasks": {...},
    "emotes": [...],
    "currencies": [...]
  }
}
```

### Save Request

```json
{
  "id": 42,
  "expectedHash": "c044...",
  "payload": { /* diff or full dataset */ },
  "mode": "diff" | "full"
}
```

### Save Response

```json
{
  "success": true,
  "hash": "new-hash",
  "quest": { /* refreshed aggregate */ },
  "stats": { "template": "update", "addon": "upsert", "objectives": {"updated":2,"created":1,"deleted":1} }
}
```

## Assumptions

- **Locale coverage**：先 enUS / zhCN。
- **Lookup modals**：复用现有 creature/item 编辑器，不重复实现搜索 SQL。
- **Undo history**：按字段/行保留，需考虑内存上限。
- **Permissions**：复用现有任务 ACL。
- **Testing**：用 CLI harness（`cli/verify.php`）断言 repository 事务。
