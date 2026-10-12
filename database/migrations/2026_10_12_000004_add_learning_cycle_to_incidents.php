<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->text('manager_review')->nullable()->after('actions_taken');
            $table->text('learning')->nullable()->after('manager_review');
            $table->text('prevention_action')->nullable()->after('learning');
            $table->timestamp('reviewed_at')->nullable()->after('status');
            $table->timestamp('closed_at')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn(['manager_review', 'learning', 'prevention_action', 'reviewed_at', 'closed_at']);
        });
    }
};
