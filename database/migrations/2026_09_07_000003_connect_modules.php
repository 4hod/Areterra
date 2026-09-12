<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 4 — the joins that were missing.
 *
 * Modules could not talk to each other because in several places there was no
 * column to talk through. Every addition here is nullable, so existing rows
 * stay valid and nothing is backfilled.
 *
 * Each block notes the flow it unblocks.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Transport had no vehicle. Vehicle costs could never reach the run that
        // incurred them, which is why `vehicles` was an island.
        Schema::table('transport_runs', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('member_id')->constrained()->nullOnDelete();
            $table->decimal('mileage', 8, 1)->nullable()->after('phase');
            $table->boolean('chargeable')->default(true)->after('mileage');
        });

        // An incident could not say who or what it was about — only free-text
        // `persons_involved`. This is the "logged once, reflected everywhere" case.
        Schema::table('incidents', function (Blueprint $table) {
            $table->nullableMorphs('subject', 'incidents_subject_idx');
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
        });

        // Sessions had no participants, so a session could never feed a member's
        // timeline, outcomes or impact reporting.
        Schema::create('activity_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('attended')->default(true);
            $table->text('outcome_notes')->nullable();
            $table->timestamps();
            $table->unique(['activity_id', 'member_id']);
        });

        // Attendance could not point at the session it was attendance *for*.
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('activity_id')->nullable()->after('member_id')->constrained()->nullOnDelete();
        });

        // Invoices carried a typed amount with no link to what they cover, so
        // attendance -> expected income -> invoice was entirely manual.
        Schema::table('member_invoices', function (Blueprint $table) {
            $table->integer('period_index')->nullable()->after('member_id');
            $table->date('covers_start')->nullable()->after('period_index');
            $table->date('covers_end')->nullable()->after('covers_start');
            $table->boolean('generated')->default(false)->after('status');
        });

        // Identity: the same person could exist as a roster member AND a user
        // with nothing joining them. Payroll matched them by name.
        Schema::table('staff_roster', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained()->nullOnDelete();
        });

        // Vehicle MOT/service dates duplicated compliance. Let compliance own the
        // deadline and point at the vehicle.
        Schema::table('compliance_items', function (Blueprint $table) {
            $table->nullableMorphs('relates_to', 'compliance_relates_idx');
        });
    }

    public function down(): void
    {
        Schema::table('compliance_items', fn (Blueprint $t) => $t->dropMorphs('relates_to'));

        Schema::table('staff_roster', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('member_invoices', function (Blueprint $table) {
            $table->dropColumn(['period_index', 'covers_start', 'covers_end', 'generated']);
        });

        Schema::table('attendances', fn (Blueprint $t) => $t->dropConstrainedForeignId('activity_id'));

        Schema::dropIfExists('activity_participants');

        Schema::table('incidents', function (Blueprint $table) {
            $table->dropMorphs('subject');
            $table->dropConstrainedForeignId('vehicle_id');
        });

        Schema::table('transport_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropColumn(['mileage', 'chargeable']);
        });
    }
};
