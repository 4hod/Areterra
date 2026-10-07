<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_checks', function (Blueprint $table) {
            $table->string('phase')->nullable()->after('checked_at');
            $table->index(['phase', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_checks', function (Blueprint $table) {
            $table->dropIndex(['phase', 'checked_at']);
            $table->dropColumn('phase');
        });
    }
};
