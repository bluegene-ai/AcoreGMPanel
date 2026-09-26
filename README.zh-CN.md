# Acore GM Panel

针对 [AzerothCore](https://www.azerothcore.org/) 服务器的多区 MVC 管理后台，覆盖日常 GM / 管理员的高频操作。

## 核心特性

- **模块化架构**：功能按业务域划分（账号、物品、生物、任务、邮件、群发、物品/库存、SmartAI、SOAP），共享工具、中间件与 UI 组件。
- **多服务器支持**：每个 Realm 使用独立的数据库与 SOAP 凭据，共享认证信息时有继承规则。
- **默认安全**：内置 CSRF 防护、登录与权限中间件、审计日志、SOAP 白名单策略。
- **一致的体验**：统一的布局、设计变量、可复用组件，以及 `panel.js` 提供的 base-path 感知 API 辅助函数。
- **安装向导**：内置五步安装流程，检查运行环境、收集连接信息、生成配置并写入 `install.lock`。

## 环境要求

| 组件 | 要求 |
|------|------|
| PHP | 8.1 及以上（推荐 8.1/8.2） |
| 扩展 | `pdo_mysql`、`mbstring`、`soap`、`intl`（推荐）、`json`、`openssl` |
| 数据库 | MySQL / MariaDB（兼容 AzerothCore 表结构） |
| Web 服务器 | Apache / Nginx（需支持重写） |
| Composer | 2.x（可选，用于自动加载刷新） |

> 安装向导会在运行时校验 PHP 版本和必需扩展。请确保 CLI 与 Web SAPI 使用同一套 PHP。

## 快速开始

1. **克隆仓库**
  ```bash
  git clone https://github.com/bluegene-ai/AcoreGMPanel.git
  cd AcoreGMPanel
  ```
2. **安装依赖（可选）** – 仅在调整命名空间或需要更新自动加载时运行。
  ```bash
  composer install
  ```
3. **授权可写目录**
  - `storage/`
  - `storage/logs/`
  - `storage/cache/`
  - `storage/ip_geo/`
  - `config/generated/`

4. **配置 Web 服务器**
  - 将站点根指向 `public/`。
  - 启用 URL Rewrite，使所有请求进入 `public/index.php`。
  - 为 PHP 进程赋予以上目录的写权限。

5. **运行安装向导**
  - 浏览器访问站点，若缺少 `install.lock` 会自动重定向到 `/setup`。
  - 依次完成 5 个步骤：环境检查 → 连接信息 → 连通性测试 → 管理员账号 → 生成配置。
  - 完成后将生成 `config/generated/*.php` 和 `config/generated/install.lock`。

6. **登录并体验功能**
  - 使用向导中设置的管理员账号登录。
  - 在导航栏切换 Realm，验证多服务器配置是否生效。

### 手动配置（可选）

若不使用安装向导，可在 `config/generated/` 目录手动创建以下文件并参照 `config/*.php` 填写：

- `config/generated/app.php`
- `config/generated/database.php`
- `config/generated/servers.php`
- `config/generated/soap.php`
- `config/generated/auth.php`

当面板部署在子路径（例如 `/panel`）时，请在 `config/generated/app.php` 中设置 `'base_path' => '/panel'`。所有 `url()`、`asset()` 以及前端 `Panel.api` 都会自动拼接该前缀。

## 目录结构

```
AcoreGMPanel/
├── app/                  # 核心服务、领域逻辑、控制器、中间件
│   ├── Core/             # 路由、语言、请求/响应
│   ├── Domain/           # 按业务模块划分的领域逻辑
│   ├── Http/             # 控制器与 HTTP 中间件
│   └── Support/          # 共享工具（认证、审计、SOAP、游戏数据）
├── bootstrap/            # 自动加载与全局 Helper 启动
├── config/               # 配置模板
├── config/generated/     # 安装向导生成的配置
├── public/               # Web 入口及静态资源
├── resources/
│   ├── lang/             # 本地化文件（en、zh_CN）
│   └── views/            # 视图模板与组件
├── routes/               # 路由定义（`web.php`）
├── scripts/              # 本地辅助脚本（依赖安装）
├── storage/
│   ├── cache/            # 缓存数据（群发名称等）
│   └── logs/             # 模块运行日志
├── docs/                 # 设计文档与模块说明
└── vendor/               # Composer 依赖（可选）
```

## 核心模块

| 模块 | 路径 | 简介 |
|------|------|------|
| 账号管理 | `/account` | 查询账号、GM 等级、封禁状态及关联角色。 |
| 物品工具 | `/item` | 管理 `item_template`，支持差异预览与受限 SQL 执行。 |
| 生物工具 | `/creature` | 编辑生物模板、模型与 SQL 导出。 |
| 任务工具 | `/quest` | 汇总式任务编辑器，带差异与日志。 |
| 邮件中心 | `/mail` | 审查、删除、标记带附件的邮件。 |
| 群发模块 | `/mass-mail` | 批量公告、物品/金币发放与提升预设。 |
| 物品/库存 | `/item-inventory` | 双轴查询：按角色看背包/银行/装备，按物品定位所有拥有者；支持减少堆叠、批量删除与替换。旧地址 `/bag`、`/item-ownership`、`/bag-query` 会 301 重定向到此页。 |
| SmartAI 向导 | `/smart-ai` | 分步生成 `smart_scripts` SQL 并支持导出。 |
| SOAP 向导 | `/soap` | 浏览 SOAP 命令、填写动态表单、预览并安全执行。 |
| 守护管理 | `/supervisor` | 查看并控制 `acore_supervisor.exe`（worldserver / authserver 守护程序）：运行状态、世界循环心跳、登录服探活、重启次数，以及按服务启停/重启。多区部署时页面顶部会出现**实例切换**（见下）。 |
| 活动 Boss | `/boss` | [acore-boss-smartai](https://github.com/bluegene-ai/acore-boss-smartai) 的 `boss.lua` 管理页：运行态卡片（状态、重生倒计时、定时启停状态）、基础配置（身份 / 战斗强度 / 援军与重生 / 刷新点四组，坐标有解析预览）、扩展配置（喊话 / 嘲讽 / AI 节奏 / 阶段阈值 / 巡逻 / 小怪 / 援军 / 职业 / 受管模板 / **技能池随机** / **6 个独立奖池** / **每天时间段自动开关**）、难度档位、事件流水与贡献快照，以及生成 / 击杀 / 重置 / 重载等命令。奖池里填物品ID会即时显示物品名，可按职业过滤，还能**模拟一次击杀**（按与脚本相同的算法算出本轮会发给谁）和**一键复制到其它区**。数据在该区自己的 `ac_eluna` 库里（见下）。 |
| 聊天答题 | `/trivia` | [ac-trivia](https://github.com/bluegene-ai/ac-trivia) 的管理页，分 Tab（实时状态 / 题库 / 奖励预设 / 中奖排行 / 设置）：实时状态（SOAP `.trivia api`）、下一题倒计时、启停与暂停开关、每天**定时启停时间段**、节奏 + 作答频道 + 标号 + 参与门槛 + 播报前缀等设置、题库增删改与 CSV/TSV/JSON 模板导入导出、奖励预设、中奖排行。数据在服务器的 `ac_eluna` 库里（表由 Lua 创建）。 |
| 拍卖机器人 | `/auctionator` | [mod-auctionator](https://github.com/bluegene-ai/mod-auctionator) 的管理页，分 Tab（状态 / 设置 / 物品策略 / 操作）：挂单统计（机器人/玩家、纯竞价/一口价、按拍卖行）、市场数据表状态与模块日志尾部；**在线编辑 `configs/modules/mod_auctionator.conf`**（只改写变化的键，原文件保留为 `.agmp.bak`，需重启 worldserver）；三张策略表（`mod_auctionator_disabled_items`、`mod_auctionator_itemclass_config`、`mod_auctionator_gm_list`）的增删改；本区一键启动/停止；以及模块自带的 GM 命令（经 worldserver SOAP 通道：`.auctionator status`、`addlist`、`expireall`、`enable`/`disable`、`multiplier`、`marketimport`、`marketprune`、`add`、`buyout`、`marketscan`）。**GM 上架要显式指定形态**：列表行与新增表单都要填 一口价 / 竞拍 以及起拍价、买断价，面板发送模块的选项参数形式（`.auctionator add … mode=… bid=… buyout=…`）；因此 `mod_auctionator_gm_list` 需要模块的 `2026_09_24_00_gm_list_mode.sql` 更新，早于它的区会收到明确提示而不是空白列表。**物品筛选与买断开关**：类别/子类白名单按物品类型分组，并有「整类配额」一键操作（补齐进入白名单所需行，或 `max_count = 0` 表示不限制）；品质卡片驱动模块的 `mod_auctionator_quality_config`（`2026_09_24_01_quality_config.sql`）；买断开关会写 `Auctionator.Seller.BidOnly` **并**发送 `.auctionator buyout 0|1`。两套策略表每个卖家周期都会重读，所以筛选项改完即时生效。市场卡片还能**给本区拍卖行定价**（`.auctionator marketscan`）：对在售挂单做一次 SQL 聚合写入单价，设置页可挂定时器。 |

## 守护实例（每个区一个）

一个 `acore_supervisor.exe` 只守护**一个** worldserver + **一个** authserver，因此多区机器上每个区各跑一个守护进程。
在 `config/supervisor.php`（或 `config/generated/supervisor.php`）里用 `instances` 列出它们；上面的所有键都是这些实例的**默认值**：

```php
'instances' => [
    // id => 覆盖项；字符串是 ['dir' => …] 的简写
    'realm-a' => ['label' => '一区', 'dir' => 'D:\AzerothCore\release\supervisor'],
    'realm-b' => [
        'label' => '二区',
        'dir' => 'D:\AzerothCore\release\supervisor-b',
        'allow_start' => true,                 // 每个实例一条计划任务
        'start_task_name' => 'AcoreSupervisorB',
    ],
    // 由上面那些扁平键描述的那个实例（自动探测 release/supervisor）：
    'default' => [],
],
```

- 给实例写了 `dir` 就会**从该目录推导** `exe / status_file / control_file / log_file`，不会再继承扁平的文件路径（否则多个实例会读写同一份状态/指令文件）。
- 页面顶部渲染实例切换按钮（每个按钮带运行状态点），切换后所有 API 都带 `?instance=<id>`；URL 也会同步，刷新后仍是同一个实例。
- **未配置的实例 id 会被 API 拒绝**（404），不会静默落到别的区；审计日志（`panel_audit`）会记录 `instance`。
- 不写 `instances`（或空数组）时只有单一隐式实例 `default`。

## 活动 Boss / 拍卖机器人的按区管理

这两个模块跑在各区自己的 worldserver 里，所以「换区」换的是**整套数据源**：

| | 活动 Boss | 拍卖机器人 |
|---|---|---|
| 数据 | `config/boss.php` → `server_overrides[<区>].custom_db_name`（该区自己的 Eluna 库） | `config/auctionator.php` → `server_overrides[<区>].server_root`（该区 worldserver 目录） |
| 启动 / 停止 | 「生成 Boss」/「击杀」/「重置」→ 只发给该区的 SOAP 端口 | 「模块状态 → 启动/停止本区机器人」→ 写该区 conf 的 `Auctionator.Enabled` 并发送 `.auctionator start`·`stop`，**立即生效且重启后保持** |
| 未部署的区 | `supported_server_ids` 之外 → 页面给只读警告，接口一律 422，绝不误写别的区 | **按区自动判定**（探测该区 world 库有没有 `mod_auctionator_disabled_items`）：装了就能管，没装才只读，且只给一条带区名的说明；`supported_server_ids` / `unsupported_server_ids` 可强制任一结论 |

完整拓扑、加区步骤与验收清单见 `docs/multi-realm.md`。

## 延伸阅读

更多细化说明位于 `docs/`：

- `docs/multi-realm.md` —— 多区（多个 realm 共用一套 auth）部署。
- `docs/DATABASES.md` —— 各数据库/表与面板读写关系。
- `docs/creature_editor.md`、`docs/quest_editor_design.md`、`docs/quest_editor_gap_analysis.md` —— 生物/任务编辑器设计说明。

## IP 归属地（本地库）

面板使用本地 MaxMind `.mmdb` 数据库解析 IP 归属地。

1. 运行时依赖（二选一）：
  - 推荐：随发布包/部署包携带 `vendor/`（服务器无需安装 Composer）。
  - 可选：在服务器安装 PHP 扩展 `maxminddb`（取决于你的 PHP 版本/平台是否有可用构建）。
2. 下载 MaxMind 数据库（推荐 GeoLite2 City），并放到：
  - `storage/ip_geo/GeoLite2-City.mmdb`
3. （可选）在 `config/generated/ip_location.php` 覆盖路径与语言：
  - `mmdb_path`（绝对路径）
  - `locale`（例如 `zh-CN`、`en`）

本地生成 `vendor/`（PowerShell）：
- `powershell -ExecutionPolicy Bypass -File .\scripts\install-deps.ps1`

`.mmdb` 文件不提交到仓库，请手动放置在 `storage/ip_geo/`。

## 贡献指南

1. Fork 仓库并创建功能分支。
2. 依据模块划分目录：领域逻辑放在 `app/Domain/<Module>`，控制器放在 `app/Http/Controllers/<Module>`。
3. 在提交前运行 PHP 语法检查（`php -l`）及相关测试。
4. 更新中英文翻译：同步修改 `resources/lang/en` 与 `resources/lang/zh_CN`。

## 许可信息

本项目遵循 AzerothCore 社区的使用规范。商业化使用或更多授权问题，请联系维护者确认。
