# Beads for Laravel

A local, Linear-style UI for [beads](https://beads.gascity.com/) (`bd`) issue tracking,
installed as a Laravel **dev package**.

- It only talks to the `bd` CLI. bd stays the single source of truth; the package stores no
  tickets of its own and has no relation to your app's code or database.
- It only runs in the `local` environment.
- List, board, epics, ready/blocked views, a side-peek detail with a markdown editor,
  Cmd+K, and live updates when an agent changes issues from the CLI.

Requirements: PHP 8.4+, Laravel 12 or 13, and bd **1.3.0 or newer**.

## Installation

```bash
composer require --dev codeberry/beads-for-laravel
php artisan beads:install
```

`beads:install` publishes `config/beads.php` and the compiled UI to `public/vendor/beads`, and
offers to add this hook to the `post-update-cmd` scripts of your `composer.json`, so the assets
stay in sync after every `composer update`:

```json
"@php artisan vendor:publish --tag=beads-assets --force"
```

Use `php artisan beads:install --hook` or `--no-hook` to answer without a prompt.

Then open `/beads` in your app (for example `http://my-app.test/beads`).

If the shell shows a banner saying the assets are out of date or not published, run:

```bash
php artisan beads:publish
```

### bd under Herd (and other PHP-FPM setups)

Herd's PHP-FPM clears the environment, so web requests have no `PATH`, `HOME` or `USER`. The
package therefore never relies on them:

- bd is called with an **absolute path**. Without `BEADS_BINARY` it looks in
  `/opt/homebrew/bin/bd`, `/usr/local/bin/bd`, `~/go/bin/bd` and `~/.local/bin/bd`, in that
  order. Set `BEADS_BINARY` when your bd lives elsewhere, or when an older bd comes first.
- `HOME` comes from the system user database (`posix_getpwuid`), and `PATH` is the directory of
  the binary plus `/usr/bin:/bin`.

## Configuration

All options live in `config/beads.php` and can be set from `.env`:

| Option | Env | Default | Meaning |
|---|---|---|---|
| `enabled` | `BEADS_ENABLED` | `true` | Set to `false` to turn the UI off, also locally. |
| `path` | `BEADS_PATH` | `base_path()` | The directory that contains `.beads`. |
| `binary` | `BEADS_BINARY` | auto-detect | Absolute path to `bd`. A configured path always wins. |
| `actor` | `BEADS_ACTOR` | `git config user.name` | The actor bd records for changes made in the UI. |
| `humans` | – | `[actor]` | Authors that are not listed here get an agent badge. |
| `route.prefix` | `BEADS_ROUTE_PREFIX` | `beads` | The URL prefix. |
| `route.domain` | `BEADS_DOMAIN` | `null` | Serve the UI on a separate domain. |
| `route.middleware` | – | `['web']` | Session and CSRF protection come from `web`. |
| `poll_interval` | – | `3` | Seconds between live-update polls. |
| `closed_window_days` | – | `30` | Closed issues newer than this are loaded by default. |
| `lock_timeout` | – | `10` | Seconds to wait for the workspace lock (then a 503 and a retry). |
| `process_timeout` | – | `30` | Seconds a single bd call may take. |

### Access

Routes, the UI and the commands are only registered when `app()->isLocal()` and
`beads.enabled` is not false. On top of that, every request passes the `viewBeads` gate. The
package defines it (allowing everyone, also guests) only when your app has not defined it, so
you can restrict it:

```php
// app/Providers/AppServiceProvider.php
Gate::define('viewBeads', fn (User $user) => $user->is_admin);
```

## Health screens

When something is wrong the UI shows a full screen with the commands to fix it:

| State | Cause | Fix |
|---|---|---|
| `binary_missing` | No bd found (or `BEADS_BINARY` points nowhere). | `brew install beads`, or set `BEADS_BINARY`. |
| `version_too_old` | bd is older than 1.3.0. | `brew upgrade beads` |
| `workspace_missing` | No `.beads` directory in the workspace. | `bd init` there, or set `BEADS_PATH`. |
| `schema_migration_required` | The bd database schema is behind the binary. | `bd migrate schema` in the workspace. |

## Keyboard shortcuts

| Key | Action |
|---|---|
| `⌘K` / `Ctrl+K` | Command menu: search issues (also `p0`–`p4`), navigate, create |
| `C` | Create an issue |
| `/` | Search the current list or board |
| `J` / `K` (or ↓ / ↑) | Next / previous issue |
| `Enter` | Open the issue in the side-peek |
| `Esc` | Close the side-peek or dialog |
| `⌘Enter` | Save the text you are editing, submit a comment or create the issue |

Shortcuts never fire while you type in a field.

## How it works

- **No shell, one call at a time.** Every bd call is an argument array (values always as
  `--flag=value`, free text after `--`), runs with `--json --sandbox` (and `--readonly` for
  reads), and goes through a per-workspace `flock`. bd's embedded Dolt store does not survive
  concurrent calls, and even reads write to it.
- **Cheap live updates.** The browser polls a snapshot every few seconds. The server compares a
  fingerprint of the files under `.beads` (path, mtime, size) with the one recorded after its own
  last call; only a change triggers a new `bd export`. An unchanged workspace answers `304` without
  calling bd.
- **Ready and blocked** are derived from the same export in PHP and match `bd ready` and
  `bd blocked`.
- **Safe editing.** Pickers save directly and optimistically. Text fields autosave and send the
  value they started from; when bd has a different value (an agent edited it meanwhile) you get a
  conflict bar instead of a silent overwrite. The markdown editor only saves when you actually
  changed something, and opens content it cannot keep losslessly (raw HTML, footnotes, tables) as
  markdown source.

The snapshot is cached in `storage/framework/beads`, independent of your cache driver.

## Not in v1

No deleting, no bulk actions, no dependency graph, no manual ordering, one workspace per app,
no `bd init`/`sync`/`push`/`pull` from the UI, and an English UI (ready for translation).

## Development

The package uses an [Orchestra Testbench](https://packages.tools/testbench) workbench:

```bash
composer install && npm install

composer demo      # (re)create a demo workspace with the real bd in workbench/demo
composer serve     # publish the assets and serve the workbench at http://127.0.0.1:8000/beads
npm run dev        # Vite with HMR; the shell picks it up through the ./hot file

composer test      # Pest
composer analyse   # Larastan
composer format    # Pint
npm run build      # type-check and build dist/ (committed)
composer fixtures  # re-record the bd fixtures in tests/Fixtures/bd-<version>
```

Tests only cover PHP. bd output comes from fixtures recorded from a real bd 1.3.2 workspace; after
a bd upgrade, run `composer demo && composer fixtures` and the test suite.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
