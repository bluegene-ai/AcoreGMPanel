# AzerothCore 数据库结构参考 (Standard References)

本面板依赖 AzerothCore 三大核心数据库：`auth`, `characters`, `world`。
以下官方 Wiki 作为结构与字段语义的权威来源（保持更新）：

- Auth: https://www.azerothcore.org/wiki/database-auth
- Characters: https://www.azerothcore.org/wiki/database-characters
- World: https://www.azerothcore.org/wiki/database-world

> 请在执行批量修改或升级脚本前，先核对上述链接最新结构。AzerothCore 随版本升级可能新增/废弃字段。

## 使用约定
- 严禁在业务逻辑中硬编码字段偏移（ordinal index），一律使用字段名。
- 升级 AzerothCore 后，若出现列缺失/新增导致面板报错，优先查阅 Wiki 对比差异，再决定：
  1. 代码适配 (新增列的默认处理 / 可为空判断)
  2. 数据迁移 (执行 ALTER / backfill 脚本)
- 面板的密码修改已实现对 `account` 表多方案兼容：`v/s` (`verifier/salt`) + `sha_pass_hash` 双写；GMP 缺失仅写 legacy。

## 典型表关注点
### auth 数据库
| 表 | 说明 | 面板用途 |
|----|------|----------|
| account | 账号主档 (登录 / SRP) | 搜索、改密、封禁、GM 级别 |
| account_access | 权限级别 (Realm 范围) | 设置/显示 GM level |
| account_banned | 封禁状态 | 列表标记、剩余时间计算 |
| logons / ip_banned (可选) | 登录日志 / IP 封禁 | 未来审计/黑名单 |

### characters 数据库
| 表 | 说明 | 面板用途 |
|----|------|----------|
| characters | 角色主档 (online 字段) | 在线状态、角色列表 |
| character_inventory | 角色物品槽位 | BagQuery 模块物品定位 |
| item_instance | 具体物品实例 (随机属性等) | BagQuery 细节展示 |
| mail / mail_items | 邮件系统 | Mail / MassMail 模块 (计划中) |

### world 数据库
| 表 | 说明 | 面板用途 |
|----|------|----------|
| item_template | 物品定义 | Item 模块读取/编辑 |
| creature_template | 生物定义 | Creature 模块 (计划/进行) |
| quest_template | 任务定义 | Quest 模块 (计划/进行) |
| gameobject_template | 游戏物件 | 后续扩展 |

## 版本与兼容策略
- 推荐在 `config/` 新增一个 `acore_version`（或自动探测 `world.version` 表）用于条件分支，避免硬编码判断。
- 逐步引入 Schema 缓存：`SHOW COLUMNS` 结果缓存 5~10 分钟，减少频繁探测的开销。
- 对于新增字段：默认读取时使用 `SELECT <existing_columns>` 避免 `SELECT *` 造成字段骤增破坏前端解析。

## 诊断建议
添加一个临时诊断页面 (只在开发模式启用)：
- 显示三库连接状态
- 输出关键表 `SHOW COLUMNS` 前 20 行
- 检测必需字段 (account.username / characters.online / item_template.entry)

可后续实现为：`php artisan panel:diag` 风格的 CLI / 或 `/diag` 路由 (受限管理权限)。

---
若你需要我继续：实现 schema 诊断路由 / 添加版本探测 / 自动差异报告，请进一步说明需求。
