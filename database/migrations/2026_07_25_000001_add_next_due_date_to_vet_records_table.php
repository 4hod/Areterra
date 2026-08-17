<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vet_records', function (Blueprint $table) {
            $table->date('next_due_date')->nullable()->after('visit_date');
        });
    }

    public function down(): void
    {
        Schema::table('vet_records', function (Blueprint $table) {
            $table->dropColumn('next_due_date');
        });
    }
};
