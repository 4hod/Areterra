<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Manual staff list for people without system accounts. Payroll references
        // payees polymorphically (User or StaffRosterMember) — no ID-offset hacks.
        Schema::create('staff_roster', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('ni_number')->nullable(); // encrypted
            $table->string('job_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payroll_rates', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable');
            $table->decimal('hourly_rate', 8, 2);
            $table->date('effective_from');
            $table->timestamps();
            $table->index(['payable_type', 'payable_id', 'effective_from'], 'payroll_rates_payable_effective_idx');
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('pay_date')->nullable();
            $table->string('authorised_by')->nullable();
            $table->string('status')->default('draft'); // draft|finalised|paid
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('payable');
            $table->string('staff_name');
            $table->text('ni_number')->nullable(); // encrypted snapshot
            $table->decimal('hourly_rate', 8, 2)->default(0);
            $table->decimal('total_hours', 6, 2)->default(0);
            $table->decimal('basic_pay', 10, 2)->default(0);
            $table->decimal('holiday_pay', 10, 2)->default(0);
            $table->decimal('total_ssp', 10, 2)->default(0);
            $table->decimal('mileage', 8, 1)->default(0);
            $table->decimal('mileage_pay', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('payroll_rates');
        Schema::dropIfExists('staff_roster');
    }
};
