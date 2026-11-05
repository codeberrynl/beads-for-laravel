<?php

declare(strict_types=1);

namespace Codeberry\Beads\Http\Middleware;

use Closure;
use Codeberry\Beads\Workspace\HealthCheck;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API calls that need bd answer with the health payload (503) when the
 * workspace is not ready, so the UI can show the matching screen.
 */
final class EnsureHealthy
{
    public function __construct(private readonly HealthCheck $health) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $state = $this->health->state();

        if (! $state->isOk()) {
            return $this->health->response($state);
        }

        return $next($request);
    }
}
