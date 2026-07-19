<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_prefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('push_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            // Per-category opt-outs, e.g. {"announcements": false}
            $table->json('categories')->nullable();
            $table->timestamps();
        });

        // Dedupe log so scheduled reminders fire at most once per day.
        Schema::create('notification_log', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // e.g. register-reminder:2026-07-20
            $table->dateTime('sent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_log');
        Schema::dropIfExists('notification_prefs');
    }
};
