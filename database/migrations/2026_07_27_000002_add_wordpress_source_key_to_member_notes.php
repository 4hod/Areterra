<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_notes', function (Blueprint $table) {
            $table->string('wordpress_source_key', 100)
                ->nullable()
                ->unique()
                ->after('wordpress_note_id');
        });
    }

    public function down(): void
    {
        Schema::table('member_notes', function (Blueprint $table) {
            $table->dropUnique(['wordpress_source_key']);
            $table->dropColumn('wordpress_source_key');
        });
    }
};
