<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safeguarding_concerns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users');
            $table->string('source')->default('manual'); // manual|end_of_day
            $table->foreignId('end_of_day_record_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->text('details'); // encrypted
            $table->text('actions_taken')->nullable(); // encrypted
            $table->string('status')->default('open'); // open|closed
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('compliance_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->nullable();
            $table->date('due_date');
            $table->text('notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('abc_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->dateTime('observed_at');
            $table->text('antecedent')->nullable();   // encrypted
            $table->text('behaviour');                 // encrypted
            $table->text('consequence')->nullable();   // encrypted
            $table->unsignedTinyInteger('wellbeing_score')->nullable(); // 1-5
            $table->boolean('concern')->default(false);
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });

        Schema::create('body_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->dateTime('recorded_at');
            // Markers: [{view: front|back, x: %, y: %, note}] — encrypted JSON.
            $table->text('markers');
            $table->text('notes')->nullable(); // encrypted
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_maps');
        Schema::dropIfExists('abc_observations');
        Schema::dropIfExists('compliance_items');
        Schema::dropIfExists('safeguarding_concerns');
    }
};
