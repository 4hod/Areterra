<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 5 — states that let things cascade.
 *
 * Nothing could react to an absence because an absence was not a record: a
 * member who did not come simply had no attendance row. You cannot fire an
 * event for the absence of a row. Same for transport — a run was either
 * created or deleted, with no way to say "went out, nobody there".
 *
 * Additive. Existing rows default to the behaviour they already have.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // expected = scheduled, not yet resolved
            // present  = checked in
            // absent   = expected and did not come (this is the new one)
            $table->string('status')->default('expected')->after('date');
            $table->string('absence_reason')->nullable()->after('status');
            $table->boolean('absence_notified')->default(false)->after('absence_reason');
            $table->foreignId('recorded_by')->nullable()->after('absence_notified')->constrained('users')->nullOnDelete();
        });

        Schema::table('transport_runs', function (Blueprint $table) {
            // collected     — travelled this leg, chargeable
            // not_collected — didn't take this leg, but is/will be in (not chargeable)
            // absent        — not in at all today (cancels both legs, no charge)
            // Existing rows default to 'collected': a run row has always meant
            // the journey happened.
            $table->string('outcome')->default('collected')->after('phase');
            $table->string('outcome_reason')->nullable()->after('outcome');
        });

        // Transport charges must be reversible for the same reason ledger
        // entries are: undoing a collection currently leaves the charge behind.
        Schema::table('transport_ledger', function (Blueprint $table) {
            $table->foreignId('reverses_id')->nullable()->constrained('transport_ledger')->nullOnDelete();
            $table->string('reversal_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transport_ledger', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reverses_id');
            $table->dropColumn('reversal_reason');
        });

        Schema::table('transport_runs', fn (Blueprint $t) => $t->dropColumn(['outcome', 'outcome_reason']));

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropColumn(['status', 'absence_reason', 'absence_notified']);
        });
    }
};
