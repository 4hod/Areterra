<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('checked_in')->default(false);
            $table->dateTime('checked_in_at')->nullable();
            $table->string('arrival_mood')->nullable(); // happy|neutral|sad|angry|anxious
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['member_id', 'date']);
        });

        Schema::create('end_of_day_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('user_id')->constrained();
            $table->string('arrival_mood')->nullable();
            $table->string('end_mood')->nullable();
            $table->string('session_type')->nullable();
            $table->text('activities')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('concern')->default(false);
            $table->text('concern_detail')->nullable();
            $table->timestamps();
            $table->unique(['member_id', 'date']);
        });

        Schema::create('transport_runs', function (Blueprint $table) {
            $table->id();
            $table->date('run_date');
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('phase')->default('morning'); // morning|afternoon
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestamps();
            $table->unique(['run_date', 'member_id', 'phase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_runs');
        Schema::dropIfExists('end_of_day_records');
        Schema::dropIfExists('attendances');
    }
};
