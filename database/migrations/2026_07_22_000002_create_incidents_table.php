<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->dateTime('occurred_at');
            $table->string('location')->nullable();
            $table->text('description'); // encrypted
            $table->text('persons_involved')->nullable(); // encrypted
            $table->text('injury_details')->nullable(); // encrypted
            $table->string('severity')->default('minor'); // minor|moderate|serious|critical
            $table->text('actions_taken')->nullable();
            $table->boolean('follow_up_required')->default(false);
            $table->string('status')->default('open'); // open|under-review|closed
            $table->foreignId('reported_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
