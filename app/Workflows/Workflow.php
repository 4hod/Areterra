<?php

namespace App\Workflows;

use App\Exceptions\WorkflowException;
use Illuminate\Support\Facades\DB;

/**
 * Base class for any multi-step action that writes to more than one table.
 *
 * The contract is deliberately narrow:
 *
 *   1. problems()  — pure inspection, no writes. Returns plain-English strings.
 *   2. execute()   — the writes. Only ever called when problems() is empty.
 *   3. run()       — wraps execute() in a transaction, so a mid-way failure
 *                    leaves nothing behind.
 *
 * A workflow that half-succeeds is a bug this class exists to make impossible.
 * Controllers should call run() and never touch DB::transaction themselves.
 */
abstract class Workflow
{
    /**
     * Pre-flight checks. Return an empty array when the action is safe to run.
     *
     * @return array<int, string>
     */
    abstract public function problems(): array;

    /** The actual work. Runs inside a transaction; never call directly. */
    abstract protected function execute(): mixed;

    /** Shown to the user when problems() is non-empty. */
    protected function summary(): string
    {
        return 'This could not be completed.';
    }

    /** Extra detail for the server-side log only. */
    protected function context(): array
    {
        return [];
    }

    public function passes(): bool
    {
        return $this->problems() === [];
    }

    public function run(): mixed
    {
        $problems = $this->problems();

        if ($problems !== []) {
            throw new WorkflowException($problems, $this->summary(), $this->context());
        }

        return DB::transaction(fn () => $this->execute());
    }
}
