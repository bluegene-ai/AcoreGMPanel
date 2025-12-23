# Acore GM Panel

Acore GM Panel 是针对 [AzerothCore](https://www.azerothcore.org/) 服务器打造的现代化 MVC 管理后台，覆盖日常 GM / 管理员的高频操作。项目提供统一的界面风格、模块化的功能设计以及多服务端协同能力，帮助团队安全高效地维护服务器。

## 核心特性

- **模块化架构**：功能按业务域划分（账号、物品、生物、任务、邮件、群发、背包查询、物品归属、SmartAI、SOAP），共享中间件与 UI 组件。
- **多服务器支持**：支持在界面中切换 Realm，针对每个 Realm 使用独立的数据库与 SOAP 凭据，默认规则可复用主服认证信息。
- **默认安全**：内置 CSRF 防护、登录与权限中间件、审计日志、SOAP 白名单策略接口。
- **一致的体验**：统一的布局、设计变量、无框架的轻量组件库以及 `panel.js` 提供的 base-path 感知 API 辅助函数。
- **安装向导**：内置五步安装流程，检查运行环境、收集连接信息、测试可用性、生成配置并写入安装锁。

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
│   ├── Core/             # 路由、语言、请求/响应等基础设施
│   ├── Domain/           # 按业务模块划分的领域逻辑
│   ├── Http/             # 控制器与 HTTP 中间件
│   └── Support/          # 共享工具（认证、审计、SOAP、游戏数据）
├── bootstrap/            # 自动加载与全局 Helper 启动
├── cli/                  # 运维脚本
├── config/               # 配置模板
├── config/generated/     # 安装向导生成的配置
├── public/               # Web 入口及静态资源
├── resources/
│   ├── lang/             # 本地化文件（en、zh_CN）
│   └── views/            # 视图模板与组件
├── routes/               # 路由定义（`web.php`）
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
| 背包查询 | `/bag` | 跨角色物品查询与移除。 |
| 物品归属 | `/item-ownership` | 根据物品定位拥有者、堆叠，并支持批量删除/替换。 |
| SmartAI 向导 | `/smart-ai` | 分步生成 `smart_scripts` SQL 并支持导出。 |
| SOAP 向导 | `/soap` | 浏览 SOAP 命令、填写动态表单、预览并安全执行。 |

## 延伸阅读

更多细化说明位于根目录及 `docs/`：

## 贡献指南

1. Fork 仓库并创建功能分支。
2. 依据模块划分目录：领域逻辑放在 `app/Domain/<Module>`，控制器放在 `app/Http/Controllers/<Module>`。
3. 在提交前运行 PHP 语法检查（`php -l`）及相关测试。
4. 更新中英文翻译：同步修改 `resources/lang/en` 与 `resources/lang/zh_CN`。

## 许可信息

本项目遵循 AzerothCore 社区的使用规范。商业化使用或更多授权问题，请联系维护者确认。


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

注意：`.mmdb` 文件不建议提交到仓库，请手动放置在 `storage/ip_geo/`。
