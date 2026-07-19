<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Charge/payment ledger — balance is always derived, never stored.
        Schema::create('transport_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // charge|payment
            $table->decimal('amount', 8, 2);
            $table->date('entry_date');
            $table->string('method')->nullable(); // cash
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestamps();
            $table->index(['member_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_ledger');
    }
};
