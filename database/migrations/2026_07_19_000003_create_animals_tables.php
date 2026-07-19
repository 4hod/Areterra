<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('species'); // Macaw|Chinchilla|Degu|Guinea Pig|Rabbit|Chicken
            $table->date('dob')->nullable();
            $table->string('microchip')->nullable();
            $table->string('sex')->nullable();
            $table->string('breed')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status')->default('active');
            $table->string('welfare_status')->default('green'); // green|amber|red
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('welfare_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('status'); // green|amber|red
            $table->text('notes')->nullable();
            $table->boolean('concern')->default(false);
            $table->timestamps();
            $table->index(['animal_id', 'created_at']);
        });

        Schema::create('daily_monitoring', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->date('monitor_date');
            $table->foreignId('user_id')->constrained();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedTinyInteger('body_condition')->nullable(); // 1-5
            $table->string('coat_condition')->nullable();
            $table->string('appetite')->nullable();
            $table->string('droppings')->nullable();
            $table->string('behaviour')->nullable();
            $table->string('enrichment')->nullable();
            $table->unsignedInteger('enrichment_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('concern')->default(false);
            $table->timestamps();
            // Editing the same animal on the same day updates rather than duplicates.
            $table->unique(['animal_id', 'monitor_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_monitoring');
        Schema::dropIfExists('welfare_checks');
        Schema::dropIfExists('animals');
    }
};
