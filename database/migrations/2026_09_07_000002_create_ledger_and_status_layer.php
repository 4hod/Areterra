<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 3 — finance ledger, status history, notification suppression.
 * Additive only; down() removes exactly what up() adds.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Point 4: costs and income ORIGINATE from source records and are posted
        // here. Finance aggregates the ledger instead of you typing totals.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->string('direction');            // income|expense
            $table->string('category');             // staff_costs|animal_costs|grant_spend|...
            $table->string('description');
            $table->decimal('amount', 12, 2);

            // Where it came from: a PayrollPeriod, GrantExpenditure, MemberInvoice,
            // VetRecord, MaintenanceTask. Null only for genuine manual entries.
            $table->nullableMorphs('source', 'ledger_source_idx');

            // Restricted funds must not inflate unrestricted reserves.
            $table->foreignId('grant_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('restricted')->default(false);

            // Point 17: corrections reverse, they don't destroy.
            $table->foreignId('reverses_id')->nullable()->constrained('ledger_entries')->nullOnDelete();
            $table->text('reversal_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['entry_date', 'direction']);
            $table->index(['category', 'entry_date']);
        });

        // Point 16: who moved this from Draft to Approved, when, and why.
        Schema::create('status_transitions', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('from')->nullable();
            $table->string('to');
            $table->text('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // Point 12: notifications derive from data, so we only need to remember
        // what has already been sent — not maintain a separate reminder list.
        Schema::create('due_notices', function (Blueprint $table) {
            $table->id();
            $table->string('kind');                 // dbs_expiring|invoice_overdue|...
            $table->nullableMorphs('subject', 'due_notices_subject_idx');
            $table->date('due_on')->nullable();
            $table->dateTime('notified_at')->nullable();
            $table->timestamps();
            $table->unique(['kind', 'subject_type', 'subject_id', 'due_on'], 'due_notices_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('due_notices');
        Schema::dropIfExists('status_transitions');
        Schema::dropIfExists('ledger_entries');
    }
};
