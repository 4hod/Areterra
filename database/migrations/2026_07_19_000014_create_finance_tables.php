<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grants', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('funder');
            $table->decimal('amount', 10, 2);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('active'); // applied|active|completed|declined
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('grant_expenditures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grant_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->date('spent_date');
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestamps();
        });

        Schema::create('in_kind_donations', function (Blueprint $table) {
            $table->id();
            $table->string('donor');
            $table->string('type')->nullable(); // goods|services|volunteer time
            $table->string('category')->nullable();
            $table->decimal('estimated_value', 10, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->date('date');
            $table->foreignId('grant_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // QuickBooks reference tracker — QB raises the invoices, the Hub tracks them.
        Schema::create('member_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('qb_reference'); // e.g. INV-0042
            $table->decimal('amount', 10, 2);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->date('paid_date')->nullable();
            $table->string('status')->default('draft'); // draft|sent|paid|overdue|cancelled
            $table->string('qb_url')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_invoices');
        Schema::dropIfExists('in_kind_donations');
        Schema::dropIfExists('grant_expenditures');
        Schema::dropIfExists('grants');
    }
};
