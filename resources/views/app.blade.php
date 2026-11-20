<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ $config['csrfToken'] }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Beads</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2NCIgaGVpZ2h0PSI2NCIgdmlld0JveD0iMCAwIDY0IDY0IiByb2xlPSJpbWciIGFyaWEtbGFiZWw9IkJlYWRzIGZvciBMYXJhdmVsIj48ZyB0cmFuc2Zvcm09InRyYW5zbGF0ZSgwIDApIHNjYWxlKDEuMCkiPjxyZWN0IHdpZHRoPSI2NCIgaGVpZ2h0PSI2NCIgcng9IjE1IiBmaWxsPSIjMUMxQzI0Ii8+PHJlY3QgeD0iMC41IiB5PSIwLjUiIHdpZHRoPSI2MyIgaGVpZ2h0PSI2MyIgcng9IjE0LjUiIGZpbGw9Im5vbmUiIHN0cm9rZT0iIzJDMkMzOCIvPjxsaW5lIHgxPSI3IiB5MT0iNTIiIHgyPSI1NyIgeTI9IjEyIiBzdHJva2U9IiM0QjRCNUMiIHN0cm9rZS13aWR0aD0iMi40IiBzdHJva2UtbGluZWNhcD0icm91bmQiLz48Y2lyY2xlIGN4PSIxOCIgY3k9IjQzLjIiIHI9IjguNiIgZmlsbD0iIzFDMUMyNCIvPjxjaXJjbGUgY3g9IjE4IiBjeT0iNDMuMiIgcj0iNyIgZmlsbD0iIzZENkFGNSIvPjxwYXRoIGQ9Ik0xNC42IDQzLjQgTDE3IDQ1LjggTDIxLjYgNDAuOCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjRkZGRkZGIiBzdHJva2Utd2lkdGg9IjIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCIvPjxjaXJjbGUgY3g9IjMyIiBjeT0iMzIiIHI9IjguNiIgZmlsbD0iIzFDMUMyNCIvPjxjaXJjbGUgY3g9IjMyIiBjeT0iMzIiIHI9IjYuMSIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjQTc4QkZBIiBzdHJva2Utd2lkdGg9IjEuOSIvPjxwYXRoIGQ9Ik0zMiAyNy42IEE0LjQgNC40IDAgMCAxIDMyIDM2LjQgWiIgZmlsbD0iI0E3OEJGQSIvPjxjaXJjbGUgY3g9IjQ2IiBjeT0iMjAuOCIgcj0iOC42IiBmaWxsPSIjMUMxQzI0Ii8+PGNpcmNsZSBjeD0iNDYiIGN5PSIyMC44IiByPSI2LjEiIGZpbGw9Im5vbmUiIHN0cm9rZT0iI0M5QzdERCIgc3Ryb2tlLXdpZHRoPSIxLjkiLz48L2c+PC9zdmc+">
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
