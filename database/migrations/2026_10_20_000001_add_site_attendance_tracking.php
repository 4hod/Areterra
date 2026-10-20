<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_attendances', function (Blueprint $table) {
            $table->timestamp('checked_in_at')->nullable()->after('present');
            $table->timestamp('checked_out_at')->nullable()->after('checked_in_at');
        });

        Schema::create('visitor_attendances', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name');
            $table->string('organisation')->nullable();
            $table->string('visiting')->nullable();
            $table->timestamp('checked_in_at');
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['date', 'checked_out_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_attendances');

        Schema::table('staff_attendances', function (Blueprint $table) {
            $table->dropColumn(['checked_in_at', 'checked_out_at']);
        });
    }
};
