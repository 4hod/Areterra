<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('registration');
            $table->string('make_model')->nullable();
            $table->date('mot_due')->nullable();
            $table->date('service_due')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehicle_defects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->constrained('users');
            $table->date('date');
            $table->text('description');
            $table->string('severity')->default('minor'); // minor|serious|vehicle_off_road
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('activity_date');
            $table->time('start_time')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
            $table->softDeletes();
            $table->index('activity_date');
        });

        Schema::create('recognitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(); // author
            $table->string('recipient');
            $table->text('message');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('recognition_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recognition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['recognition_id', 'user_id']);
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->string('referrer_name');
            $table->string('referrer_email')->nullable();
            $table->string('referrer_phone')->nullable();
            $table->string('organisation')->nullable();
            $table->string('person_name');
            $table->text('details')->nullable(); // encrypted
            $table->string('status')->default('pending'); // pending|accepted|declined
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('recognition_likes');
        Schema::dropIfExists('recognitions');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('vehicle_defects');
        Schema::dropIfExists('vehicles');
    }
};
