<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_import_rows', function (Blueprint $table) {
            $table->id();
            $table->string('source_key')->unique();
            $table->string('source_system')->default('jotform');
            $table->string('form_key');
            $table->string('form_name');
            $table->string('entry_id');
            $table->string('source_file_name');
            $table->char('source_file_sha256', 64);
            $table->char('payload_sha256', 64);
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('recorded_at')->nullable();
            $table->string('author_name')->nullable();
            $table->string('outcome');
            $table->unsignedInteger('target_count')->default(0);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('payload');
            $table->timestamps();

            $table->index(['form_key', 'entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_import_rows');
    }
};
