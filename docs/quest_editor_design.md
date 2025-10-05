# Quest Editor Refactor Design

*Objective: deliver a Keira3-level quest editor inside the panel, covering multi-table quest authoring with coherent UX and transactional backend saves.*

## Functional Scope (MVP)

1. **Quest core (`quest_template`)** – expose all columns, grouped into digestible sections (general, progression, flags, rewards, internal).
2. **Quest addon (`quest_template_addon`)** – support create/update/delete record linked by `ID`.
3. **Narrative blocks** – manage `quest_details`, `quest_request_items`, and `quest_offer_reward` (text fields, emotes, cinematic IDs).
4. **Objectives** – CRUD for `quest_objectives` (multiple rows with type/asset/amount/special data) and ensure numbering continuity.
5. **Rewards** – manage `quest_reward_choice_item`, `quest_reward_item`, `quest_reward_currency`, and reputation gains. Mirror Keira3 grouping (choice rewards vs. guaranteed vs. currency/honor/talents).
6. **Starters / Enders** – edit relations in creature/gameobject/item starter & ender tables; provide quick search/attach UI.
7. **Locales** – allow editing simplified locale strings (initially zhCN + enUS). Additional locales can hook into same components later.
8. **Validation aides** – inline warnings for missing required fields, type mismatch, and cross-table consistency (e.g., reward items referencing existing entries).
9. **Logging & audit** – reuse existing SQL log appenders per table, with quest-specific context.

## Backend Architecture

### Layering

- **`QuestAggregateService` (new)**
  - Coordinates repositories per table.
  - `load(int $id): QuestAggregateDTO`
  - `save(QuestAggregateDTO $payload, string $expectedHash): SaveResult`
  - Handles transactions (via PDO `beginTransaction` on world DB).

- **Repositories (new / extended)**
  - `QuestTemplateRepository` (existing `QuestRepository` will be split): responsible for `quest_template` CRUD + search.
  - `QuestAddonRepository` – `quest_template_addon` single-row access.
  - `QuestNarrativeRepository` – wraps details/request/offer tables.
  - `QuestObjectiveRepository` – row-based operations with auto-incremented `ID` (guid) and `QuestID`.
  - `QuestRewardRepository` – manage choice/fixed/currency/reputation sets.
  - `QuestRelationRepository` – starter/ender tables for creatures/gameobjects/items.
  - `QuestLocaleRepository` – locale tables (optional per locale).

Each repository should expose `fetch(int $questId): array`, `persist(int $questId, array $payload, PDO $tx)` to allow transaction reuse.

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

Hashing strategy: compute a composite hash over all fetched tables to enforce optimistic locking (`expectedHash`).

### API Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/quest/api/editor` (`id` query) | Load aggregate DTO |
| `POST` | `/quest/api/editor/preview` | Validate payload, return diff summary without saving |
| `POST` | `/quest/api/editor/save` | Persist payload transactionally (requires CSRF token) |
| `POST` | `/quest/api/editor/delete-objective` | Optional targeted deletions if not handled via bulk save |
| `GET` | `/quest/api/lookup/{type}` | Reuse for creature/item search (wrap existing search endpoints or provide thin proxies) |

Existing list/search endpoints remain unchanged for the index table.

### Transactions & Logging

- Begin a transaction on the selected world database.
- Persist tables in deterministic order: template → addon → narrative (three tables) → objectives → rewards → relations → locales.
- On error, rollback and return structured validation message.
- Record audit entry summarizing changed tables + counts.
- Append SQL snapshots similar to current logger but per-table to `quest_sql.log` with JSON payload for non-template tables.

### Validation Rules (non-exhaustive)

- Enforce unique objective `Index` per quest (0–4).
- Validate reward choice count ≤ 6.
- Ensure relation IDs reference existing entries (optional soft validation w/ warnings using lookup endpoints).
- For locales, ensure string fields align with base table columns.

## Frontend Architecture

### Overall Layout

- Replace current free-form tabs with a **five-step wizard**:
  1. General (template core).
  2. Rewards (template + reward tables).
  3. Objectives (quest_objectives CRUD list).
  4. Narrative (details/request/offer texts & emotes).
  5. Relations & Locales.

- Keep side diff summary; extend to indicate table/section.
- Provide status ribbon for validation errors.

### State Management

- Upgrade `QuestEditorCore` to support nested entities:
  - Store `state.original` / `state.current` as structured object.
  - Track diffs per table + per row (`dirtyMap` becomes hierarchical: `{template:{field:{old,new}}, objectives:{id:{field...}}, ...}`).
  - Provide helpers `Core.setField(path, value)` where `path` is dot-notation (e.g., `template.LogTitle`, `objectives[2].ItemId`).
  - Provide `Core.addRow('objectives', data)` / `Core.deleteRow`
  - Generate SQL preview using dedicated builders for each table; surface as multi-statement script.

### UI Components

- **General tab**: dynamic form from expanded metadata (reuse renderer, extended config file to include all template columns grouped logically).
- **Rewards tab**: table editors for choice/fixed/currency/reputation; include add/remove row buttons and quick item lookup modal.
- **Objectives tab**: data grid with row forms; allow reordering (drag-drop or index field).
- **Narrative tab**: textareas with preview toggles; include emote selectors using dropdown enumerations.
- **Relations tab**: search pickers for creatures/gameobjects/items (AJAX to existing modules) with ability to remove.
- **Locales tab** (maybe share with relations step or separate) with collapsible panels per locale.

### Reuse & Utilities

- Share existing `Panel.api` helper.
- Introduce `QuestLookupModal` JS module for item/creature search (can wrap item/creature modules list endpoints).
- Introduce `QuestValidator` module to run client-side checks before save.

### Save Workflow

1. User clicks **Save** (new button).
2. Client validates; if pass, send payload to `/quest/api/editor/save` with `expectedHash`.
3. On success, backend returns refreshed DTO + new hash; `Core.rebaseline` updates state.
4. SQL preview area updates with comment `-- Saved @ timestamp`.
5. On conflict (hash mismatch), show diff overlay comparing server vs local.

### SQL Preview

- Compose multi-statement script per table (INSERT/UPDATE/DELETE as needed).
- Label sections with comments (e.g., `-- quest_template`, `-- quest_objectives`).
- Offer copy-to-clipboard for entire script or per section.

## Data Contracts (Draft)

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

## Open Items / Assumptions

- **Locale coverage**: start with minimal locale set (enUS, zhCN) until more are requested.
- **Lookup modals**: reuse existing creature/item editors for search; avoid duplicating search SQL.
- **Undo history**: maintain per-field/row; consider capping to avoid memory bloat.
- **Permissions**: re-use existing quest ACL; no extra roles assumed.
- **Testing**: add integration coverage via CLI harness (`cli/verify.php`) to assert repository transactions.

## Milestones

1. Schema mapping & metadata（扩展 `config/quest.php` 中 `fields` / `metadata` 配置，按需追加 objectives/rewards 结构）。
2. Backend services & DTO serialization.
3. API endpoints + routing adjustments (with CSRF protection).
4. Frontend state core upgrade + new UI skeleton.
5. Feature parity for template/addon/narrative/objective/reward.
6. Relations & locale editors.
7. QA, docs, polish.

---

*Document owner: quest-editor refactor team. Keep updated as implementation details evolve.*
