<?php

declare(strict_types=1);

namespace Codeberry\Beads\Support;

use Illuminate\Support\HtmlString;

/**
 * Reads the Vite manifest of the published assets (public/vendor/beads)
 * and compares it with the package's dist/, so the shell can warn about
 * stale assets. When the package's Vite dev server runs (hot file), the
 * shell loads from there instead (HMR).
 */
final class AssetManifest
{
    public const ENTRY = 'resources/js/app.ts';

    public const MANIFEST = '.vite/manifest.json';

    public const PUBLISH_COMMAND = 'php artisan beads:publish';

    public function __construct(
        private readonly string $distPath,
        private readonly string $publicPath,
        private readonly string $hotFile,
        private readonly string $publicUrl = '/vendor/beads',
    ) {}

    public function hotUrl(): ?string
    {
        if (! is_file($this->hotFile)) {
            return null;
        }

        $url = trim((string) file_get_contents($this->hotFile));

        return $url !== '' ? rtrim($url, '/') : null;
    }

    public function packageHash(): ?string
    {
        return $this->hashOf($this->distPath.'/'.self::MANIFEST);
    }

    public function publishedHash(): ?string
    {
        return $this->hashOf($this->publicPath.'/'.self::MANIFEST);
    }

    public function isPublished(): bool
    {
        return $this->publishedHash() !== null;
    }

    public function isStale(): bool
    {
        if ($this->hotUrl() !== null) {
            return false;
        }

        $published = $this->publishedHash();

        return $published === null || $published !== $this->packageHash();
    }

    /**
     * @return array{hot: bool, published: bool, stale: bool, command: string}
     */
    public function status(): array
    {
        return [
            'hot' => $this->hotUrl() !== null,
            'published' => $this->isPublished(),
            'stale' => $this->isStale(),
            'command' => self::PUBLISH_COMMAND,
        ];
    }

    /**
     * Script and style tags for the SPA entry.
     */
    public function tags(string $entry = self::ENTRY): HtmlString
    {
        $hot = $this->hotUrl();

        if ($hot !== null) {
            return new HtmlString(implode("\n", [
                '<script type="module" src="'.e($hot.'/@vite/client').'"></script>',
                '<script type="module" src="'.e($hot.'/'.$entry).'"></script>',
            ]));
        }

        $manifest = JsonFile::read($this->publicPath.'/'.self::MANIFEST);
        $chunk = is_array($manifest) ? ($manifest[$entry] ?? null) : null;

        if (! is_array($chunk) || ! is_string($chunk['file'] ?? null)) {
            return new HtmlString('');
        }

        $tags = [];

        foreach ($this->cssFor($manifest, $entry) as $css) {
            $tags[] = '<link rel="stylesheet" href="'.e($this->url($css)).'">';
        }

        $tags[] = '<script type="module" src="'.e($this->url($chunk['file'])).'"></script>';

        return new HtmlString(implode("\n", $tags));
    }

    /**
     * @param  array<mixed>  $manifest
     * @param  array<string, true>  $seen
     * @return list<string>
     */
    private function cssFor(array $manifest, string $key, array &$seen = []): array
    {
        if (isset($seen[$key]) || ! is_array($manifest[$key] ?? null)) {
            return [];
        }

        $seen[$key] = true;
        $chunk = $manifest[$key];
        $css = [];

        foreach (is_array($chunk['imports'] ?? null) ? $chunk['imports'] : [] as $import) {
            if (is_string($import)) {
                $css = [...$css, ...$this->cssFor($manifest, $import, $seen)];
            }
        }

        foreach (is_array($chunk['css'] ?? null) ? $chunk['css'] : [] as $file) {
            if (is_string($file)) {
                $css[] = $file;
            }
        }

        return array_values(array_unique($css));
    }

    private function url(string $file): string
    {
        return asset(trim($this->publicUrl, '/').'/'.ltrim($file, '/'));
    }

    private function hashOf(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $hash = sha1_file($path);

        return $hash !== false ? $hash : null;
    }
}
