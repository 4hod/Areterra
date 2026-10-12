<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('welfare_checks', function (Blueprint $table) {
            $table->boolean('pellets_given')->default(false)->after('fed');
            $table->boolean('greenery_given')->default(false)->after('pellets_given');
            $table->boolean('fresh_water')->default(false)->after('greenery_given');
            $table->boolean('hay_topped_up')->default(false)->after('fresh_water');
            $table->boolean('enclosure_cleaned')->default(false)->after('hay_topped_up');
        });
    }

    public function down(): void
    {
        Schema::table('welfare_checks', function (Blueprint $table) {
            $table->dropColumn([
                'pellets_given',
                'greenery_given',
                'fresh_water',
                'hay_topped_up',
                'enclosure_cleaned',
            ]);
        });
    }
};
