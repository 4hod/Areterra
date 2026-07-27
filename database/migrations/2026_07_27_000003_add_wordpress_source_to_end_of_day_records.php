<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_of_day_records', function (Blueprint $table) {
            $table->unsignedBigInteger('wordpress_handover_id')->nullable()->unique()->after('id');
            $table->string('source')->default('hub')->after('wordpress_handover_id');
            $table->string('source_author_name')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('end_of_day_records', function (Blueprint $table) {
            $table->dropUnique(['wordpress_handover_id']);
            $table->dropColumn(['wordpress_handover_id', 'source', 'source_author_name']);
        });
    }
};
