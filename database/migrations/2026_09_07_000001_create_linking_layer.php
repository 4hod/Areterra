<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 2 — the linking layer (points 10 and 11).
 *
 * Deliberately additive. Nothing existing is altered or dropped:
 *  - `documents` gains a nullable polymorphic owner. Every existing row keeps
 *    working with a null owner and continues to behave exactly as it does now.
 *  - `tasks` is a new table. `maintenance_tasks` is untouched and keeps running;
 *    migrating it across is a later, separate decision.
 *
 * down() removes only what up() added, so this is safe to roll back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // A vet report belongs to an animal; a funding letter to a member.
            // Null means "general filing", which is what everything is today.
            $table->nullableMorphs('attachable', 'documents_attachable_idx');
            $table->json('consent')->nullable();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium'); // low|medium|high
            $table->date('due_date')->nullable();

            // Attach to a member, animal, grant, invoice, compliance item — anything.
            $table->nullableMorphs('taskable', 'tasks_taskable_idx');

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();

            // When set, recording the underlying action auto-completes the task —
            // "Review Rico's medication" disappears once the review is recorded.
            $table->string('completes_on_event')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['completed_at', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');

        Schema::table('documents', function (Blueprint $table) {
            $table->dropMorphs('attachable');
            $table->dropColumn('consent');
        });
    }
};
