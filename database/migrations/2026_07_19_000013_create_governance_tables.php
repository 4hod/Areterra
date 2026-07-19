<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->boolean('requires_read')->default(false);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('read_at');
            $table->timestamps();
            $table->unique(['document_id', 'user_id']);
        });

        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('likelihood'); // 1-5
            $table->unsignedTinyInteger('severity');   // 1-5
            $table->text('control_measures')->nullable();
            $table->date('review_date')->nullable();
            $table->string('status')->default('draft'); // draft|active|archived
            $table->foreignId('signed_off_by')->nullable()->constrained('users');
            $table->dateTime('signed_off_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('member_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->date('review_date');
            $table->text('outcomes')->nullable();
            $table->text('actions')->nullable();
            $table->date('next_review_date')->nullable();
            $table->foreignId('conducted_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('vet_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->date('visit_date');
            $table->string('reason')->nullable();
            $table->text('treatment')->nullable();
            $table->string('vet_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vet_records');
        Schema::dropIfExists('member_reviews');
        Schema::dropIfExists('risk_assessments');
        Schema::dropIfExists('document_reads');
        Schema::dropIfExists('documents');
    }
};
