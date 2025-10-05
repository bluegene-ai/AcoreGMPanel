# Quest Editor Gap Analysis

> Drafted to guide the refactor toward Keira3-level functionality.

## Current Implementation Snapshot

- **Data scope**: Only `quest_template` is editable, surfaced through ~20 fields defined in `config/quest.php` (`fields` 部分)。The repository exposes a larger whitelist but the UI ignores most columns.
- **UI capabilities**: Single-page tabbed form with client-side diff tracking, SQL preview/execute helpers, and limited bitmask editors.
- **Ancillary tooling**: No integrated lookup helpers (creature/GO selectors, item search, etc.); quest start/end relations, scripts, conditions, and locales are absent.
- **Persistence flow**: Changes produce a single `UPDATE quest_template ... LIMIT 1`; there is no concept of child-table transactions.

## Keira3 Feature Surface (Reference)

The Keira3 quest editor (paired with AzerothCore) spans multiple tables and workflows:

1. **Quest Template (core)** – full coverage of `quest_template` columns, including XP/money scaling, reward choice bundles, POI, reputation overrides, timers, event flags, etc.
2. **Quest Template Addon** – faction requirement, script IDs, prerequisite flags, weekly reset data.
3. **Quest Details / Request Items / Offer Reward** – narrative texts (accept/completion, progress), emotes, camera/equipment, and quest giver/finisher presentations.
4. **Quest Objectives** – per-objective records (`quest_objectives`), including storage for type, asset, amount, and description for up to four objectives plus special spell/object data.
5. **Quest Starter / Ender bindings** – CRUD for relations in `creature_queststarter`, `creature_questender`, `gameobject_queststarter`, `gameobject_questender`, and item starters.
6. **Quest Rewards** – multi-choice reward sets, reputation gains, currency, spell triggers (`quest_reward_choice_item`, `quest_reward_item`, `quest_reward_currency`, etc.).
7. **Locales** – localized strings across the above tables (`quest_template_locale`, `quest_request_items_locale`, ...).
8. **SmartScripts / Conditions hooks** – quick links or in-line editors for `smart_scripts`, `conditions`, and `waypoints` associated with the quest.
9. **Validation aides** – automatic ID generation, duplicate detection, required field prompts, server-side diff merging, undo stacks.

## Identified Gaps

| Domain | Current Panel | Expected (Keira3) | Gap Notes |
| --- | --- | --- | --- |
| Quest template coverage | ~20 fields (basic level/reward/flags) | Full column coverage including XP diff, honor, reputation, POI, fail conditions | Missing ~80% of columns despite repository support |
| Addon table (`quest_template_addon`) | Not surfaced | Editable inline with defaults and toggles | Entire table absent |
| Narrative tables (`quest_details`, `quest_request_items`, `quest_offer_reward`) | Not surfaced | Rich text editors, emote selectors, cinematic toggles | No CRUD or preview |
| Objectives (`quest_objectives`) | Not surfaced | Row-based editor (type/asset/amount/done events) | Need list + row management |
| Starter/Ender bindings | Delete-only via SQL | Visual selectors and batch add/remove | Completely missing UI + API |
| Reward bundles (choice/fixed/currency) | 4 fixed reward items only | Support up to 6 choice items, multiple reward types, reputation bundles | Partial coverage, no currency/honor/talents handling |
| Locales | Not surfaced | Tabbed interface per locale | Need schema + UI strategy |
| Linked systems (conditions, SmartAI, scripts) | External manual edits | Shortcut links or inline editors | Determine scope for MVP |
| Validation & UX | Manual numeric entry | Contextual lookups, ID resolvers, safe defaults, undo | Need pickers, search modals, undo/redo |
| Multi-table transactions | Single-table update | Coordinated save across child tables, with optimistic locking | Requires backend transaction orchestration |

## Immediate Questions for Design

1. **Scope** – which Keira3 components are mandatory for first delivery? (e.g., is locales support required from day one?)
2. **Data access layer** – extend current repository or introduce service layer per quest domain (template/addon/objectives...).
3. **UI architecture** – modular stepper vs. tabbed layout; strategy for lazy-loading heavy datasets (objectives, relations).
4. **Lookups** – reuse existing item/creature search components or build lightweight APIs.
5. **Versioning** – Should edits produce revision logs beyond current SQL diff logging?

---

*This document will evolve alongside the refactor plan. Add follow-up notes as decisions land.*
