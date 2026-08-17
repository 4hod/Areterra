<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('welfare_checks', function (Blueprint $table) {
            $table->boolean('fed')->default(true)->after('status');
            $table->boolean('treats_given')->default(false)->after('fed');
            $table->text('treats_notes')->nullable()->after('treats_given');
        });
    }

    public function down(): void
    {
        Schema::table('welfare_checks', function (Blueprint $table) {
            $table->dropColumn(['fed', 'treats_given', 'treats_notes']);
        });
    }
};
