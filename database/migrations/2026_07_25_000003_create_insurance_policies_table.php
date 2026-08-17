<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_type'); // e.g. Public Liability, Employer's Liability, Animal, Buildings
            $table->string('provider')->nullable();
            $table->string('policy_number')->nullable();
            $table->date('renewal_date')->nullable();
            $table->decimal('annual_cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_policies');
    }
};
