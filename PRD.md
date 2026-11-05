# PRD — `codeberry/beads-for-laravel`

Een lokale, Linear-achtige UI voor [beads](https://beads.gascity.com/) (`bd`) als
losstaand Laravel-package. Opgesteld op 2026-10-29 na een interview van 33 vragen;
de besluiten hieronder zijn leidend.

## Doel

Een Laravel **dev-package** dat de beads-tickets van het project waarin het
geïnstalleerd is toont en bewerkt in een snelle, Linear-achtige webinterface.
Het package praat uitsluitend met de `bd`-CLI, voegt **geen eigen opslag** voor
tickets toe (bd blijft de enige bron van waarheid) en heeft **geen enkele
relatie** met de code van de host-app. Het werkt alleen lokaal.

Referenties voor CLI-gebruik (alleen werkbare code, géén styling):
[mantoni/beads-ui](https://github.com/mantoni/beads-ui) en
[brendan-appstart/bead-me-up-scotty](https://github.com/brendan-appstart/bead-me-up-scotty).

## Niet-doelen (v1)

- Geen `bd delete` vanuit de UI.
- Geen bulkacties / multi-select.
- Geen dependency-graph-view.
- Geen handmatige volgorde (rank) van issues.
- Geen multi-project-switcher; één workspace per host-app.
- Geen `bd init`, `sync`, `push` of `pull` vanuit de UI.
- Geen browser-/e2e-tests en geen frontend-unit-tests.
- Geen uitgebreide of configureerbare sneltoetsen (alleen de basisset).
- Geen Nederlandse UI (wel i18n-klaar).
- Geen installatie in recruitment of andere host-apps tijdens de bouw.

## Besluiten

| #  | Onderwerp | Besluit |
|----|-----------|---------|
| 1  | Locatie | Eigen repo: `~/dev/beads` is de package-root (lokale git, geen remote). |
| 2  | Naam | Composer `codeberry/beads-for-laravel`, namespace `Codeberry\Beads`, `BeadsServiceProvider`, `config/beads.php`, route `/beads`, env `BEADS_*`. |
| 3  | Versies | PHP `^8.4`, `illuminate/*` `^12.0\|\|^13.0`. |
| 4  | Frontend | Precompiled Vue 3 + TypeScript-SPA (Vue Router, Tailwind 4) met een JSON-API van het package (Horizon/Telescope-patroon). |
| 5  | Assets | `vendor:publish` naar `public/vendor/beads` (tag `beads-assets`). |
| 6  | Verouderde assets | `beads:install` (publiceert config + assets, biedt `post-update-cmd`-hook aan), `beads:publish`, en een banner bij een manifest-hash-mismatch. |
| 7  | Alleen lokaal | `require --dev`; routes, UI en commands alleen als `app()->isLocal()` en `BEADS_ENABLED` niet `false`; `web`-middleware (CSRF); overschrijfbare Gate `viewBeads` (lokaal standaard toegestaan, ook zonder login). |
| 8  | Scope | Lezen + kern-schrijven: aanmaken, titel/markdownvelden, status, prioriteit, type, assignee, labels, parent, relaties, comments, close/reopen. Geen delete. |
| 9  | Actualiteit | Fingerprint-polling (~3 s): goedkope PHP-fingerprint van `.beads`; alleen bij verandering een `bd export`, met een content-hash als ETag. |
| 10 | Workspace | Eén: `base_path()` of `BEADS_PATH`. Onboarding-scherm met commando's als bd of `.beads` ontbreekt. |
| 11 | Views v1 | Kern: issuelijst, detail (side-peek + pagina), Cmd+K. Plus: Board (drag & drop), Epics met voortgang, Ready/Blocked. Graph naar v2. |
| 12 | Statussen | Dynamisch via `bd statuses --json`; elke status (ook custom) is een groep/kolom, geordend op categorie: frozen → active → wip → `blocked` → done. Blokkade door een dependency is een badge, geen status. |
| 13 | Prioriteit | Linear-namen + iconen: P0 Urgent, P1 High, P2 Medium, P3 Low, P4 Backlog; P-nummer in tooltip en zoeken (`p0`). |
| 14 | Taal | Engels, i18n-klaar (eigen `t()` + `lang/en.ts`, geen vue-i18n). |
| 15 | Componenten | shadcn-vue (reka-ui) als broncode in het package, met eigen Linear-geïnspireerde Tailwind 4-tokens, self-hosted Inter en Lucide-iconen. |
| 16 | Thema | System + toggle (localStorage), dark-first; indigo/violet accent; statuskleuren per categorie. |
| 17 | Sneltoetsen | Basisset: `Cmd+K`, `C`, `/`, `J`/`K`, `Enter`, `Esc`, via een centrale registry, uit in invoervelden. |
| 18 | Bulk | Niet in v1; de lijst houdt rekening met latere selectie. |
| 19 | Markdown | Tiptap 3 (WYSIWYG) met `@tiptap/markdown`, ook als read-only renderer. |
| 20 | Round-trip | Drie waarborgen: (1) opslaan alleen als de Tiptap-output afwijkt van de baseline (het origineel één keer door Tiptap geserialiseerd); (2) toggle naar de bronmodus; (3) automatisch bronmodus bij inhoud die niet verliesvrij kan (ruwe HTML, footnotes, afwijkende tabellen). |
| 21 | Opslaan | Pickers direct en optimistisch (rollback + toast); tekst bij blur en na 2 s stilte, met een Saving/Saved-indicator; `Cmd+Enter` forceert. |
| 22 | Conflicten | Altijd per veld schrijven; tekstvelden binnen de lock eerst `bd show` en vergelijken met de `base` van de client; bij een afwijking 409 en een conflictbalk (Overschrijven / Hun versie nemen / Mijn tekst kopiëren). Pickers: last-write-wins. |
| 23 | Actor | `BEADS_ACTOR`, anders `git config user.name` van de workspace (gecached); config `humans` (standaard [actor]); de rest krijgt een agent-badge. |
| 24 | bd-versie | Minimaal 1.3.0 (`bd --version`, gecached op de mtime van de binary), blokkerend scherm als die te oud is; één tolerante DTO-mappinglaag; geen bovengrens. |
| 25 | Testen | Alleen PHP: Pest 4 + Testbench; `Process::fake()` en JSON-fixtures van echte bd-output. |
| 26 | Distributie | Eerst lokaal, MIT. `dist/` in git. GitHub Actions uit de skeleton staan klaar, maar draaien pas met een remote. |
| 27 | Dev-omgeving | Testbench-workbench met een demo-workspace (echte bd); `composer serve` als Herd-proces; Vite-HMR in dev-modus. |
| 28 | Ready/Blocked | Afgeleid uit dezelfde export-snapshot in één geteste PHP-klasse; fixtures vergeleken met `bd ready --json`. |
| 29 | Datavolume | Slanke rijen naar de client (zonder lange tekst en comments), niet-gesloten plus 30 dagen gesloten (configureerbaar), "Show older closed"; filteren, groeperen en sorteren client-side; filters in de URL. |
| 30 | Sluiten | Direct, zonder dialoog; "Close with reason…" in het detail; bij een weigering een rollback met de bd-fout; "Force close" alleen bij pinned/gate, na bevestiging. |
| 31 | Relaties | Bewerkbaar: Blocked by / Blocking (blocks), Related (related), Parent (parent-child). Overige typen read-only onder "Other relations" (wel te verwijderen). |
| 32 | Volgorde | Geen handmatige volgorde; slepen alleen tussen statuskolommen; sorteren via weergave-opties (standaard prioriteit, dan updated). |
| 33 | Vastleggen | Deze PRD + bd-epics/issues in deze repo (prefix `bfl`). |

**Afgeleid uit combinaties.** Door #25 en #26 vervalt "CI tegen de nieuwste bd" uit #24.
Compatibiliteit leunt op fixtures; bij een bd-upgrade nemen we die opnieuw op en draaien we de tests.

## Feiten uit het onderzoek

Gemeten met bd 1.3.2 in embedded-Dolt-modus.

- `bd` heeft geen HTTP-API; we starten de CLI als los proces. Eén aanroep kost **~0,55 s**.
- **Elke** aanroep schrijft naar de store, ook lezen: `export` raakt het Dolt-journal
  en de manifest, `show` raakt `.beads/last-touched`. Daarom moeten **alle** aanroepen per
  workspace serieel lopen. Gelijktijdige aanroepen corrumperen de embedded Dolt-store.
- `bd export --json` geeft JSONL met per issue labels, dependencies
  (`issue_id`, `depends_on_id`, `type`) en comments: de hele graaf in één aanroep.
- `bd show <id> --json` geeft een **array**, ook voor één ID. Met `BD_JSON_ENVELOPE=1`
  staat de payload onder `data`. Fouten komen als JSON op stderr (`{"error": …, "hint": …}`) met exit 1;
  een guard-mismatch (`--if-status`/`--if-assignee`) geeft exit 13.
- `revision` verandert bij elke update. Er is geen `--if-revision`-guard.
- `bd statuses --json` en `bd types --json` geven definities met een `category`
  (active / wip / frozen / done), inclusief custom statussen.
- Globale flags: `--sandbox` (geen Dolt-auto-push), `--readonly` (blokkeert writes),
  `--actor`, `-C <dir>`.
- Herd's PHP-FPM leegt de environment (`clear_env` niet uitgezet): in webrequests ontbreken `PATH`,
  `HOME` en `USER`. De binary moet dus met een absoluut pad aangeroepen worden, en `HOME` geven we zelf mee.

## Architectuur

### Pakketindeling

```
~/dev/beads/
  composer.json  package.json  vite.config.ts  tsconfig.json  testbench.yaml
  phpunit.xml.dist  phpstan.neon.dist  pint.json  LICENSE.md  README.md
  config/beads.php
  dist/                         # gecompileerde SPA + .vite/manifest.json (in git)
  resources/views/app.blade.php # SPA-shell
  resources/js/                 # Vue-bron (app.ts, router.ts, pages/, components/, composables/, lib/, lang/en.ts, css/)
  routes/beads.php              # SPA-catch-all + /api/*
  src/
    BeadsServiceProvider.php
    Cli/        BdBinary, BdRunner, BdResult, BdVersion, WorkspaceLock, Exceptions/BdException
    Workspace/  Workspace, WorkspaceHealth (enum), Actor
    Data/       Issue, IssueRow, Dependency, Comment, StatusDefinition, IssueTypeDefinition
    Snapshot/   Fingerprint, Snapshot, SnapshotStore, SnapshotRepository
    Graph/      IssueGraph (relaties, ready/blocked, epic-voortgang)
    Actions/    CreateIssue, UpdateIssue, CloseIssue, ReopenIssue, AddRelation, RemoveRelation, AddComment
    Http/       Controllers/{SpaController, Api/*}, Middleware/Authorize, Requests/*
    Commands/   InstallCommand, PublishCommand
    Support/    AssetManifest
  tests/        Pest (Unit, Feature) + Fixtures/bd-1.3.2/*.json
  workbench/    Testbench-workbench; demo-workspace in workbench/demo (gitignored)
```

Geen extra PHP-dependencies naast `spatie/laravel-package-tools`. De DTO's zijn plain
`readonly` classes met `fromArray()` en `JsonSerializable`. De TS-types staan met de hand in `lib/types.ts`.

### Configuratie (`config/beads.php`)

```php
return [
    'enabled' => env('BEADS_ENABLED', true),
    'path' => env('BEADS_PATH', base_path()),
    'binary' => env('BEADS_BINARY'),            // null = auto-detect
    'actor' => env('BEADS_ACTOR'),               // null = git config user.name
    'humans' => [],                              // leeg = [actor]
    'route' => [
        'prefix' => env('BEADS_ROUTE_PREFIX', 'beads'),
        'domain' => env('BEADS_DOMAIN'),
        'middleware' => ['web'],
    ],
    'poll_interval' => 3,                        // seconden
    'closed_window_days' => 30,
    'lock_timeout' => 10,                        // seconden; daarna 503
    'process_timeout' => 30,                     // seconden
];
```

### CLI-laag

- **BdBinary**: `beads.binary`, anders het eerste uitvoerbare bestand uit
  `/opt/homebrew/bin/bd`, `/usr/local/bin/bd`, `~/go/bin/bd`, `~/.local/bin/bd`.
- **BdVersion**: `bd --version` parsen, cachen op het pad + de mtime van de binary, minimaal `1.3.0`.
- **BdRunner**: Laravel `Process`, args als array (nooit via een shell). Stringwaarden
  altijd als `--flag=value`, zodat een titel die met `-` begint geen flag wordt. Altijd `--json`
  en `--sandbox`, en `--readonly` bij leesaanroepen. Werkmap = workspace. Expliciete env: `HOME`
  (via `posix_getpwuid`), `PATH` (map van de binary + `/usr/bin:/bin`), `BD_JSON_ENVELOPE=1`,
  `BEADS_ACTOR`, `NO_COLOR=1`. De runner pakt de envelope uit en vertaalt stderr-JSON naar een `BdException`
  met een code (`not_found`, `guard_mismatch`, `schema_migration_required`, `failed`).
- **WorkspaceLock**: `flock` op `storage/framework/beads/<sha1(workspace)>.lock`
  rond **elke** bd-aanroep. Bij een timeout volgt een 503 en probeert de client het opnieuw.
- **ID-validatie**: `^[A-Za-z0-9][A-Za-z0-9._-]*$` op alle ID's uit requests.

### Snapshot en live data

1. Een **Fingerprint** is een hash over (pad, mtime, grootte) van alle bestanden onder `.beads/`,
   behalve `*.lock` en `last-touched`. Zonder bd-aanroep.
2. **SnapshotRepository::current()**: blijft de fingerprint gelijk aan die van de opgeslagen snapshot, dan
   gebruiken we de cache. Anders volgt binnen de lock `bd export --json`, het parsen, de content-hash, en het opslaan
   van de snapshot **plus de fingerprint van ná onze eigen export**. Zo veroorzaakt onze eigen leesactie geen
   nieuwe refresh.
3. **Opslag**: `storage/framework/beads/<sha1(workspace)>/` (`snapshot.json`, `meta.json`).
   Dit hangt niet af van de cache-driver van de host.
4. Na elke schrijfactie wordt de snapshot direct ververst, nog binnen dezelfde lock.

### IssueGraph

- Parent/children uit `parent-child`. Blocks in beide richtingen. Overige typen generiek.
- **Ready**: status in categorie `active`, geen open blocker (blocks-dependency naar een issue
  dat niet in categorie `done` zit), geen `defer_until` in de toekomst, geen
  systeemtypes. `in_progress`, `blocked`, `deferred` en `hooked` tellen niet mee. Dit wordt geverifieerd tegen
  `bd ready --json`-fixtures.
- **Blocked**: status `blocked` óf een open blocker. Bij elke issue staat waarop hij wacht.
- **Epic-voortgang**: het aantal kinderen in categorie done gedeeld door het totaal aantal kinderen.
- Systeemtypes (message, molecule, gate, event) zijn standaard verborgen.

### HTTP-API (onder `/{prefix}/api`)

| Methode | Pad | Doel |
|---------|-----|------|
| GET | `bootstrap` | actor, humans, statussen, types, bd-versie, health-state, poll-interval, assets-status |
| GET | `snapshot` | slanke rijen + relaties; `?closed=all`; `ETag`/`If-None-Match` → 304 |
| GET | `issues/{id}` | volledig detail uit de snapshot (tekstvelden, comments, relaties) |
| POST | `issues` | aanmaken (`--title`, `--type`, `--priority`, `--status`, `--description`, `--assignee`, `--labels`, `--parent`) |
| PATCH | `issues/{id}` | per veld; tekstvelden met `base` → 409 `{field, theirs}` bij een afwijking |
| POST | `issues/{id}/close` | `reason?`, `force?` (alleen pinned/gate) |
| POST | `issues/{id}/reopen` | heropenen |
| POST | `issues/{id}/relations` | `{type: blocks\|related\|parent-child, target, direction}` |
| DELETE | `issues/{id}/relations` | `{type, target, direction}` |
| POST | `issues/{id}/comments` | comment toevoegen |

Elke schrijf-respons bevat de nieuwe ETag, zodat de client niet hoeft te wachten op de volgende poll.

**Health-states** (die de UI als volledig scherm toont): `binary_missing`, `version_too_old`,
`workspace_missing`, `schema_migration_required`, `ok`.

### Toegang en beveiliging

- De provider registreert routes, Gate en commands alleen als `app()->isLocal()` en `beads.enabled`.
  Publishable resources registreren we altijd (onschuldig, en nodig voor de composer-hook).
- De middleware is `beads.route.middleware` (standaard `web`, dus sessie + CSRF), plus `Authorize`
  (Gate `viewBeads`, met een nullable user; het package definieert hem alleen als de host dat niet al
  gedaan heeft).
- Er is nooit een shell, ID's worden gevalideerd, en elke waarde gaat als `--flag=value`. Tiptap rendert via het
  ProseMirror-schema, dus er wordt nooit ruwe HTML geïnjecteerd.

### Assets

- Vite bouwt naar `dist/` met `.vite/manifest.json` en gehashte bestandsnamen.
- `beads:publish` publiceert dat naar `public/vendor/beads` (`--force`). `beads:install` publiceert
  config + assets en biedt aan om `@php artisan vendor:publish --tag=beads-assets --force` toe te voegen aan
  `post-update-cmd` in de composer.json van de host.
- De Blade-shell leest het **gepubliceerde** manifest. Wijkt de hash af van `dist/.vite/manifest.json`
  (of ontbreekt hij), dan toont de shell een banner met het commando.
- Dev-modus: bestaat het hot-bestand van de package-Vite, dan laadt de shell de Vite-devserver (HMR).

### Frontend

- **Stack**: Vue 3.5, TypeScript, Vue Router (history onder de prefix), Tailwind 4,
  shadcn-vue/reka-ui, `lucide-vue-next`, `@fontsource-variable/inter`, `@tanstack/vue-query`
  (polling, ETag, optimistische mutaties met rollback), `@vueuse/core`, Tiptap 3
  (`@tiptap/vue-3`, starter-kit, `@tiptap/markdown`). Board-drag via native HTML5 DnD.
- **Navigatie (sidebar)**: Issues (All / Active / Backlog), My issues (assignee = actor),
  Ready, Blocked, Board, Epics. Thema-toggle in het gebruikersmenu.
- **Lijst**: dichte rijen van 32 px, gegroepeerd per status in categorie-volgorde. Filters (status,
  prioriteit, type, label, assignee, parent) en weergave-opties (groeperen, sorteren) in de URL-query.
  Zoeken op ID en titel. Een "Show older closed"-toggle.
- **Detail**: side-peek vanuit lijst en board, en een volledige pagina `/issue/{id}`. Een properties-paneel
  met pickers; titel en markdownvelden met Tiptap (inclusief de waarborgen van #20); een relaties-sectie
  met een issue-zoek-picker; een comments-thread; Close with reason / Reopen / Force close.
- **Aanmaken**: een modal (`C`) met alle kernvelden, "Create more", `Cmd+Enter`, en voorinvulling
  vanuit de context (kolom → status, epic/parent).
- **Board**: kolommen per status (dynamisch); slepen wijzigt de status of sluit het issue.
- **Epics**: lijst met voortgangsbalk en uitklapbare kinderen; sub-issue toevoegen.
- **Ready/Blocked**: de lijstcomponent met een vast filter; Blocked toont waarop een issue wacht.
- **Iconen**: status per categorie, prioriteit (Linear), type, labels, een agent-badge (auteur niet
  in `humans`) en een blocked-by-indicator.
- **i18n**: alle UI-teksten via `t('key')` uit `lang/en.ts`.

## Teststrategie

- Pest 4 + Orchestra Testbench, Larastan en Pint.
- **Unit**: BdBinary-resolutie, het bouwen van args (`--flag=value`, sandbox/readonly, env), envelope- en
  foutmapping, versievergelijking, DTO-mapping tegen fixtures (show-array en -object, ontbrekende en
  onbekende velden), Fingerprint-gating (eigen export triggert niets), IssueGraph tegen
  `bd ready`-fixtures, en de conflictdetectie.
- **Feature**: geen routes buiten `local` of bij `BEADS_ENABLED=false`, Gate, CSRF bij writes,
  validatie, ETag/304, 409-conflict, health-states, en de commands `beads:install` en `beads:publish`.
- **Fixtures**: één keer opgenomen van een echte bd 1.3.2-demoworkspace, in
  `tests/Fixtures/bd-1.3.2/`. Bij een nieuwe bd-versie nemen we ze opnieuw op.

## Ontwikkelomgeving

- Testbench-workbench. `composer demo` maakt met de echte bd een demo-workspace in
  `workbench/demo` aan (epics, taken, bugs, labels, blocks/related, comments, een gesloten
  issue). `testbench.yaml` zet `BEADS_PATH` op die map.
- `composer serve` draait als Herd-proces; `npm run dev` levert HMR.
- De package-repo gebruikt zelf bd (prefix `bfl`) voor het bouwplan.

## Bouwplan

Het bouwplan staat als epics en issues met dependencies in de bd-workspace van deze repo
(`bd ready` toont wat oppakbaar is). De fasen:

1. **Fundament**: skeleton, config en provider, frontend-toolchain, Blade-shell, publish/install, workbench.
2. **CLI-laag**: binary, versie, runner, lock, workspace-health, actor.
3. **Snapshot en domein**: DTO's en fixtures, fingerprint-snapshot, IssueGraph, lees-API.
4. **Schrijfacties**: create, update met conflictcheck, close/reopen, relaties en comments.
5. **UI-shell en lijst**: shell, datalaag, iconen, lijst, sneltoetsen en Cmd+K.
6. **Detail en bewerken**: detail, Tiptap met waarborgen, conflictbalk, relaties en comments.
7. **Views**: Board, Epics, Ready/Blocked.
8. **Aanmaken**: create-modal.
9. **Afronding v1**: README, statische analyse, dist gebouwd, CI-workflows klaar.
