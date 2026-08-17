<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->date('joined_date')->nullable()->after('dob');
            $table->text('care_requirements')->nullable()->after('joined_date');
            $table->text('feeding_notes')->nullable()->after('care_requirements');
        });
    }

    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->dropColumn(['joined_date', 'care_requirements', 'feeding_notes']);
        });
    }
};
