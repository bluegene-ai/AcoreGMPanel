# AzerothCore 数据库结构参考 (Standard References)

本面板依赖 AzerothCore 三大核心数据库：`auth`, `characters`, `world`。
字段语义以官方 Wiki 为准（保持更新）：

- Auth: https://www.azerothcore.org/wiki/database-auth
- Characters: https://www.azerothcore.org/wiki/database-characters
- World: https://www.azerothcore.org/wiki/database-world

请在执行批量修改或升级脚本前先核对上述链接：AzerothCore 随版本升级可能新增/废弃字段。

## 使用约定
- 严禁在业务逻辑中硬编码字段偏移（ordinal index），一律使用字段名。
- 升级 AzerothCore 后若出现列缺失/新增导致面板报错，先查 Wiki 对比差异，再决定：
  1. 代码适配（新增列的默认处理 / 可为空判断）
  2. 数据迁移（执行 ALTER / backfill 脚本）
- 面板的密码修改兼容 `account` 表两种方案：`v`/`s`（`verifier`/`salt`）与 `sha_pass_hash` 双写；GMP 缺失时只写 legacy。
- 兼容性靠运行时探测列（`SHOW COLUMNS` / `INFORMATION_SCHEMA`），不靠版本号分支；探测到旧结构时按缺列路径降级（例如 `item_instance` 的 `owner` / `owner_guid`）。

## 典型表关注点
### auth 数据库
| 表 | 说明 | 面板用途 |
|----|------|----------|
| account | 账号主档 (登录 / SRP) | 搜索、改密、封禁、GM 级别 |
| account_access | 权限级别 (Realm 范围) | 设置/显示 GM level |
| account_banned | 封禁状态 | 列表标记、剩余时间计算 |
| logons / ip_banned | 登录日志 / IP 封禁 | 面板未使用 |

### characters 数据库
| 表 | 说明 | 面板用途 |
|----|------|----------|
| characters | 角色主档 (online 字段) | 在线状态、角色列表 |
| character_inventory | 角色物品槽位 | 物品/库存模块（按角色 / 按物品两轴） |
| item_instance | 具体物品实例 (随机属性等) | 物品/库存模块（堆叠、耐久、增删改） |
| mail / mail_items | 邮件系统 | 邮件中心 / 群发模块 |

### world 数据库
| 表 | 说明 | 面板用途 |
|----|------|----------|
| item_template | 物品定义 | 物品工具读取/编辑 |
| creature_template | 生物定义 | 生物工具读取/编辑 |
| quest_template | 任务定义 | 任务工具读取/编辑（子表与 `*_locale` 见 `quest_editor_design.md`） |
| gameobject_template | 游戏物件 | 面板未使用 |
