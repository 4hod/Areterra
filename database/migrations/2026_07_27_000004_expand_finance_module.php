<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('additional_incomes', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->string('frequency')->default('four_weekly');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fixed_costs', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->string('category')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('frequency')->default('four_weekly');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('member_finance_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('attendance_type')->default('full_day');
            $table->decimal('custom_day_rate', 10, 2)->nullable();
            $table->decimal('one_to_one_hours_per_week', 8, 2)->default(0);
            $table->decimal('custom_one_to_one_rate', 10, 2)->nullable();
            $table->boolean('charge_transport')->default(true);
            $table->decimal('custom_transport_rate', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_finance_profiles');
        Schema::dropIfExists('fixed_costs');
        Schema::dropIfExists('additional_incomes');
    }
};
