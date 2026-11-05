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
    <div id="beads-app" class="h-full"></div>
</body>
</html>
