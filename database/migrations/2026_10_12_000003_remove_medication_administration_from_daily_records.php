<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_of_day_records', function (Blueprint $table) {
            $table->dropColumn(['medication_given', 'medication_notes']);
        });

        DB::table('member_consents')->where('consent_type', 'medication')->delete();

        DB::table('policies')
            ->where('title', 'Medication Administration')
            ->whereNull('deleted_at')
            ->update(['status' => 'archived', 'deleted_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('end_of_day_records', function (Blueprint $table) {
            $table->boolean('medication_given')->default(false)->after('toileting_notes');
            $table->text('medication_notes')->nullable()->after('medication_given');
        });
    }
};
