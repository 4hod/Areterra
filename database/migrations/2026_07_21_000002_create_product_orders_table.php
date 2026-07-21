<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('item_name');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('unit')->nullable(); // e.g. boxes, kg, packs
            $table->string('category')->nullable(); // e.g. animal feed, PPE, office, activities
            $table->text('notes')->nullable();

            // pending -> approved -> ordered -> delivered
            //         \-> rejected
            $table->string('status')->default('pending');

            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->dateTime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->string('supplier')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->decimal('cost', 8, 2)->nullable();
            $table->dateTime('ordered_at')->nullable();

            $table->dateTime('delivered_at')->nullable();
            $table->text('delivery_notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_orders');
    }
};
