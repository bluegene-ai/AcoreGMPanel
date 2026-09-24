# 多区部署（多个 realm 共用一套 auth）

面板本身就按「区服」分区：`config/generated/servers.php` 里的每个条目是一套
`auth / characters / world / soap`，而 `?server=<索引>`（页头的区服下拉框）决定当前页
读写哪一套。本文说明**活动 Boss** 与**拍卖机器人**这两个「跑在 worldserver 里」的模块
在多区下的绑定关系、独立启停方式，以及新增一个区时要改什么。

## 1. 拓扑

```
                        acore_auth（唯一，各区共用）
                                │
      ┌─────────────────────────┼─────────────────────────┐
      │                         │                         │
  <realm-a>\                <realm-b>\                <realm-c>\
  worldserver.exe           worldserver.exe           worldserver.exe
  RealmID / 端口 A           RealmID / 端口 B           RealmID / 端口 C
  <realm-a>_world           <realm-b>_world           <realm-c>_world
  <realm-a>_characters      <realm-b>_characters      <realm-c>_characters
  lua_scripts\boss.lua      lua_scripts\boss.lua      lua_scripts\boss.lua
   └ key = "current"         └ key = "<realm-b>"       └ key = "<realm-c>"
  configs\modules\mod_auctionator.conf（每区一份，互不影响）
  SOAP 端口 A                SOAP 端口 B                SOAP 端口 C

            活动 Boss：四张表都在**同一个** Eluna 库（默认 ac_eluna）里，
                    用 state_key 把这些 key 的数据分开（共用库，不建新库）
```

要点：

- **auth 共用**：账号、登录、realmlist 只有一套，各区在 `realmlist` 里各占一行（端口不同）。
- **world / characters / SOAP / conf 各自一套**：面板的区服下拉框切换的就是它们。
- **活动 Boss：共用一个 Eluna 库 + 每区一个 state_key**。`boss_activity_config` / `_ext` /
  `_runtime` 的主键就是 `state_key`；`boss_activity_events` / `_contributors` 有 `state_key` 列
  （老库由 `boss.lua` 自动补列并建索引，历史行按列的默认值归到主区，**零迁移**）。
  **两个区用同一个 key 就是共用同一份数据**，所以每区一个 key 是硬要求；库名不必不同。
- **拍卖机器人的表在该区的 world/characters 库里**，conf 在该区目录里；模块本身不含
  全局单例，各区天然独立。

## 2. 绑定表（面板侧配置在哪）

| 项 | 面板位置 | 说明 |
|---|---|---|
| 区服清单（名称/端口/库/SOAP） | `config/generated/servers.php` | 安装向导生成，`server` 索引就是面板 URL 里的 `?server=` |
| Boss 本区 key | `config/boss.php` → `server_overrides[].runtime_key` | **必须等于**该区 `boss.lua` §2 的 `BOSS_RUNTIME_KEY` / `BOSS_CONFIG_KEY`；每区必须不同 |
| Boss 本区库 | `config/boss.php` → `server_overrides[].custom_db_name` | 通常各区相同（共用 `ac_eluna`）；只有给某区单独建库时才不同 |
| Boss 支持哪些区 | `config/boss.php` → `supported_server_ids` | 留空 = 全部支持；锁成单区时不在列表里的区直接 422、不发 SOAP |
| 拍卖机器人区服目录 / conf / 日志 | `config/auctionator.php` → `server_overrides` | 键 = server 索引；缺省值在文件顶部的 `server_root` / `conf_file` / `log_file` |
| 拍卖机器人**强制**可管理的区 | `config/auctionator.php` → `supported_server_ids` | 白名单：写进去的区直接算"已部署"，跳过自动探测（快路径） |
| 拍卖机器人**强制**只读的区 | `config/auctionator.php` → `unsupported_server_ids` | 黑名单：优先于白名单与探测，给"不想从面板动"的区留出口 |
| 拍卖机器人默认怎么判定 | 自动探测该区 world 库有没有 `mod_auctionator_disabled_items` | 装了就能管、没装就只读；**不必**为了"支持多区"再手改白名单 |
| 守护实例（每区一个） | `config/supervisor.php` → `instances` | 一区一个 `acore_supervisor.exe`，各自 ini/logs；共享的 authserver 只交给其中一个实例守护 |

> 仓库里的 `config/boss.php` / `config/auctionator.php` **只放通用默认值**（`server_overrides`
> 为空、`server_root` 为空）；各区真实的索引、key、路径属于部署信息，必须写到
> `config/generated/boss.php` / `config/generated/auctionator.php`（这两个文件不入库，`git pull`
> 不会覆盖）。改完不需要清缓存。
>
> 合并规则要留意：`Core\Config` 是**递归合并**，generated 里只写部分 `server_overrides` 不会
> 删掉跟踪文件里的其它条目；但扁平列表 `supported_server_ids` 是整体替换，所以用
> "只给本机部署的区写 override"就足够，其余条目不会被读到。

### 拍卖机器人的「本区能不能管」怎么判定

按区部署过模块的区**自动就能管**，不需要维护名单；判定顺序（`AuctionatorController::serverSupport()`）：

1. `unsupported_server_ids` 命中 → 只读，reason `denied`；
2. `supported_server_ids` 命中 → 可管理，reason `explicit`（不查库，快路径）；
3. 否则探测该区 world 库有没有模块自己的表 `mod_auctionator_disabled_items`：
   - 有 → 可管理（reason `deployed`）；
   - 没有 → 只读，reason `not_deployed`；
   - **连不上该区库** → 只读，reason `db_unreachable`（说的是连接问题，不是"没装模块"）。

页面把结论发布在 `.au-page` 的 `data-au-supported` / `data-au-support-reason` 上，只读时
**只给一条**带区名的说明，并且不去碰该区的库表——不会再有满屏 `SQLSTATE ... Unknown table`
冒充"面板坏了"。写配置 / 物品查询 / GM 动作 / 按区一键启停四个接口走同一判定，只读时一律 422。

## 3. 按区独立启停

两个模块都跑在各区自己的 worldserver 进程里，所以「启动/停止」= 对该区的运行时开关发
SOAP 命令（面板发出的每条命令都带当前区服的 `server_id`，落到该区自己的 SOAP 端口）。

| 模块 | 面板操作 | 实际动作 | 是否立即生效 |
|---|---|---|---|
| 活动 Boss | 「Boss 活动管理」→ 生成 / 击杀 / 重置 / 重载配置 | `.boss spawn` / `.boss kill` / `.boss clear` / `.boss config reload` | 是 |
| 拍卖机器人 | 「拍卖机器人」→ 模块状态卡 → **启动本区机器人 / 停止本区机器人** | 写本区 `mod_auctionator.conf` 的 `Auctionator.Enabled`，再发 `.auctionator start` / `.auctionator stop` | 是（配置跨重启保持） |

拍卖机器人的总开关单独做一个按钮的原因：`Auctionator.Enabled` 是启动时读一次的，
只改配置文件必须重启该区 worldserver 才生效；`.auctionator start|stop` 是运行时开关
（`Auctionator::SetEnabled()`，会重建事件排程），两者一起做才是"一键启停且重启后保持"。
worldserver 没在跑时按钮会明确回报「配置已写、命令未生效」，而不是谎报成功。

事件/贡献的按区隔离由**两侧共同保证**：脚本写入时带上本区 key，面板 4 条查询都带
`state_key` 过滤。面板对缺列的情况**失败关闭**：该区脚本还没升级时页面只给一条
"本区脚本未升级"的警告，而不是不带过滤地把别的区的数据读出来。

## 4. 新增一个区要做什么

1. **部署该区 worldserver**：复制一份 `release\<区>`，改
   `configs/worldserver.conf` 的 `RealmID` / `WorldServerPort` /
   `WorldDatabaseInfo` / `CharacterDatabaseInfo` / `SOAP.Port` / `DataDir` / `LogsDir` / `TempDir`；
   `Data` 目录可以用 junction 指到已有副本（省几 GB）：
   `New-Item -ItemType Junction -Path <新区>\Data -Target <老区>\Data`。
   新区的 DB 如果是旧核心导入的，第一次启动时核心的 DB 更新器会自动补齐（几百条 update，
   先备份；这一步可能耗时几分钟，期间该区起不来）。
2. **在 acore_auth.realmlist 加一行**（端口与 `WorldServerPort` 一致）。
3. **活动 Boss**：**不需要建库**，只要给该区一个自己的 key：
   ```powershell
   pwsh -File <acore-boss-smartai>\tools\deploy-realm.ps1 `
       -RealmRoot <区目录> -RuntimeKey <该区 key> -LuaExe <lua.exe> `
       -ApplyTierSql <该区 world 库> -DbPassword <pw>
   ```
   它会改写 `boss.lua` §2 的 key、备份原文件、做语法检查，并把难度档位 SQL 导入该区
   （**导入前会把 SQL 里写死的共用库名与主区 key 都换成该区的**，否则那一步会去改主区的
   活动配置）。然后把该区加进 `config/generated/boss.php` 的 `server_overrides`（`runtime_key`
   填同一个 key）与 `supported_server_ids`。该区第一次加载时 `boss.lua` 会用这个 key
   `INSERT IGNORE` 一份默认配置。
4. **拍卖机器人**：把 `mod_auctionator.conf.dist` 复制成该区的
   `configs\modules\mod_auctionator.conf`，把模块 SQL 导进**该区的** world/characters 库
   （`data/sql/db-world/base/*.sql`、`data/sql/db-characters/updates/*.sql`），
   并把 `Auctionator.CharacterId` / `CharacterGuid` 指向该区自己的机器人角色
   （各区必须各有一个专用角色），最后给该区写一条 `server_overrides`（区目录）。
   模块一装上，面板就会自动认出这个区可管理（探测到模块自己的表）；只有想跳过探测、
   或反过来**禁止**面板管某个区时，才需要动 `supported_server_ids` / `unsupported_server_ids`。
5. **只部署与「按区 key」兼容的 Eluna 脚本**：`boss.lua` 已经支持；
   仍写死默认库名（`ac_eluna`）且没有按区分租的脚本——`TriviaReward.lua`、
   `RecruitAFriend*.lua`、`LevelUpReward.lua`——**不能**同时上第二个区，否则两个区共用同一份
   数据（题库/开关/中奖名单、招募链接、升级奖励都会串）。把它们先放在 `lua_scripts/` 之外
   （建议 `<区>\lua_scripts_disabled_shared_db\` 并留一份说明，Eluna 不加载该目录），
   等它们也照 `boss.lua` 这样加一列 `state_key`（或各自独立库）后再放回来。
6. **守护**：复制一份 `release\supervisor-<区>`，改 `InstanceName` / `WorkDir` / `ServerConf`，
   `[authserver] Enabled = false`（共享登录服只由一个实例守护），然后在
   `config/generated/supervisor.php` → `instances` 里登记，并用
   `acore_supervisor.exe --config <ini> --once` 体检。
   面板 `instances` 的 id 建议用区服索引或区名；没写 `dir` 时面板按
   `release/supervisor-<id>`、`release/<id>/supervisor` 依次探测。
7. **面板验收**：页头切到该区 → Boss 页页头应显示该区的 `state_key`；拍卖页应显示该区的
   conf 路径；两个页面的写入只影响该区。跑 `php tools/verify_multi_realm.php`（离线，含可选
   写入隔离）与 `php tools/verify_multi_realm_live.php --server=<区索引>`（对着运行中的
   worldserver 验证按区启停 + 事件/贡献隔离）。

## 5. 验收清单（每次加区都跑一遍）

> 下面的校验脚本在本地 `tools/` 目录，按本仓库约定**不入库**（`.gitignore` 里 `/tools/` 标注为
> 本地校验工具）；`docs/multi-realm.md` 本身入库。

自动：
- [ ] `php tools/verify_multi_realm.php` —— 配置层/数据层/页面层/启停接线；加
      `--write-server=<测试区索引>` 还会真的写一次配置，断言只动了那一个区。
- [ ] `php tools/verify_multi_realm_live.php --server=<区索引>` —— 对着**运行中的** worldserver
      按真实控制器路径启停机器人，并回读 `.auctionator status` 证明运行时确实被切换、对照区未受影响。
- [ ] `php tools/verify_boss_ext_page.php <区索引> <该区 boss.lua>` —— Boss 页与 boss.lua 描述表逐列一致。
- [ ] `php tools/verify_auctionator_pages.php` —— 每个区的拍卖页：装了模块的区可管理、
      没装的区只读且**整页 0 处 SQL 报错**、只读说明与原因一致、写接口返回 422。
- [ ] `php tools/verify_auctionator_autodetect.php` —— 判定顺序回归：白名单/黑名单/自动探测
      （有表→`deployed`、无表→`not_deployed`、库连不上→`db_unreachable`）。

手工：
- [ ] 面板切到新区，`Boss 活动管理` 页头显示的 `state_key` = 该区 `boss.lua` §2 的 key。
- [ ] 该区 `lua_scripts\lua_logs\boss.log` 里有 `[BOSS] 本区绑定: db=... configKey=... runtimeKey=...`，
      两处的 key 与 db 都与页头一致。
- [ ] 在新区改一个 Boss 配置并保存 → 老区该配置不变（两区 key 不同）。
- [ ] 面板切到新区点「生成 Boss」→ 只有新区的 worldserver 收到命令（看该区 Server.log / boss.log）。
- [ ] 在新区触发一次 `.boss config reload` → 事件表里只有新区的 key 多一条，老区页面看不到它
      （`verify_multi_realm_live.php` 已自动断言这条）。
- [ ] 面板切到新区点「停止本区机器人」→ 该区 conf 的 `Auctionator.Enabled` 变 0，
      `.auctionator status` 显示 stopped；老区仍为 running。
- [ ] 面板切到**没装模块**的区 → 拍卖页只有一条带区名的只读说明（不是一屏 SQL 报错、
      也不是"本模块只支持单区"），字段仍能看到该区 conf 的内容。
- [ ] `supervisor` 页每个实例都能单独启停，且不会有两个实例守护同一个 worldserver。
- [ ] 该区脚本若是旧版（事件表还没有 `state_key` 列），Boss 页应只给一条"本区脚本未升级"警告，
      绝不能显示别的区的事件/贡献。
