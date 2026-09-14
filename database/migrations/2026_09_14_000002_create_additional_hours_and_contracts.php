<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('contracted_hours', 5, 2)->nullable();
        });

        Schema::create('additional_hours_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->unsignedInteger('minutes');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_hours_entries');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('contracted_hours'));
    }
};
