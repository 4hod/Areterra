<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 6 — the answers Ethan gave. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        // "If a session's cancelled — no staff able to come in — all of it is
        // cancelled, transport and everything." One record for the whole day.
        Schema::create('day_cancellations', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('reason');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // "Archive their welfare history, but keep it so we can still access it."
        Schema::table('animals', function (Blueprint $table) {
            $table->dateTime('archived_at')->nullable();
            $table->string('archived_reason')->nullable();
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->string('status')->default('scheduled');   // scheduled|completed|cancelled
            $table->string('cancellation_reason')->nullable();
        });

        // "Just mention it on their file" — the leaving balance, frozen.
        Schema::table('members', function (Blueprint $table) {
            $table->decimal('closing_transport_balance', 8, 2)->nullable();
            $table->dateTime('deactivated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('members', fn (Blueprint $t) => $t->dropColumn(['closing_transport_balance', 'deactivated_at']));
        Schema::table('activities', fn (Blueprint $t) => $t->dropColumn(['status', 'cancellation_reason']));
        Schema::table('animals', fn (Blueprint $t) => $t->dropColumn(['archived_at', 'archived_reason']));
        Schema::dropIfExists('day_cancellations');
    }
};
