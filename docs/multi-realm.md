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
  release\70                release\80                release\test
  worldserver.exe           worldserver.exe           worldserver.exe
  RealmID 1 / 8085          RealmID 2 / 8086          RealmID 3 / 8087
  acore_world70             acore_world80             acore_w_test
  acore_characters70        acore_characters80        acore_c_test
  lua_scripts\boss.lua      lua_scripts\boss.lua      lua_scripts\boss.lua
   └ BOSS_DB_NAME=           └ BOSS_DB_NAME=           └ BOSS_DB_NAME=
     ac_eluna70                ac_eluna                  ac_eluna_test
  configs\modules\mod_auctionator.conf（每区一份，互不影响）
  SOAP 7878                 SOAP 7879                 SOAP 7880
```

要点：

- **auth 共用**：账号、登录、realmlist 只有一套，各区在 `realmlist` 里各占一行（端口不同）。
- **world / characters / SOAP 各自一套**：面板的区服下拉框切换的就是它们。
- **活动 Boss 的库按区独立**：`boss_activity_config` / `_ext` / `_runtime` / `_events` /
  `_contributors` 都在该区自己的库里。事件与贡献表没有 `state_key` 列，**两个区共用一个库
  一定会串**（面板会显示别的区的事件/贡献），所以每区一个库是硬要求。
- **拍卖机器人的表在该区的 world/characters 库里**，conf 在该区目录里；模块本身不含
  全局单例，各区天然独立。

## 2. 绑定表（面板侧配置在哪）

| 项 | 面板位置 | 说明 |
|---|---|---|
| 区服清单（名称/端口/库/SOAP） | `config/generated/servers.php` | 安装向导生成，`server` 索引就是面板 URL 里的 `?server=` |
| Boss 本区库 / state_key | `config/boss.php` → `server_overrides` | 键 = server 索引；必须与该区 `boss.lua` §2 的 `BOSS_DB_NAME` / `BOSS_RUNTIME_KEY` 一致 |
| Boss 支持哪些区 | `config/boss.php` → `supported_server_ids` | 留空 = 全部支持；锁成单区时不在列表里的区直接 422、不发 SOAP |
| 拍卖机器人区服目录 / conf / 日志 | `config/auctionator.php` → `server_overrides` | 键 = server 索引；缺省值在文件顶部的 `server_root` / `conf_file` / `log_file` |
| 拍卖机器人支持哪些区 | `config/auctionator.php` → `supported_server_ids` | 同上，留空 = 全部 |
| 守护实例（每区一个） | `config/supervisor.php` → `instances` | 一区一个 `acore_supervisor.exe`，各自 ini/logs；共享的 authserver 只交给其中一个实例守护 |

> 改了 `config/*.php` 里的默认值不需要清缓存；覆盖项请写到 `config/generated/*.php`
> （不随仓库发布），否则升级会被覆盖。

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

## 4. 新增一个区要做什么

1. **部署该区 worldserver**：复制一份 `release\<区>`，改
   `configs/worldserver.conf` 的 `RealmID` / `WorldServerPort` /
   `WorldDatabaseInfo` / `CharacterDatabaseInfo` / `SOAP.Port` / `DataDir` / `LogsDir` / `TempDir`；
   `Data` 目录可以用 junction 指到已有副本（省几 GB）：
   `New-Item -ItemType Junction -Path <新区>\Data -Target <老区>\Data`。
   新区的 DB 如果是旧核心导入的，第一次启动时核心的 DB 更新器会自动补齐（本机 70 区补了约 400 条）。
2. **在 acore_auth.realmlist 加一行**（端口与 `WorldServerPort` 一致）。
3. **活动 Boss**：
   ```powershell
   pwsh -File <acore-boss-smartai>\tools\deploy-realm.ps1 `
       -RealmRoot <区目录> -DbName <该区库> -LuaEnv <lua.exe> `
       -ApplyTierSql <该区 world 库> -DbPassword <pw>
   ```
   它会改写 `boss.lua` §2 的 `BOSS_DB_NAME`/`BOSS_RUNTIME_KEY`、备份原文件、做语法检查，
   并把难度档位 SQL 导入该区（**导入前会把 SQL 里写死的 `ac_eluna` 换成 `-DbName`**，
   否则那一步会去改 80 区的活动配置）。然后把该区加进 `config/boss.php` → `server_overrides`
   与 `supported_server_ids`。首次启动 `boss.lua` 会自建库与表。
4. **拍卖机器人**：把 `mod_auctionator.conf.dist` 复制成该区的
   `configs\modules\mod_auctionator.conf`，把模块 SQL 导进**该区的** world/characters 库
   （`data/sql/db-world/base/*.sql`、`data/sql/db-characters/updates/*.sql`），
   并把 `Auctionator.CharacterId` / `CharacterGuid` 指向该区自己的机器人角色
   （各区必须各有一个专用角色），最后把该区加进 `config/auctionator.php` 的
   `server_overrides` 与 `supported_server_ids`。
5. **只部署与「按区库名」兼容的 Eluna 脚本**：`boss.lua` 已经支持；
   仍然写死 `ac_eluna` 的脚本（本机是 `TriviaReward.lua`、`RecruitAFriend*.lua`、
   `LevelUpReward.lua`）**不能**同时上第二个区，否则两个区共用同一份数据。
   先放在 `lua_scripts/` 之外（本机是 `<区>\lua_scripts_disabled_shared_db\`，里面有说明），
   等它们也支持按区库名后再放回来。
6. **守护**：复制一份 `release\supervisor-<区>`，改 `InstanceName` / `WorkDir` / `ServerConf`，
   `[authserver] Enabled = false`（共享登录服只由一个实例守护），然后在
   `config/generated/supervisor.php` → `instances` 里登记（本机已登记 `default`=80 区、
   `70`=70 区），并用 `acore_supervisor.exe --config <ini> --once` 体检。
7. **面板验收**：页头切到该区 → Boss 页页头应显示该区的库名；拍卖页应显示该区的 conf 路径；
   两个页面的写入只影响该区。跑 `php tools/verify_multi_realm.php`（离线，含可选写入隔离）
   与 `php tools/verify_multi_realm_live.php --server=<区索引>`（对着运行中的 worldserver
   验证按区启停）。

## 4.1 本机现状（70 区已实体部署）

| 项 | 70 区 | 80 区 |
|---|---|---|
| worldserver 目录 | `E:\Server\release\70`（`Data` 是 junction → 80 区） | `E:\Server\release\80` |
| RealmID / 世界端口 / SOAP | 1 / 8085 / 7878 | 2 / 8086 / 7879 |
| characters / world 库 | `acore_characters70` / `acore_world70` | `acore_characters80` / `acore_world80` |
| Boss 库 | `ac_eluna70` | `ac_eluna` |
| boss.lua | 由 `deploy-realm.ps1` 写入（§2 = `ac_eluna70`） | 历史部署（§2 = `ac_eluna`） |
| 拍卖机器人 conf | `release\70\configs\modules\mod_auctionator.conf`（初始 `Enabled=0`、无机器人角色） | `release\80\...`（`Enabled=1`、guid 542） |
| 守护实例 | `release\supervisor-70`（只守护 worldserver） | `release\supervisor`（worldserver + 共享 authserver） |
| Eluna 脚本 | 只放 `boss.lua` + `buff/chatlog/Phase/removeItem` | 全套 |


## 5. 验收清单（每次加区都跑一遍）

> 下面的校验脚本在本地 `tools/` 目录，按本仓库约定**不入库**（`.gitignore` 里 `/tools/` 标注为
> 本地校验工具）；`docs/multi-realm.md` 本身入库。脚本内容见本机 `E:\Server\web\WWW\AGMP\tools\`。

自动：
- [ ] `php tools/verify_multi_realm.php` —— 配置层/数据层/页面层/启停接线；加
      `--write-server=<测试区索引>` 还会真的写一次配置，断言只动了那一个区。
- [ ] `php tools/verify_multi_realm_live.php --server=<区索引>` —— 对着**运行中的** worldserver
      按真实控制器路径启停机器人，并回读 `.auctionator status` 证明运行时确实被切换、对照区未受影响。
- [ ] `php tools/verify_boss_ext_page.php <区索引> <该区 boss.lua>` —— Boss 页与 boss.lua 描述表逐列一致。

手工：
- [ ] 面板切到新区，`Boss 活动管理` 页头显示的库名 = 该区 `boss.lua` §2 的 `BOSS_DB_NAME`。
- [ ] 该区 `lua_scripts\lua_logs\boss.log` 里有 `[BOSS] 本区绑定: db=... configKey=... runtimeKey=...`。
- [ ] 在新区改一个 Boss 配置并保存 → 老区该配置不变（两区库不同）。
- [ ] 面板切到新区点「生成 Boss」→ 只有新区的 worldserver 收到命令（看该区 Server.log / boss.log）。
- [ ] 面板切到新区点「停止本区机器人」→ 该区 conf 的 `Auctionator.Enabled` 变 0，
      `.auctionator status` 显示 stopped；老区仍为 running。
- [ ] `supervisor` 页每个实例都能单独启停，且不会有两个实例守护同一个 worldserver。
