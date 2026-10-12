<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_of_day_records', function (Blueprint $table) {
            $table->string('participation_level')->nullable()->after('activities');
            $table->text('achievement')->nullable()->after('participation_level');
            $table->text('support_notes')->nullable()->after('achievement');
        });
    }

    public function down(): void
    {
        Schema::table('end_of_day_records', function (Blueprint $table) {
            $table->dropColumn(['participation_level', 'achievement', 'support_notes']);
        });
    }
};
