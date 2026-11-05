<?php

declare(strict_types=1);

namespace Codeberry\Beads\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class Authorize
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Gate::allows('viewBeads'), 403);

        return $next($request);
    }
}
