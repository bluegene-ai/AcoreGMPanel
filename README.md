# Acore GM Panel

A web game management toolkit for AzerothCore realms. Acore GM Panel is a modern MVC control panel for [AzerothCore](https://www.azerothcore.org/) realms. It streamlines daily server operations with a consistent UI, unified tooling, and multi-realm aware services that cover the most common GM and administrator workflows.

## Highlights

- **Modular architecture** – Each feature lives in an isolated domain (Account, Item, Creature, Quest, Mail, Mass Mail, Items / Inventory, SmartAI, SOAP). Modules share common helpers, middleware, and UI components.
- **Multi-realm support** – Dynamic realm switching with per-realm database and SOAP credentials, plus inheritance rules for shared authentication.
- **Secure by default** – CSRF protection, authentication middleware, audit logging, and configurable SOAP whitelisting.
- **Consistent UX** – Shared layout, design tokens, reusable components, and a front-end helper (`panel.js`) that abstracts base-path aware API calls.
- **Setup wizard** – Five step installer that validates environment, collects credentials, creates generated config, and locks the installation.

## System Requirements

| Component | Requirement |
|-----------|-------------|
| PHP       | 8.1 or later (tested with 8.1/8.2) |
| Extensions| `pdo_mysql`, `mbstring`, `soap`, `intl` (recommended), `json`, `openssl` |
| Database  | MySQL / MariaDB compatible with AzerothCore schemas |
| Web server| Apache / Nginx (rewrite capable) |
| Composer  | 2.x (optional but recommended for autoload refresh) |

> The setup wizard performs runtime checks for PHP version and mandatory extensions. Ensure CLI and web SAPIs share the same PHP build.

## Getting Started

1. **Clone the repository**
  ```bash
  git clone https://github.com/bluegene-ai/AcoreGMPanel.git
  cd AcoreGMPanel
  ```
2. **Install PHP dependencies (optional)** – Composer is only required when you change namespaces.
  ```bash
  composer install
  ```
3. **Prepare writable directories**
  - `storage/`
  - `storage/logs/`
  - `storage/cache/`
  - `storage/ip_geo/`
  - `config/generated/`

4. **Configure web server**
  - Point the document root to `public/`.
  - Ensure URL rewriting routes all requests to `public/index.php`.
  - Set proper permissions for the PHP user on the writable directories above.

5. **Run the setup wizard**
  - Access the site in a browser. If `install.lock` is missing you will be redirected to `/setup`.
  - Complete the five steps: environment check → connection details → connectivity tests → administrator account → generated config.
  - After success the wizard creates files under `config/generated/` and writes `config/generated/install.lock`.

6. **Login and explore modules**
  - Use the administrator credentials you defined in the wizard.
  - Switch realms from the top navigation to verify multi-realm configuration.

### Manual configuration (optional)

If you prefer to bypass the wizard, copy defaults from `config/*.php` into `config/generated/` and adjust values manually:

- `config/generated/app.php`
- `config/generated/database.php`
- `config/generated/servers.php`
- `config/generated/soap.php`
- `config/generated/auth.php`

When deploying under a sub-path (e.g. `/panel`), set `'base_path' => '/panel'` in `config/generated/app.php`. All helper functions (`url()`, `asset()`, `Panel.api`) automatically respect this prefix.

## Directory Structure

```
AcoreGMPanel/
├── app/                  # Core services, domain logic, controllers, middlewares
│   ├── Core/             # Framework-like utilities (Routing, Lang, Request, Response)
│   ├── Domain/           # Business logic grouped by module
│   ├── Http/             # Controllers and HTTP middleware
│   └── Support/          # Shared helpers (auth, audit, SOAP, game meta)
├── bootstrap/            # Autoload bootstrap and global helper registration
├── cli/                  # Maintenance and utility scripts (e.g., comment updater)
├── config/               # Base configuration blueprints
├── config/generated/     # Runtime-generated config produced by the setup wizard
├── public/               # Web entry point (`index.php`) and static assets
├── resources/
│   ├── lang/             # Localization files (en, zh_CN)
│   └── views/            # PHP view templates and components
├── routes/               # Route declarations (`web.php`)
├── storage/
│   ├── cache/            # Cached data (mass mail names, ...)
│   └── logs/             # Runtime logs per module
├── docs/                 # Design notes and module-specific documentation
└── vendor/               # Composer dependencies (optional)
```

## Core Modules Overview

| Module | Path | Summary |
|--------|------|---------|
| Account Management | `/account` | Search, view, and manage account metadata, GM levels, bans, and connected characters. |
| Item Toolkit | `/item` | CRUD for `item_template`, diff previews, and SQL execution guardrails. |
| Creature Toolkit | `/creature` | Template editor with model management, diffing, and quick SQL exports. |
| Quest Toolkit | `/quest` | Aggregated quest authoring with editor, diffing, and logs. |
| Mail Center | `/mail` | Inspect, delete, and mark mail with attachments. |
| Mass Mail | `/mass-mail` | Bulk announcements, item/gold distribution, and boost presets. |
| Items / Inventory | `/item-inventory` | Two search axes: per-character bag/bank/equipment reads, and item-to-owner lookup with stack reduction, bulk delete and bulk replace. The legacy `/bag`, `/item-ownership` and `/bag-query` URLs 301-redirect here. |
| SmartAI Wizard | `/smart-ai` | Guided builder for `smart_scripts` entries with SQL export. |
| SOAP Wizard | `/soap` | Browse SOAP commands, fill dynamic forms, preview and execute requests securely. |
| Supervisor | `/supervisor` | Inspect and control `acore_supervisor.exe` (the worldserver / authserver watchdog): state, world-loop heartbeat, auth probe, restart counters, plus per-service start / stop / restart. On a multi-realm machine the page gets an **instance switcher** (see below). |
| Chat Trivia | `/trivia` | Admin page for the [ac-trivia](https://github.com/bluegene-ai/ac-trivia) Lua event, split into tabs (runtime status / question bank / reward presets / leaderboard / settings): live round state (via SOAP `.trivia api`), a next-question countdown, start / stop / pause-resume / enable-disable controls (one toggle each, labelled from the live state), daily **scheduled start/stop windows**, pacing + answer-channel + label settings, question bank CRUD with CSV/TSV/JSON template import & export, reward presets, and the winner leaderboard. Settings and questions live in the server's `ac_eluna` database (tables are created by the Lua script). |

## Further Reading

Additional focused guides live in the `docs/` directory and project root:

### Several supervisors (one per realm)

One `acore_supervisor.exe` supervises exactly one worldserver + one authserver, so a multi-realm
machine runs one supervisor process per realm. List them in `config/supervisor.php` (or the
untracked `config/generated/supervisor.php`); every other key in that file becomes the **default**
for these entries:

```php
'instances' => [
    // id => overrides; a plain string is shorthand for ['dir' => …]
    'realm-a' => ['label' => 'Realm A', 'dir' => 'D:\AzerothCore\release\supervisor'],
    'realm-b' => [
        'label' => 'Realm B',
        'dir' => 'D:\AzerothCore\release\supervisor-b',
        'allow_start' => true,                  // one scheduled task per supervisor
        'start_task_name' => 'AcoreSupervisorB',
    ],
    // the instance the flat keys above describe (auto-detected release/supervisor):
    'default' => [],
],
```

- Giving an instance its own `dir` makes it **derive** `exe / status_file / control_file / log_file`
  from that directory instead of inheriting the flat file paths (otherwise two instances would read
  and write the same status and command files).
- The page renders a switcher (each button carries a state dot); switching sends `?instance=<id>` on
  every API call and is reflected in the URL, so a refresh keeps the selection.
- An **unlisted instance id is refused** by the APIs (404) rather than silently falling back to
  another realm; the audit entry (`panel_audit`) records the instance.
- Without `instances` (or with an empty array) nothing changes: one implicit instance `default`.



## IP Geolocation (Local Database)

The panel resolves IP locations using a local MaxMind `.mmdb` database (no online queries, no disk cache).

1. Runtime requirement (choose one):
  - Recommended: deploy with `vendor/` (server does not need Composer).
  - Optional: install the PHP `maxminddb` extension (if available for your PHP build).
2. Download a MaxMind database (recommended: GeoLite2 City) and place it at:
  - `storage/ip_geo/GeoLite2-City.mmdb`
3. (Optional) Override path/locale in `config/generated/ip_location.php`:
  - `mmdb_path` (absolute path)
  - `locale` (e.g. `zh-CN`, `en`)

To build `vendor/` locally (PowerShell):
- `powershell -ExecutionPolicy Bypass -File .\scripts\install-deps.ps1`

Note: the `.mmdb` file is not committed; place it manually under `storage/ip_geo/`.


## Contributing

1. Fork the repository and create a feature branch.
2. Keep modules isolated—add new services under `app/Domain/<Module>` and controllers under `app/Http/Controllers/<Module>`.
3. Run PHP linting (`php -l`) and, if applicable, unit tests before submitting a PR.
4. Translate UI strings by updating both `resources/lang/en` and `resources/lang/zh_CN`.

## License

This project follows AzerothCore community usage guidelines. Refer to the repository license file or contact the maintainers for commercial usage inquiries.
