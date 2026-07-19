<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('preferred_name')->nullable();
            $table->string('status')->default('active'); // active|inactive|on-leave|archived
            $table->date('dob')->nullable();
            // Encrypted at rest (special-category data) — text columns, not queryable.
            $table->text('nhs_number')->nullable();
            $table->text('support_needs')->nullable();
            $table->text('diagnoses')->nullable();
            $table->text('emergency_contacts')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('town')->nullable();
            $table->string('postcode')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('member_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('transport_required')->default(false);
            // ISO day numbers the member attends, e.g. [1,2,4,5]
            $table->json('attendance_days')->nullable();
            $table->foreignId('key_worker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_settings');
        Schema::dropIfExists('members');
    }
};
