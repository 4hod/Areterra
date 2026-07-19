<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timeclock_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('clock_in');
            $table->dateTime('clock_out')->nullable();
            $table->unsignedInteger('break_minutes')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['user_id', 'clock_in']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timeclock_entries');
    }
};
