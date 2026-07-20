<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_rates', function (Blueprint $table) {
            // Overtime defaults to 1.5× in the UI; stored explicitly for history.
            $table->decimal('overtime_rate', 8, 2)->nullable();
            $table->decimal('contracted_hours', 5, 2)->nullable();
        });

        Schema::table('policies', function (Blueprint $table) {
            $table->string('category')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->dateTime('approved_at')->nullable();
        });

        // Hand-rolled audit trail: every create/update/delete on major models.
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('action'); // created|updated|deleted
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('changes')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
        Schema::table('policies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['category', 'approved_at']);
        });
        Schema::table('payroll_rates', function (Blueprint $table) {
            $table->dropColumn(['overtime_rate', 'contracted_hours']);
        });
    }
};
