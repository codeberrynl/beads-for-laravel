<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | The UI, its routes and its commands are only registered when the app
    | runs in the "local" environment and this option is not false.
    |
    */

    'enabled' => env('BEADS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Workspace
    |--------------------------------------------------------------------------
    |
    | The directory that contains the .beads workspace. Defaults to the root
    | of the host application.
    |
    */

    'path' => env('BEADS_PATH', base_path()),

    /*
    |--------------------------------------------------------------------------
    | bd binary
    |--------------------------------------------------------------------------
    |
    | Absolute path to the bd binary. When null, the package looks in the
    | usual install locations. Web requests under Herd/PHP-FPM run without a
    | PATH, so an absolute path is always used.
    |
    */

    'binary' => env('BEADS_BINARY'),

    /*
    |--------------------------------------------------------------------------
    | Actor and humans
    |--------------------------------------------------------------------------
    |
    | The actor is recorded by bd for every change made from the UI. When
    | null, `git config user.name` of the workspace is used. Authors that are
    | not listed in "humans" get an agent badge. An empty list means
    | [actor].
    |
    */

    'actor' => env('BEADS_ACTOR'),

    'humans' => [],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */

    'route' => [
        'prefix' => env('BEADS_ROUTE_PREFIX', 'beads'),
        'domain' => env('BEADS_DOMAIN'),
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Live data
    |--------------------------------------------------------------------------
    |
    | poll_interval: seconds between snapshot polls from the browser.
    | closed_window_days: closed issues newer than this are sent by default.
    | lock_timeout: seconds to wait for the workspace lock (then a 503).
    | process_timeout: seconds a single bd call may take.
    |
    */

    'poll_interval' => 3,

    'closed_window_days' => 30,

    'lock_timeout' => 10,

    'process_timeout' => 30,

];
