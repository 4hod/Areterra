<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

// Writes an audit trail row for every create/update/delete on registered
// models. Values of encrypted attributes are redacted — the log records
// that a field changed, never its plaintext.
class AuditLogObserver
{
    public function created(Model $model): void
    {
        $this->log('created', $model, null);
    }

    public function updated(Model $model): void
    {
        $changes = $this->redact($model, array_keys($model->getChanges()));
        if ($changes !== []) {
            $this->log('updated', $model, $changes);
        }
    }

    public function deleted(Model $model): void
    {
        $this->log('deleted', $model, null);
    }

    private function redact(Model $model, array $keys): array
    {
        $keys = array_values(array_diff($keys, ['updated_at', 'created_at', 'remember_token', 'password']));

        return collect($keys)->mapWithKeys(function ($key) use ($model) {
            $cast = $model->getCasts()[$key] ?? null;
            $sensitive = $cast !== null && str_starts_with($cast, 'encrypted');

            return [$key => $sensitive ? '[redacted]' : $model->getAttribute($key)];
        })->all();
    }

    private function log(string $action, Model $model, ?array $changes): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => class_basename($model),
            'subject_id' => $model->getKey(),
            'changes' => $changes,
        ]);
    }
}
