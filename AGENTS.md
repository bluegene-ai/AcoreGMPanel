# AGMP — Acore GM Panel (project conventions)

Panel at `<web root>/AGMP`, PHP 8.4 (ZTS) + self-rolled lightweight MVC,
served by Apache at `http://<host>/agmp/` (lowercase, case-sensitive).

Keep this file short and normative. Design write-ups belong in `docs/`.

## URL construction (base path) — hard rule

The panel is deployed **under a sub-path**, not at the web root:
`config/generated/app.php` sets `'base_path' => '/agmp'`.

So `http://localhost/character/view` does **not** exist. Every generated URL
must carry the base path, or it resolves against the web root and **404s**.

### PHP
Always use a helper. Never hand-write a root-relative `href`/`action`/`src`.

- `url('/path')` / `\Acme\Panel\Core\Url::to('/path')` — panel URL, base included.
- `url_with_server('/path')` — same, plus the current `?server=` arg.
- `character_view_url($guid)` / `account_view_url($id)` — entity links.
- `character_link($guid, $label)` / `account_link($id, $label)` — ready `<a>` HTML.

Defined in `bootstrap/helpers.php` (they delegate to `Core\Url::to`).

### JavaScript
Two different rules — they are **not** interchangeable:

| Purpose | Use | Pass |
|---|---|---|
| **API calls** (fetch/XHR) | `Panel.api.get/post(path)` | **panel-relative** (`/aegis/api/x`) |
| **Links / navigation** (`href`, `location.href`) | `Panel.absoluteUrl(path)` | panel-relative (`/character/view?guid=1`) |

`Panel.api()` runs `buildUrl()` (`panel.js`), which prepends the base itself —
so **passing an `absoluteUrl()` result to it double-prefixes** into
`/agmp/agmp/...`. Conversely, a plain `href` gets no prefixing at all.

`Panel.basePath()` returns the base alone (`/agmp`, or `''` at the web root).
The base is published **only** as `data-app-base` on `<body>`; `window.APP_BASE`
is never defined.

### Why this rule exists
The same defect recurred in two modules (`aegis.js`, `mail.js`), both times from
a hand-written root-relative URL:

- `aegis.js` built character/account links without the base → **404** on every
  row of the cheat-management lists.
- `mail.js` called a `urlWithServer()` helper that only existed inside
  `account.js`'s separate IIFE → **`ReferenceError`**, breaking the mail list
  and detail sender/receiver cells outright.

Both are fixed; the point is that neither was caught by syntax checking. Treat a
new hand-written `'/path'` in a JS `href` as a bug until proven otherwise.

## Modules
- A page module is an IIFE in `public/assets/js/modules/<name>.js`, guarded by
  `document.body.dataset.module !== '<name>'`, registered in
  `app/Support/ModuleAssets.php`.
- Server data reaches JS through a `window.<NAME>_DATA` JSON script tag.
- Capabilities gate UI: `window.PANEL_CAPABILITIES`, checked via `can(key)`.
- Mutating endpoints are POST under `CsrfMiddleware`, and are capability-gated.

## Checks before reporting done
- `php -l` on every changed `.php` file (PHP at `$env:PHP_HOME\php.exe`).
- `node --check` on every changed `.js` file.
- Verify against the running panel at `http://localhost/agmp/` — a JS change
  needs a hard refresh (Ctrl+F5), not an Apache restart.
