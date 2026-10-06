<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impact_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_goal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users');
            $table->dateTime('observed_at');
            $table->string('mood_before')->nullable();
            $table->string('mood_after')->nullable();
            $table->unsignedTinyInteger('engagement_rating')->nullable();
            $table->unsignedTinyInteger('independence_rating')->nullable();
            $table->text('outcome_note');
            $table->json('evidence_tags')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'observed_at']);
            $table->index(['animal_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impact_entries');
    }
};
