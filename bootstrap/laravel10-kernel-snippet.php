<?php

/**
 * Laravel 10 only — copy into app/Http/Kernel.php
 *
 * protected $middlewareAliases = [
 *     'role' => \App\Http\Middleware\EnsureRole::class,
 * ];
 *
 * In the "web" group also append:
 *     \App\Http\Middleware\TrackLastSeen::class,
 */

return [
    'role' => \App\Http\Middleware\EnsureRole::class,
    'seen' => \App\Http\Middleware\TrackLastSeen::class,
];
