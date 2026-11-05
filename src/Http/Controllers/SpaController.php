<?php

declare(strict_types=1);

namespace Codeberry\Beads\Http\Controllers;

use Illuminate\Contracts\View\View;

final class SpaController
{
    public function __invoke(): View
    {
        return view('beads::app');
    }
}
