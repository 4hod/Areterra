<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thrown when a workflow's pre-flight checks fail.
 *
 * Carries a list of plain-English problems ("Vanessa Doe has no hourly rate
 * configured for this pay period") rather than a stack trace, and renders
 * itself back to the user instead of becoming a 500.
 */
class WorkflowException extends RuntimeException
{
    /**
     * @param  array<int, string>  $problems
     */
    public function __construct(
        public readonly array $problems,
        public readonly string $summary = 'This could not be completed.',
        public readonly array $context = [],
    ) {
        parent::__construct($summary.' '.implode(' ', $problems));
    }

    /**
     * Logged server-side with context so a failure is diagnosable after the fact,
     * without the user ever seeing an opaque error.
     */
    public function report(): void
    {
        Log::warning('Workflow blocked: '.$this->summary, [
            'problems' => $this->problems,
            'context' => $this->context,
            'user_id' => auth()->id(),
        ]);
    }

    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->summary,
                'problems' => $this->problems,
            ], 422);
        }

        return back()
            ->with('error', $this->summary)
            ->with('problems', $this->problems);
    }
}
