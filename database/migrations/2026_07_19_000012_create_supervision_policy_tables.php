<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisions', function (Blueprint $table) {
            $table->id();
            // Subject can be a system User or a StaffRosterMember.
            $table->morphs('subject');
            $table->string('type'); // supervision|appraisal|probation_review|return_to_work|informal|disciplinary
            $table->date('date');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->foreignId('supervisor_id')->constrained('users');
            $table->text('discussion')->nullable();
            $table->text('actions_agreed')->nullable();
            $table->text('development_notes')->nullable();
            $table->date('next_due_date')->nullable();
            $table->boolean('staff_signed_off')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('policies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('body');
            $table->string('version')->default('1.0');
            $table->date('review_date')->nullable();
            $table->string('status')->default('draft'); // draft|active|archived
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policies');
        Schema::dropIfExists('supervisions');
    }
};
