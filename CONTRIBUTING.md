# Contributing to Beads for Laravel

Thanks for helping out! This guide gets you from a fresh clone to a merged pull request.

## Before you start

- **Bugs:** open an [issue](https://github.com/codeberry/beads-for-laravel/issues/new/choose) with
  the steps to reproduce it and your bd version (`bd --version`).
- **Features and bigger changes:** start a [discussion](https://github.com/codeberry/beads-for-laravel/discussions)
  first, so we can agree on the approach before you spend time on it. Check
  [Not (yet) included](README.md#not-yet-included): some features were left out on purpose.
- **Security issues:** never in public; see [SECURITY.md](SECURITY.md).

## Setting up

You need PHP 8.4+, Composer, Node 22.12+ (or 24) and [bd](https://beads.gascity.com/) 1.3.0+.

```bash
git clone https://github.com/codeberry/beads-for-laravel.git
cd beads-for-laravel
composer install
npm install
```

The package is developed in an [Orchestra Testbench](https://packages.tools/testbench) workbench,
which is a small Laravel app around the package:

```bash
composer demo    # create a demo bd workspace in workbench/demo (with the real bd)
composer serve   # publish the assets and serve http://127.0.0.1:8000/beads
npm run dev      # in a second terminal: Vite with hot reloading
```

`composer demo` can be run again at any time; it throws the old demo away and builds a fresh one
with epics, blocked chains, comments by humans and agents, custom statuses and a closed issue.
While `npm run dev` runs, the shell loads the UI from Vite (it writes a `hot` file); stop it to see
the published build again.

On macOS with Laravel Herd you can also run `composer serve` as a Herd process.

## Project layout

```
src/
  Cli/         Running bd: binary lookup, version check, the runner, the workspace lock
  Workspace/   The workspace, its health checks and the actor
  Data/        Tolerant DTOs mapped from bd's JSON
  Snapshot/    The fingerprint-gated bd export and the API payloads
  Graph/       Relations, ready/blocked and epic progress (IssueGraph)
  Actions/     Writes: create, update, close/reopen, relations, comments
  Http/        Controllers, form requests and middleware for the JSON API
  Commands/    beads:install and beads:publish
resources/
  js/          The Vue 3 + TypeScript app (pages, components, composables, lib, lang)
  views/       The Blade shell
routes/        The SPA catch-all and the /api routes
tests/         Pest tests and the recorded bd fixtures
workbench/     The Testbench app, plus the demo and fixture commands
dist/          The compiled UI (built by CI, see below)
```

## Guidelines

### PHP

- Every bd call goes through `BdRunner`: an argument array, never a shell string, and values always
  as `--flag=value` (free text after `--`). Never call bd in any other way.
- Every bd call needs the workspace lock (the runner takes it for you). Reads write to bd's store
  too, so there are no "safe" parallel calls.
- Map bd output through the DTOs in `src/Data`, tolerantly: unknown fields are ignored and missing
  ones get defaults, because bd's JSON changes between versions.
- Code style is [Pint](https://laravel.com/docs/pint) (Laravel preset, `declare(strict_types=1)`)
  and [Larastan](https://github.com/larastan/larastan) at level 6, without a baseline.

### Frontend

- Vue 3 with `<script setup lang="ts">`, strict TypeScript. Types for the API live in
  `resources/js/lib/types.ts`.
- Every UI text goes through `t('key')` from `resources/js/lang/en.ts`; no hardcoded strings in
  components.
- UI primitives are [shadcn-vue](https://www.shadcn-vue.com) components, kept as source in
  `resources/js/components/ui`. Styling uses the design tokens in `resources/js/css/app.css`;
  avoid one-off colors.
- Keyboard shortcuts register through `useShortcut()`, never with your own `keydown` listener.

### Commits and pull requests

- Keep a pull request to one change, with a short description of what and why.
- Write commit messages in the imperative ("Add …", "Fix …").
- **Do not commit `dist/`.** CI builds the assets on `main` and commits them, just like Laravel
  Horizon does. If `npm run build` changed `dist/` locally, undo it with `git checkout dist`.
- The pull request template has a short checklist; please go through it.

## Tests

```bash
composer test      # Pest
composer analyse   # Larastan
composer format    # Pint (fixes the code style)
npm run typecheck  # vue-tsc
```

- Tests are PHP only, with Pest and Testbench. bd is never run in tests: `Process::fake()` returns
  real bd output from fixtures in `tests/Fixtures/bd-<version>/`. The helpers in `tests/Pest.php`
  (`fakeBd()`, `fixtureIssues()`, `bdArgs()`, …) do most of the work.
- Every bug fix comes with a test that fails without the fix.
- The frontend has no automated tests. For UI changes, check them by hand in the workbench, in
  light and dark, and mention what you checked in the pull request.

### New bd version

When bd changes its output, record new fixtures from a real workspace and run the suite:

```bash
composer demo
composer fixtures   # writes tests/Fixtures/bd-<version>/
composer test
```

Point the helpers in `tests/Pest.php` at the new fixture directory when the tests pass on it.

## Continuous integration

GitHub Actions runs on every push and pull request:

| Workflow | What it does |
|---|---|
| `run-tests` | Pest on PHP 8.4 and 8.5 with Laravel 12 and 13, lowest and latest dependencies |
| `phpstan` | Larastan |
| `fix-php-code-style-issues` | Runs Pint and commits the fixes |
| `build-assets` | Type-checks and builds the UI; on `main` it commits `dist/` |
| `update-changelog` | Writes the notes of a published release into `CHANGELOG.md` |
| `dependabot-auto-merge` | Merges patch and minor dependency updates once the checks pass |

## Releasing (maintainers)

1. Make sure `main` is green and `dist/` is up to date (the `build-assets` workflow has run).
2. Create a release on GitHub with a new tag (`v1.2.0`, following [semver](https://semver.org)) and
   write the release notes.
3. Packagist picks up the tag through the GitHub webhook, and `update-changelog` adds the notes to
   `CHANGELOG.md`.

## Code of conduct

Be kind and assume good intent. Harassment or disrespectful behaviour is not tolerated in issues,
discussions or pull requests.
