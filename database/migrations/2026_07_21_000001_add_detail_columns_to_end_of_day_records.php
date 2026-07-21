<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_of_day_records', function (Blueprint $table) {
            // Structured handover detail (WordPress parity) — replaces relying
            // on free-text notes alone for things managers routinely need to
            // scan at a glance.
            $table->string('food_intake')->nullable()->after('activities'); // good|some|poor|refused
            $table->string('fluid_intake')->nullable()->after('food_intake'); // good|some|poor|refused
            $table->text('toileting_notes')->nullable()->after('fluid_intake');
            $table->boolean('medication_given')->default(false)->after('toileting_notes');
            $table->text('medication_notes')->nullable()->after('medication_given');
            // Distinct from the safeguarding `concern` flag — a minor incident
            // (bump, trip, damaged item) that's worth a record but isn't
            // necessarily a safeguarding matter.
            $table->boolean('incident')->default(false)->after('medication_notes');
            $table->text('incident_detail')->nullable()->after('incident');
            $table->json('photos')->nullable()->after('incident_detail'); // array of storage paths
        });
    }

    public function down(): void
    {
        Schema::table('end_of_day_records', function (Blueprint $table) {
            $table->dropColumn([
                'food_intake', 'fluid_intake', 'toileting_notes',
                'medication_given', 'medication_notes',
                'incident', 'incident_detail', 'photos',
            ]);
        });
    }
};
