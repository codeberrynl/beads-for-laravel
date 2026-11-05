<?php

declare(strict_types=1);

namespace Codeberry\Beads\Http\Controllers;

use Codeberry\Beads\Support\AssetManifest;
use Illuminate\Contracts\View\View;

final class SpaController
{
    public function __invoke(AssetManifest $assets): View
    {
        $basePath = rtrim((string) parse_url(route('beads.spa', [], false), PHP_URL_PATH), '/');

        return view('beads::app', [
            'assets' => $assets,
            'config' => [
                'basePath' => $basePath,
                'apiPath' => $basePath.'/api',
                'csrfToken' => csrf_token(),
                'pollInterval' => (int) config('beads.poll_interval', 3),
                'assets' => $assets->status(),
            ],
        ]);
    }
}
