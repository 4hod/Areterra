<?php

namespace App\Http\Middleware;

use App\Services\TodayChecklist;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceDailyWorkflowOrder
{
    public function __construct(private readonly TodayChecklist $checklist) {}

    public function handle(Request $request, Closure $next, string $step): Response
    {
        if (! $this->checklist->canAccess($step, today())) {
            return redirect()->route('today')->with(
                'error',
                'Please complete the current Today job before moving on.',
            );
        }

        return $next($request);
    }
}
