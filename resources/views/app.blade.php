<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ $config['csrfToken'] }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Beads</title>
    <script>
        (function () {
            // Dark-first: dark unless the system (or the saved choice) says light.
            var theme = 'system';
            try { theme = localStorage.getItem('beads-theme') || 'system'; } catch (e) {}
            var light = theme === 'light' || (theme === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches);
            document.documentElement.classList.toggle('dark', !light);
        })();
    </script>
    <script>window.Beads = @json($config);</script>
    {{ $assets->tags() }}
</head>
<body class="h-full overflow-hidden">
    @if ($config['assets']['stale'])
        <div id="beads-stale-assets" role="alert" style="position:fixed;inset:auto 1rem 1rem auto;z-index:60;max-width:26rem;padding:.75rem 1rem;border-radius:.5rem;font:13px/1.45 ui-sans-serif,system-ui,sans-serif;background:#2a2116;color:#f6e7c8;border:1px solid #6b5326;box-shadow:0 8px 24px rgba(0,0,0,.35)">
            <strong style="display:block;margin-bottom:.25rem">{{ $config['assets']['published'] ? 'The Beads assets are out of date.' : 'The Beads assets are not published.' }}</strong>
            Run <code style="padding:.1rem .3rem;border-radius:.25rem;background:rgba(255,255,255,.1)">{{ $config['assets']['command'] }}</code> and reload.
        </div>
    @endif
    <div id="beads-app" class="h-full"></div>
</body>
</html>
