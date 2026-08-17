<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('postcode');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
            // Set once geocoding succeeds/fails so we don't keep retrying an
            // address that couldn't be resolved on every page load.
            $table->timestamp('geocoded_at')->nullable()->after('lng');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng', 'geocoded_at']);
        });
    }
};
