<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('working_days')->nullable()->after('contracted_hours');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->boolean('start_half_day')->default(false)->after('end_date');
            $table->boolean('end_half_day')->default(false)->after('start_half_day');
            $table->json('days_by_year')->nullable()->after('days');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['start_half_day', 'end_half_day', 'days_by_year']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('working_days');
        });
    }
};
